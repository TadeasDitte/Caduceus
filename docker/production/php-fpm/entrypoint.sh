#!/bin/sh
set -e

# storage/ is a named volume. Docker creates it empty, so seed it from the copy
# baked into the image on first boot.
if [ ! "$(ls -A /var/www/storage)" ]; then
    echo "Initialising storage directory..."
    cp -R /var/www/storage-init/. /var/www/storage
fi

# The queue worker shares this image and this entrypoint; only one container
# should touch the schema, and deployments that migrate out of band can turn it
# off entirely.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "Running migrations..."
    php artisan migrate --force
fi

echo "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
