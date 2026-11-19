# Образ для Railway / любого Docker-хостинга: PHP 8.3 + Apache
FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev default-mysql-client \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql gd exif \
 && a2enmod rewrite headers deflate expires \
 && (a2dismod -f mpm_event mpm_worker || true) && a2enmod mpm_prefork \
 && rm -rf /var/lib/apt/lists/*

COPY deploy/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY deploy/apache-site.conf /etc/apache2/sites-available/000-default.conf
RUN printf "ServerTokens Prod\nServerSignature Off\nServerName localhost\n" >> /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY . .
RUN rm -f .env && mkdir -p storage/logs public/uploads \
 && chown -R www-data:www-data storage public/uploads

COPY deploy/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
