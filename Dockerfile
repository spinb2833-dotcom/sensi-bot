FROM php:8.2-apache

RUN apt-get update && apt-get install -y libpq-dev && \
    docker-php-ext-install pdo_pgsql

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

EXPOSE 10000

CMD ["php", "-S", "0.0.0.0:10000", "-t", "/var/www/html"]
