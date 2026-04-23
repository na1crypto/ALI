FROM php:8.2-apache
RUN docker-php-ext-install mysqli pdo pdo_mysql
COPY . /var/www/html/
RUN chmod -R 755 /var/www/html/
RUN printf '#!/bin/bash\nset -e\nrm -f /etc/apache2/mods-enabled/mpm_event.conf\nrm -f /etc/apache2/mods-enabled/mpm_event.load\nrm -f /etc/apache2/mods-enabled/mpm_worker.conf\nrm -f /etc/apache2/mods-enabled/mpm_worker.load\nln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load\nln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf\nsed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf\nsed -i "s/*:80>/*:$PORT>/" /etc/apache2/sites-enabled/000-default.conf\nexec apache2-foreground\n' > /start.sh && chmod +x /start.sh
CMD ["/start.sh"]
