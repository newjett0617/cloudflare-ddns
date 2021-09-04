FROM docker.io/library/php:7.4-alpine as build
WORKDIR /app
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock index.php ./
RUN composer install --no-dev

FROM docker.io/library/php:7.4-alpine
WORKDIR /app
COPY --from=build /app ./
CMD [ "php", "index.php" ]
