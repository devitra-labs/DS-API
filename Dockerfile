# --- Base PHP image ---
FROM php:8.2-cli

# --- Install required extensions for TiDB (MySQL) ---
RUN apt-get update && apt-get install -y \
    default-mysql-client \
    libssl-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-install pdo pdo_mysql

# --- Set working directory ---
WORKDIR /app

# --- Copy project files ---
COPY . /app

# Railway runs service on port 8080
EXPOSE 8080

# --- Start PHP built-in server ---
CMD ["php", "-S", "0.0.0.0:8080", "index.php"]
