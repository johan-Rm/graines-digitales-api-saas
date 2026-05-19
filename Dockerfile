FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        acl \
        bash \
        git \
        icu-dev \
        libpq-dev \
        linux-headers \
        unzip \
        zlib-dev \
    && docker-php-ext-install intl opcache pdo pdo_pgsql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /srv/api

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

CMD ["php-fpm"]
