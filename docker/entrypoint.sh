#!/bin/sh
set -eu
umask 077
cd /var/www/html

mkdir -p storage/app/private storage/app/public     storage/framework/cache/data storage/framework/cache/locks     storage/framework/sessions storage/framework/views storage/logs     bootstrap/cache database/data

key_file=/var/www/html/storage/app/private/docker-app-key
if [ "${CONTAINER_ROLE:-web}" != scheduler ]; then
    exec 9>storage/app/private/.docker-init.lock
    flock -x 9
    if [ -s "$key_file" ]; then
        APP_KEY=$(cat "$key_file")
        if [ -n "${PORTAL_APP_KEY:-}" ] && [ "$PORTAL_APP_KEY" != "$APP_KEY" ]; then
            echo "Configured key differs from the persisted key. Restore matching data and key." >&2
            exit 1
        fi
    else
        APP_KEY=${PORTAL_APP_KEY:-}
        if [ -z "$APP_KEY" ]; then
            APP_KEY=$(php -r 'echo "base64:".base64_encode(random_bytes(32));')
        fi
        export APP_KEY
        php -r '$key=getenv("APP_KEY"); if (!str_starts_with($key,"base64:") || strlen(base64_decode(substr($key,7),true) ?: "") !== 32) {fwrite(STDERR,"APP_KEY must be a base64-encoded 32-byte key.\n"); exit(1);}'
        printf '%s\n' "$APP_KEY" > "$key_file"
    fi
    export APP_KEY
    printf 'APP_KEY=%s\n' "$APP_KEY" > .env
    touch database/data/database.sqlite
    chown -R www-data:www-data storage bootstrap/cache database/data
    chown www-data:www-data .env
    chmod 600 .env "$key_file"
    gosu www-data php artisan migrate --force --no-interaction
    gosu www-data php artisan portal:push-setup --no-interaction
    flock -u 9
    exec 9>&-
else
    if [ ! -s "$key_file" ]; then
        echo "Start the web container and initialize the persistent volumes first." >&2
        exit 1
    fi
    APP_KEY=$(cat "$key_file")
    export APP_KEY
    printf 'APP_KEY=%s\n' "$APP_KEY" > .env
    chown www-data:www-data .env
    chmod 600 .env
fi

if [ "${1:-}" = apache2-foreground ]; then
    exec "$@"
fi
exec gosu www-data "$@"
