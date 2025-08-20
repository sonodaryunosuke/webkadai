FROM php:8.4-fpm-alpine AS php
RUN docker-php-ext-install pdo_mysql



RUN install -o www-data -g www-data -d /var/www/upload/image/
RUN apk add -U --no-cache curl-dev



RUN docker-php-ext-install curl
RUN docker-php-ext-install exif
