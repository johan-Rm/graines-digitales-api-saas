# PHP2Shell : version minimale corrigee pour la branche PHP 8.4.
FROM php:8.4.27-fpm-alpine

RUN apk add --no-cache \
        acl \
        autoconf \
        bash \
        g++ \
        git \
        icu-dev \
        imagemagick \
        imagemagick-dev \
        imagemagick-jpeg \
        imagemagick-webp \
        libpq-dev \
        linux-headers \
        make \
        pkgconfig \
        unzip \
        zlib-dev \
    && docker-php-ext-install intl opcache pdo pdo_pgsql \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && cd /tmp && git clone --depth 1 https://github.com/Imagick/imagick.git \
    && cd /tmp/imagick && phpize && ./configure && make -j"$(nproc)" && make install \
    && docker-php-ext-enable imagick \
    && rm -rf /tmp/imagick

# Composer via l'image officielle : il s'exécute sous /usr/local/bin/php (toutes
# les extensions), sans tirer le paquet Alpine php84 et son PHP concurrent.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN addgroup -g 1000 app && adduser -D -u 1000 -G app app
RUN git config --system --add safe.directory /srv/api

WORKDIR /srv/api

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

CMD ["php-fpm"]
