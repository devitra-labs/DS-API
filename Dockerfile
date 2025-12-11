FROM php:8.2-fpm

# 1. Install nginx DAN curl (curl wajib ada untuk download)
RUN apt-get update && apt-get install -y nginx curl && rm -rf /var/lib/apt/lists/*

# 2. Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# 3. Copy app (Source code kamu)
COPY . /var/www/html/

# =======================================================
# SOLUSI FIX: Download sertifikat langsung di sini
# Jadi tidak peduli file ada di GitHub atau tidak
# =======================================================
RUN curl -o /etc/ssl/certs/tidb-cloud.pem https://curl.se/ca/cacert.pem

# 4. Copy nginx template
COPY nginx.conf.template /etc/nginx/nginx.conf.template

# 5. Copy start script
COPY start.sh /start.sh
RUN chmod +x /start.sh

# 6. Ubah permission agar PHP bisa baca
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["/start.sh"]