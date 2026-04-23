FROM php:8.2-apache
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN rm -f /etc/apache2/mods-enabled/mpm_event.conf \
          /etc/apache2/mods-enabled/mpm_event.load \
          /etc/apache2/mods-enabled/mpm_worker.conf \
          /etc/apache2/mods-enabled/mpm_worker.load && \
    ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf && \
    ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
COPY . /var/www/html/
RUN chmod -R 755 /var/www/html/
CMD bash -c "sed -i \"s/Listen 80/Listen \${PORT}/\" /etc/apache2/ports.conf && sed -i \"s/*:80>/*:\${PORT}>/\" /etc/apache2/sites-enabled/000-default.conf && apache2-foreground"
