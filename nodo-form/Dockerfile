FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libpng-dev libjpeg-dev libwebp-dev libzip-dev unzip git \
 && docker-php-ext-configure gd --with-jpeg --with-webp && docker-php-ext-install gd zip exif \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www
RUN composer require dompdf/dompdf phpmailer/phpmailer --no-interaction
COPY php.ini /usr/local/etc/php/conf.d/app.ini
COPY app/ /var/www/html/
RUN mkdir -p /var/www/data && chown -R www-data:www-data /var/www/data
