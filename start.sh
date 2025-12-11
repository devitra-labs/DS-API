#!/bin/sh

# Replace port
sed "s/__PORT__/${PORT}/g" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Start nginx
nginx

# Start PHP-FPM
php-fpm
