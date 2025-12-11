FROM php:8.2-fpm

# 1. Install nginx & curl
RUN apt-get update && apt-get install -y nginx curl && rm -rf /var/lib/apt/lists/*

# 2. Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# 3. Copy app
COPY . /var/www/html/

# 4. Download Certificate
RUN curl -o /etc/ssl/certs/tidb-cloud.pem https://curl.se/ca/cacert.pem

# 5. COPY php.ini (INI YANG BARU)
# Copy file konfigurasi error kita ke folder config PHP
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# 6. Copy nginx & start script
COPY nginx.conf.template /etc/nginx/nginx.conf.template
COPY start.sh /start.sh
RUN chmod +x /start.sh

# 7. Permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["/start.sh"]