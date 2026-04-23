FROM php:8.2-apache
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true && a2enmod mpm_prefork rewrite
COPY . /var/www/html/
RUN chmod -R 755 /var/www/html/
CMD bash -c "sed -i 's/Listen 80/Listen '\"$PORT\"'/' /etc/apache2/ports.conf && sed -i 's/*:80>/*:'\"$PORT\"'>/' /etc/apache2/sites-enabled/000-default.conf && apache2-foreground"
