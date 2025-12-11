#!/bin/sh

# Generate nginx config using PORT from Railway
sed "s/__PORT__/${PORT}/g" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Start nginx
nginx

# Start PHP-FPM
php-fpm
