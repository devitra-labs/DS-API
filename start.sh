#!/bin/sh

# Pastikan script berhenti jika ada command yang error
set -e

# Ganti variabel __PORT__ di nginx.conf dengan port asli dari Railway
sed "s/__PORT__/${PORT}/g" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Validasi config Nginx dulu sebelum dijalankan
nginx -t

# Start Nginx sebagai background process (Daemon)
echo "Starting Nginx..."
nginx

# Start PHP-FPM di foreground (agar container tidak mati)
echo "Starting PHP-FPM..."
php-fpm