#!/bin/sh
set -eu

mkdir -p /var/www/html/storage/framework/cache \
  /var/www/html/storage/framework/sessions \
  /var/www/html/storage/framework/views \
  /var/www/html/storage/app/public \
  /var/www/html/bootstrap/cache \
  /var/www/html/public/facturas_pdf

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/public/facturas_pdf

if [ ! -L /var/www/html/public/storage ]; then
  rm -rf /var/www/html/public/storage
  ln -s /var/www/html/storage/app/public /var/www/html/public/storage
fi

exec /usr/bin/supervisord -c /etc/supervisord.conf
