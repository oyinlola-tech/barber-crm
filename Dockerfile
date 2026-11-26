FROM node:24-alpine AS frontend

WORKDIR /var/www/html

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN npm install --global pnpm@11.24.0 \
    && pnpm install --frozen-lockfile

COPY resources ./resources
COPY vite.config.ts tsconfig.json components.json ./
RUN npm run build

FROM composer:2 AS vendor

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts

FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache $PHPIZE_DEPS icu-dev oniguruma-dev postgresql-dev \
    && docker-php-ext-install bcmath intl mbstring pcntl pdo_pgsql \
    && apk del $PHPIZE_DEPS \
    && docker-php-ext-enable opcache

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
COPY --from=frontend /var/www/html/public/build ./public/build
RUN php artisan package:discover --ansi

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-barber-crm.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/10-opcache.ini

RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data bootstrap/cache storage

USER www-data

EXPOSE 9000

CMD ["php-fpm"]

FROM nginx:stable-alpine AS web

COPY --from=runtime /var/www/html/public /var/www/html/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
