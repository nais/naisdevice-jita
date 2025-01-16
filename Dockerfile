FROM php:8.3-cli AS build

RUN apt-get update && \
    apt-get install -y git && \
    apt-get install -y unzip
RUN pecl install apcu \
    && docker-php-ext-enable apcu

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
COPY composer.json composer.lock src ./
RUN composer install -o --no-dev

FROM php:8.3-apache
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
