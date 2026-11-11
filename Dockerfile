FROM php:8.4-fpm-alpine AS php-base
RUN apk add --no-cache icu-dev oniguruma-dev sqlite-dev libxml2-dev \
    && docker-php-ext-install -j$(nproc) intl mbstring pdo_sqlite dom xml xmlwriter \
    && printf 'expose_php=Off\n' > /usr/local/etc/php/conf.d/app.ini
RUN apk add --no-cache unzip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

FROM php-base AS dependencies
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN touch .env && composer dump-autoload --optimize

FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

FROM dependencies AS tests
COPY --from=frontend /app/public/build ./public/build
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
CMD ["php", "artisan", "test"]

FROM dependencies AS production
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

FROM php-base AS app
COPY --from=production /var/www/html /var/www/html
COPY --from=frontend /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm", "-F"]

FROM nginx:1.28-alpine AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
COPY --from=frontend /app/public/build /var/www/html/public/build
