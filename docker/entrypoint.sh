#!/bin/sh
set -e

if [ ! -f vendor/autoload.php ]; then
    echo "vendor/autoload.php missing (fresh clone) — running composer install..."
    composer install --no-interaction --no-progress --prefer-dist
fi

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
