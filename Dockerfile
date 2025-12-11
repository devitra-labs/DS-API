FROM php:8.2-fpm

# Install MySQL/TiDB drivers
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Install Nginx
RUN apt-get update && apt-get install -y nginx && rm -rf /var/lib/apt/lists/*

# Copy project
COPY . /var/www/html/

# Nginx config
COPY nginx.conf /etc/nginx/nginx.conf

# Permission
RUN chown -R www-data:www-data /var/www/html

# Expose port
EXPOSE 80

CMD service nginx start && php-fpm
