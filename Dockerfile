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

FROM php:8.2-fpm-alpine
WORKDIR /var/www/html

RUN apk add --no-cache nginx supervisor gettext \
    && docker-php-ext-install pdo pdo_mysql \
    && mkdir -p /run/nginx

COPY . /var/www/html
COPY --from=composer /app/vendor /var/www/html/vendor
COPY --from=assets /app/public/build /var/www/html/public/build

COPY docker/nginx.conf.template /etc/nginx/http.d/default.conf.template
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/start.sh /usr/local/bin/start-app

RUN mkdir -p /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/app/public \
    /var/www/html/bootstrap/cache \
    /var/www/html/public/facturas_pdf \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public/facturas_pdf \
    && chmod +x /usr/local/bin/start-app

EXPOSE 80
CMD ["/usr/local/bin/start-app"]
