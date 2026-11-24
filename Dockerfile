FROM php:8.4-apache-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates gosu util-linux unzip \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libicu-dev \
        libzip-dev libonig-dev libsqlite3-dev libgmp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath gd gmp intl mbstring pcntl zip \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/portal.ini

FROM php-base AS build
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && mkdir -p bootstrap/cache storage/app/private storage/app/public \
        storage/framework/cache/data storage/framework/cache/locks \
        storage/framework/sessions storage/framework/views storage/logs database/data \
    && php artisan package:discover --no-interaction

FROM php-base AS production
WORKDIR /var/www/html
COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/portal-entrypoint
ENTRYPOINT ["portal-entrypoint"]
CMD ["apache2-foreground"]
