FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

RUN { \
    echo 'upload_max_filesize = 12M'; \
    echo 'post_max_size = 12M'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

COPY public/ /var/www/html/
