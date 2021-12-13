FROM php:7.4-cli-alpine AS build
WORKDIR /app
COPY composer.json composer.lock src templates ./
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    EXPECTED_SIGNATURE="$(wget -q -O - https://composer.github.io/installer.sig)" && \
    ACTUAL_SIGNATURE="$(php -r "echo hash_file('sha384', 'composer-setup.php');")" && \
    [[ "$ACTUAL_SIGNATURE" == "$EXPECTED_SIGNATURE" ]] || { echo >&2 "Corrupt installer"; exit 1; } && \
    php composer-setup.php && \
    php -r "unlink('composer-setup.php');"
RUN php composer.phar install -o --no-dev

FROM php:7.4-apache
RUN apt-get update && \
    apt-get install -y libpq-dev && \
    docker-php-ext-install pdo_pgsql
RUN pecl install apcu \
    && docker-php-ext-enable apcu
RUN a2enmod rewrite
COPY --from=build /app/vendor/ /var/www/vendor/
COPY templates/ /var/www/templates/
COPY scripts/ /var/www/scripts/
COPY src/ /var/www/src/
COPY public/ /var/www/html/
COPY entrypoint-wrapper.sh /usr/local/bin/entrypoint-wrapper

RUN sed -i "s/80/8080/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE 8080

ENTRYPOINT [ "entrypoint-wrapper" ]
