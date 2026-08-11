FROM php:8.2-apache

# Enable mod_rewrite
RUN a2enmod rewrite

# Copy all files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Install PostgreSQL driver
RUN apt-get update && apt-get install -y libpq-dev && \
    docker-php-ext-install pdo_pgsql

# Set environment variable
ENV DATABASE_URL=${DATABASE_URL}

EXPOSE 80