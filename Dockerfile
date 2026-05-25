FROM php:8.3-apache

# Apache mod_rewrite yoqish
RUN a2enmod rewrite

# PHP kengaytmalari
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Apache: AllowOverride All (session, redirect uchun)
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# PHP session papkasiga ruxsat
RUN mkdir -p /var/lib/php/sessions && chmod 777 /var/lib/php/sessions

# Fayllarni ko'chirish
COPY . /var/www/html/

# Ruxsatlar
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 80
