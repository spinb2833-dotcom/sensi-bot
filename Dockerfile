# ============================================
# SENSI MODS - DOCKERFILE FOR RENDER
# ============================================

FROM php:8.2-apache

# ── INSTALL POSTGRESQL DRIVER ──
RUN apt-get update && apt-get install -y libpq-dev && \
    docker-php-ext-install pdo_pgsql

# ── COPY ALL FILES ──
COPY . /var/www/html/

# ── ENABLE REWRITE ──
RUN a2enmod rewrite

# ── SET PERMISSIONS ──
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# ── USE PORT 10000 ──
EXPOSE 10000

# ── START PHP SERVER ──
CMD ["php", "-S", "0.0.0.0:10000", "-t", "/var/www/html"]
