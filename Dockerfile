FROM php:8.3-apache

RUN a2enmod rewrite \
    && printf '%s\n' \
       '<Directory /var/www/html>' \
       '    AllowOverride All' \
       '    Require all granted' \
       '</Directory>' \
       > /etc/apache2/conf-available/justopen.conf \
    && a2enconf justopen

WORKDIR /var/www/html
COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD php -r '$$c=@file_get_contents("http://127.0.0.1/"); exit($$c===false ? 1 : 0);'
