FROM php:8.4-apache-bookworm

# Advanced / minimal Apache image — NOT the default Compose path.
# Default `docker compose up` builds the root Dockerfile (nginx, php-fpm,
# Horizon, Reverb, scheduler) so self-host matches production runtime semantics.
# Use this file only when you intentionally want a reduced stack.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libicu-dev \
    && docker-php-ext-install pdo_mysql zip bcmath intl pcntl \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/app/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf '%s\n' \
        '<Directory /app/public>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/ark-public.conf \
    && a2enconf ark-public

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-interaction \
    && mkdir -p \
        storage/app/public \
        storage/app/private \
        storage/app/install \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/selfhost/entrypoint.sh /usr/local/bin/ark-selfhost-entrypoint
RUN chmod +x /usr/local/bin/ark-selfhost-entrypoint

EXPOSE 80
ENTRYPOINT ["ark-selfhost-entrypoint"]
