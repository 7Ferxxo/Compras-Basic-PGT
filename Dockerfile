# syntax=docker/dockerfile:1

FROM php:8.2-cli-alpine AS composer
WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN apk add --no-cache git unzip libzip-dev zlib-dev $PHPIZE_DEPS curl \
    && docker-php-ext-install zip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM php:8.2-apache
WORKDIR /var/www/html

RUN a2dismod mpm_event mpm_worker \
    && a2enmod mpm_prefork \
    && a2enmod rewrite \
    && docker-php-ext-install pdo pdo_mysql \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e 's!/var/www/!/var/www/html/public!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY . /var/www/html
COPY --from=composer /app/vendor /var/www/html/vendor
COPY --from=assets /app/public/build /var/www/html/public/build

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80
CMD ["apache2-foreground"]
