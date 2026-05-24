FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        acl \
        bash \
        git \
        icu-dev \
        imagemagick \
        imagemagick-dev \
        libpq-dev \
        linux-headers \
        unzip \
        zlib-dev \
    && docker-php-ext-install intl opcache pdo pdo_pgsql \
    && pecl install imagick \
    && docker-php-ext-enable imagick

RUN addgroup -g 1000 app && adduser -D -u 1000 -G app app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /srv/api

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

CMD ["php-fpm"]
