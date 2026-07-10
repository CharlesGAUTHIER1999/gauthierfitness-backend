#!/bin/sh
set -e

# docker-compose.yml bind-mounts the host directory over /var/www/html, which
# shadows the vendor/ built at image time whenever the host has no vendor/ of
# its own (fresh clone, Docker-only setup, no local Composer). Rebuild it here
# so the container is self-sufficient regardless of host state.
if [ ! -f vendor/autoload.php ]; then
    echo "vendor/autoload.php missing (fresh clone) — running composer install..."
    composer install --no-interaction --no-progress --prefer-dist
fi

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
