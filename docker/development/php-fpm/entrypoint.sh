#!/bin/sh
set -e

# Composer dependencies live on the host and are bind-mounted in, so a fresh
# clone can reach this point without them. Fail loudly instead of dying on an
# unreadable autoloader.
if [ ! -f /var/www/vendor/autoload.php ]; then
    echo "vendor/ is missing — run 'composer install' (e.g. in the workspace container) and restart." >&2
    exec "$@"
fi

echo "Clearing cached configuration..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

exec "$@"
