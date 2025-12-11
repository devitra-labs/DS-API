FROM php:8.2-apache

# Install dependency untuk MySQL/TiDB + tools
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache mod_rewrite (optional)
RUN a2enmod rewrite

# Copy semua file project
COPY . /var/www/html/

# Permission storage/cache jika perlu
RUN mkdir -p /var/www/html/storage/cache/bmkg \
    && chmod -R 777 /var/www/html/storage
