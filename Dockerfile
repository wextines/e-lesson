FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql curl gd \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache.conf /etc/apache2/conf-available/iq-creative.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/iq-creative.ini

RUN a2enconf iq-creative
