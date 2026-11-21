# Marketing School platforması — PHP-FPM imici (app, worker, scheduler eyni imicdən)
FROM node:26-alpine AS assets
WORKDIR /build
COPY platform/package.json platform/package-lock.json ./
RUN npm ci --ignore-scripts && mkdir -p out && cp node_modules/hls.js/dist/hls.light.min.js out/

FROM php:8.4-fpm-alpine
RUN apk add --no-cache icu-libs libzip libpng freetype libjpeg-turbo postgresql-libs fcgi \
 && apk add --no-cache --virtual .build $PHPIZE_DEPS icu-dev libzip-dev libpng-dev freetype-dev libjpeg-turbo-dev postgresql-dev linux-headers \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip gd bcmath sockets pcntl opcache \
 && apk del .build
COPY deploy/php/php.ini /usr/local/etc/php/conf.d/zz-platform.ini
COPY deploy/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www
COPY platform/composer.json platform/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY platform/ ./
COPY --from=assets /build/out/hls.light.min.js public/platform/assets/vendor/hls.light.min.js
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
 && rm -f .env .env.testing && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R o-rwx storage
USER www-data
HEALTHCHECK CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1
