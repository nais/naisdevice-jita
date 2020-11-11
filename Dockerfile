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
RUN a2enmod rewrite
RUN echo "export ISSUER_ENTITY_ID" >> /etc/apache2/envvars && \
    echo "export LOGIN_URL" >> /etc/apache2/envvars && \
    echo "export LOGOUT_URL" >> /etc/apache2/envvars && \
    echo "export SAML_CERT" >> /etc/apache2/envvars && \
    echo "export API_PASSWORD" >> /etc/apache2/envvars && \
    echo "export DB_DSN" >> /etc/apache2/envvars
COPY --from=build /app/vendor/ /var/www/vendor/
COPY templates/ /var/www/templates/
COPY src/ /var/www/src/
COPY public/ /var/www/html/
