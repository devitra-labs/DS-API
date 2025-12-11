FROM php:8.2-fpm

# 1. Install nginx & curl
RUN apt-get update && apt-get install -y nginx curl && rm -rf /var/lib/apt/lists/*

# 2. Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# 3. Copy app
COPY . /var/www/html/

# 4. Download Certificate (SOLUSI CURL YANG KITA PAKAI TADI)
RUN curl -o /etc/ssl/certs/tidb-cloud.pem https://curl.se/ca/cacert.pem

# ==============================================================================
# ### LOGGING FIX (TAMBAHKAN INI)
# Ini memaksa PHP-FPM untuk menampilkan error pekerja (workers) ke log utama
# Tanpa ini, error 500 akan tetap misterius.
# ==============================================================================
RUN echo "catch_workers_output = yes" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "php_admin_flag[log_errors] = on" >> /usr/local/etc/php-fpm.d/www.conf && \
    echo "php_admin_value[error_log] = /proc/self/fd/2" >> /usr/local/etc/php-fpm.d/www.conf

# 5. Copy nginx config & start script
COPY nginx.conf.template /etc/nginx/nginx.conf.template
COPY start.sh /start.sh
RUN chmod +x /start.sh

# 6. Permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["/start.sh"]