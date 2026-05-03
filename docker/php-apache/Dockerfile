ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-apache-bookworm

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_CACHE_DIR=/tmp/composer-cache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libmcrypt-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libsqlite3-dev \
        libssl-dev \
        libwebp-dev \
        libxml2-dev \
        libzip-dev \
        mariadb-client \
        unzip \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        gettext \
        intl \
        mbstring \
        mysqli \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        pgsql \
        soap \
        sockets \
        zip \
    && (yes '' | pecl install mcrypt && docker-php-ext-enable mcrypt || echo "PECL mcrypt unavailable; legacy mcrypt code must be refactored") \
    && a2enmod rewrite headers expires remoteip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php-apache/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php-apache/php.ini /usr/local/etc/php/conf.d/a2billingplus.ini
COPY docker/php-apache/entrypoint.sh /usr/local/bin/a2bp-entrypoint

RUN chmod +x /usr/local/bin/a2bp-entrypoint \
    && mkdir -p /tmp/composer-cache \
    && chown -R www-data:www-data /tmp/composer-cache /var/www/html

WORKDIR /var/www/html

ENTRYPOINT ["a2bp-entrypoint"]
CMD ["apache2-foreground"]
