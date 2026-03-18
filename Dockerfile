FROM php:8.1-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN a2enmod rewrite

# PHP xatolarini ko'rsatish (debug uchun)
RUN echo "display_errors = On" > /usr/local/etc/php/conf.d/errors.ini
RUN echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/errors.ini
RUN echo "log_errors = On" >> /usr/local/etc/php/conf.d/errors.ini

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html/

CMD sed -i "s/80/${PORT:-80}/g" /etc/apache2/ports.conf /etc/apache2/sites-enabled/*.conf && apache2-foreground