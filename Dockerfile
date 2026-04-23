FROM php:8.2-apache
RUN docker-php-ext-install mysqli pdo pdo_mysql
COPY . /var/www/html/
RUN chmod -R 755 /var/www/html/ && \
    chmod +x /var/www/html/entrypoint.sh
CMD ["/var/www/html/entrypoint.sh"]
