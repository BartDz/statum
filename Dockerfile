FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

COPY . /var/www/html/

RUN mkdir -p /var/www/html/db \
    && chown -R www-data:www-data /var/www/html/db \
    && chmod 755 /var/www/html/db

WORKDIR /var/www/html

EXPOSE 80
