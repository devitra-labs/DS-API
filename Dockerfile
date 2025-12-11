FROM php:8.2-fpm

# Install nginx
RUN apt-get update && apt-get install -y nginx && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copy app (Salin semua file project)
COPY . /var/www/html/

# ### BARU: Salin cacert.pem ke folder sistem SSL agar path-nya PASTI dan AMAN
COPY cacert.pem /etc/ssl/certs/tidb-cloud.pem

# Copy nginx template
COPY nginx.conf.template /etc/nginx/nginx.conf.template

# Copy start script
COPY start.sh /start.sh
RUN chmod +x /start.sh

# ### BARU: Ubah kepemilikan file ke www-data agar PHP bisa membacanya
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["/start.sh"]