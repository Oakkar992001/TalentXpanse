FROM composer:2 AS vendor

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

COPY . ./
RUN composer dump-autoload --no-dev --optimize

FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx supervisor gettext libpq-dev oniguruma-dev \
    && docker-php-ext-install mbstring pdo_pgsql \
    && mkdir -p /run/nginx /var/www/html/storage/framework/cache /var/www/html/storage/framework/sessions /var/www/html/storage/framework/views

WORKDIR /var/www/html

COPY --from=vendor /var/www/html /var/www/html
COPY docker/nginx.conf.template /etc/nginx/http.d/default.conf.template
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/start-container /usr/local/bin/start-container

RUN chmod +x /usr/local/bin/start-container \
    && chown -R nobody:nobody /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 10000

CMD ["/usr/local/bin/start-container"]
