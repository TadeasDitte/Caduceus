# syntax=docker/dockerfile:1
#
# Caduceus image definitions.
#
# Caduceus is an Inertia monolith: PHP renders the pages and Vite builds the
# assets that go with them. Both halves therefore come out of a single build so
# the compiled manifest PHP reads can never drift from the files Nginx serves.
#
#   base        shared PHP runtime (extensions + Composer)
#   node        Node.js + pnpm distribution, copied into the stages that need it
#   development PHP-FPM for local work (Xdebug, host UID/GID, code bind-mounted)
#   workspace   development + Node, for artisan/composer/pnpm and the Vite server
#   vendor      base + application source + Composer dependencies
#   assets      vendor + Node, produces public/build
#   production  PHP-FPM for deployment (code and assets baked in)
#   nginx       production web server (static assets baked in)
#
# Targets are selected from compose.dev.yml / compose.prod.yml. The development
# stages come first on purpose: without BuildKit (`docker-buildx` is a separate
# package) the classic builder walks every stage above the requested target, so
# a local build would otherwise pay for Composer and the whole asset pipeline.

ARG NODE_VERSION=26.4.0
ARG PNPM_VERSION=11.3.0


###############################################################################
# Stage: base
###############################################################################
FROM php:8.5-fpm AS base

# Extensions the application actually needs: PostgreSQL (pdo_pgsql/pgsql),
# intl and zip for the framework, bcmath for the WebAuthn/passkey stack, and
# redis for the cache and queue drivers.
RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        unzip \
        xz-utils \
        libcurl4-openssl-dev \
        libicu-dev \
        libonig-dev \
        libpq-dev \
        libssl-dev \
        libxml2-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        pdo_pgsql \
        pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get autoremove -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY ./docker/common/php-fpm/conf.d/*.conf /usr/local/etc/php-fpm.d/

WORKDIR /var/www


###############################################################################
# Stage: node
#
# Debian's packaged Node is too old for Vite 8, so the official distribution is
# unpacked into a self-contained prefix that later stages copy into /usr/local.
###############################################################################
FROM base AS node

ARG NODE_VERSION
ARG PNPM_VERSION

ENV PATH=/opt/node/bin:$PATH

RUN set -eux; \
    case "$(uname -m)" in \
        x86_64)  node_arch=x64 ;; \
        aarch64) node_arch=arm64 ;; \
        *) echo "Unsupported architecture: $(uname -m)" >&2; exit 1 ;; \
    esac; \
    mkdir -p /opt/node; \
    curl -fsSL "https://nodejs.org/dist/v${NODE_VERSION}/node-v${NODE_VERSION}-linux-${node_arch}.tar.xz" -o /tmp/node.tar.xz; \
    tar -xJf /tmp/node.tar.xz -C /opt/node --strip-components=1 --no-same-owner; \
    rm /tmp/node.tar.xz; \
    npm install -g "pnpm@${PNPM_VERSION}"; \
    node --version; \
    pnpm --version


###############################################################################
# Stage: development (PHP-FPM)
#
# Built from `base` rather than `production` so local builds never pay for the
# asset pipeline — Vite serves those from its own container.
###############################################################################
FROM base AS development

ARG XDEBUG_ENABLED=true
ARG XDEBUG_MODE=develop,coverage,debug,profile
ARG XDEBUG_HOST=host.docker.internal
ARG XDEBUG_IDE_KEY=DOCKER
ARG XDEBUG_LOG=/dev/stdout
ARG XDEBUG_LOG_LEVEL=0

# start_with_request=trigger keeps requests fast unless XDEBUG_TRIGGER is sent.
RUN if [ "${XDEBUG_ENABLED}" = "true" ]; then \
        pecl install xdebug \
        && docker-php-ext-enable xdebug \
        && { \
            echo "xdebug.mode=${XDEBUG_MODE}"; \
            echo "xdebug.idekey=${XDEBUG_IDE_KEY}"; \
            echo "xdebug.log=${XDEBUG_LOG}"; \
            echo "xdebug.log_level=${XDEBUG_LOG_LEVEL}"; \
            echo "xdebug.client_host=${XDEBUG_HOST}"; \
            echo "xdebug.start_with_request=trigger"; \
        } >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini ; \
    fi

# Match the host user so files written from a container (logs, caches, the
# generated Wayfinder helpers) stay editable outside of it.
ARG UID=1000
ARG GID=1000

RUN if getent group "${GID}" > /dev/null; then \
        group_name="$(getent group "${GID}" | cut -d: -f1)"; \
        useradd -m -u "${UID}" -g "${GID}" -s /bin/bash www; \
    else \
        groupadd -g "${GID}" www \
        && useradd -m -u "${UID}" -g www -s /bin/bash www; \
        group_name=www; \
    fi \
    && sed -i "s/^user = www-data/user = www/" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i "s/^group = www-data/group = ${group_name}/" /usr/local/etc/php-fpm.d/www.conf

COPY ./docker/development/php-fpm/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www

ENV HOME=/home/www

USER www

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

EXPOSE 9000
CMD ["php-fpm"]


###############################################################################
# Stage: workspace
#
# The shell you actually work in, and the image the Vite dev server runs from.
# Everything (php, artisan, composer, node, pnpm) is available here.
###############################################################################
FROM development AS workspace

ARG UID=1000
ARG GID=1000

USER root

COPY --from=node /opt/node /usr/local

# node_modules is a named volume shared with the Vite container; Docker seeds a
# new volume from the image, so the directory has to exist with the right owner.
RUN mkdir -p /var/www/node_modules \
    && chown "${UID}:${GID}" /var/www/node_modules

ENV PNPM_HOME=/home/www/.local/share/pnpm
ENV PATH=$PNPM_HOME:$PATH

USER www

# Bypass the php-fpm entrypoint: this image runs shells and dev servers.
ENTRYPOINT []
CMD ["bash"]


###############################################################################
# Stage: vendor
###############################################################################
FROM base AS vendor

# Dependencies are resolved from the manifests alone first, so editing app code
# does not re-download the whole tree. Scripts and the autoloader are skipped
# here because Composer's post-autoload-dump hook runs `php artisan
# package:discover`, which boots the framework and needs the full source.
COPY composer.json composer.lock /var/www/

RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --no-progress \
        --prefer-dist

COPY . /var/www

# Everything is downloaded by now, so this only runs the scripts and writes the
# optimised autoloader.
RUN composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --no-progress \
        --prefer-dist


###############################################################################
# Stage: assets
###############################################################################
FROM base AS assets

COPY --from=node /opt/node /usr/local

# Same trick as the vendor stage: install from the manifests, then bring in the
# code. node_modules survives the source copy because it is .dockerignored.
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml .npmrc /var/www/

RUN pnpm install --frozen-lockfile

COPY --from=vendor /var/www /var/www

# Wayfinder generates its TypeScript helpers by booting the framework during
# the Vite build, so a key has to be readable even though nothing is encrypted
# here. This stage only ever contributes public/build to the runtime images.
ENV APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
ENV APP_ENV=production

RUN pnpm run build


###############################################################################
# Stage: production (PHP-FPM)
###############################################################################
FROM php:8.5-fpm AS production

# Runtime libraries for the extensions copied over from the base stage.
# libfcgi-bin and procps are required by the php-fpm healthcheck script.
RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        libfcgi-bin \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        procps \
    && apt-get autoremove -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN curl -fsSL -o /usr/local/bin/php-fpm-healthcheck \
        https://raw.githubusercontent.com/renatomefi/php-fpm-healthcheck/master/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck

COPY --from=base /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=base /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=base /usr/local/etc/php-fpm.d/ /usr/local/etc/php-fpm.d/

# PHP ships a development and a production php.ini; use the latter.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=vendor /var/www /var/www
COPY --from=assets /var/www/public/build /var/www/public/build
COPY ./docker/production/php-fpm/entrypoint.sh /usr/local/bin/entrypoint.sh

# storage/ is a volume at runtime and Docker creates it empty, so keep a
# pristine copy the entrypoint can seed it from.
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && cp -R /var/www/storage /var/www/storage-init \
    && chown -R www-data:www-data /var/www

WORKDIR /var/www

USER www-data

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

EXPOSE 9000
CMD ["php-fpm"]


###############################################################################
# Stage: nginx (production web server)
###############################################################################
FROM nginx:alpine AS nginx

COPY ./docker/production/nginx/nginx.conf /etc/nginx/nginx.conf
COPY --from=vendor /var/www/public /var/www/public
COPY --from=assets /var/www/public/build /var/www/public/build

WORKDIR /var/www

EXPOSE 8000
CMD ["nginx", "-g", "daemon off;"]
