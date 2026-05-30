FROM php:8.3-fpm-alpine

RUN apk add --no-cache bash git curl libpng-dev libzip-dev oniguruma-dev icu-dev libxml2-dev autoconf gcc g++ make \
 && docker-php-ext-install pdo pdo_mysql bcmath zip intl opcache pcntl \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del autoconf gcc g++ make

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .
RUN composer install --no-scripts --no-interaction --prefer-dist || true

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
