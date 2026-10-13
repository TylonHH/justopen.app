FROM php:8.3-apache-bookworm

RUN php -r 'exit(in_array("sqlite", PDO::getAvailableDrivers(), true) ? 0 : 1);' \
    && a2enmod rewrite \
    && printf '%s\n' \
       '<Directory /var/www/html>' \
       '    AllowOverride All' \
       '    Require all granted' \
       '</Directory>' \
       > /etc/apache2/conf-available/justopen.conf \
    && a2enconf justopen

WORKDIR /var/www/html
COPY . /var/www/html/

ENV JUSTOPEN_DATA_DIR=/var/lib/justopen
RUN mkdir -p /var/lib/justopen \
    && chown -R www-data:www-data /var/lib/justopen \
    && printf 'expose_php=Off\ndisplay_errors=Off\nlog_errors=On\n' > /usr/local/etc/php/conf.d/justopen.ini

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD php -r '$c=@file_get_contents("http://127.0.0.1/healthz"); exit($c===false ? 1 : 0);'
