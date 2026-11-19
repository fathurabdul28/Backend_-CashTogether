FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative --ignore-platform-reqs

FROM php:8.3-cli-alpine
# zip: dibutuhkan openspout untuk menulis file .xlsx
RUN apk add --no-cache ca-certificates libzip-dev && docker-php-ext-install pdo_mysql opcache zip
WORKDIR /var/www
COPY --from=vendor /app /var/www
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache
USER www-data
ENV PORT=10000
EXPOSE 10000
CMD ["sh", "-c", "php artisan config:cache && php artisan route:cache && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT}"]
