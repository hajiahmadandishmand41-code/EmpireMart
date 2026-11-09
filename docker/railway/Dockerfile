# EmpireMart production runtime for Railway.
# Build context is the repository root; this deliberately copies this checkout,
# not the upstream Bagisto repository.
FROM ubuntu:24.04

ARG PHP_VERSION=8.3
ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=Asia/Kabul
ENV APP_DIR=/var/www/bagisto

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl gnupg software-properties-common unzip \
    && add-apt-repository ppa:ondrej/php -y \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx supervisor gettext-base \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-calendar \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-exif \
        php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-gmp \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-opcache \
        php${PHP_VERSION}-soap \
        php${PHP_VERSION}-sockets \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR ${APP_DIR}
COPY . ${APP_DIR}

# Install the exact dependencies in composer.lock. Composer scripts are
# disabled here so no database action can occur during an image build.
RUN mkdir -p storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs \
        bootstrap/cache /run/php \
    && composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --no-scripts \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

# Keep PHP-FPM environment variables visible to Laravel and apply production
# upload/time limits suitable for product images and documents.
RUN printf '%s\n' \
        'clear_env = no' \
        'catch_workers_output = yes' \
        'pm = dynamic' \
        'pm.max_children = 20' \
        'pm.start_servers = 4' \
        'pm.min_spare_servers = 2' \
        'pm.max_spare_servers = 8' \
        >> /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf \
    && printf '%s\n' \
        'memory_limit = 512M' \
        'upload_max_filesize = 64M' \
        'post_max_size = 70M' \
        'max_execution_time = 300' \
        'max_input_time = 300' \
        'max_input_vars = 5000' \
        'expose_php = Off' \
        > /etc/php/${PHP_VERSION}/fpm/conf.d/99-empiremart.ini \
    && cp /etc/php/${PHP_VERSION}/fpm/conf.d/99-empiremart.ini /etc/php/${PHP_VERSION}/cli/conf.d/99-empiremart.ini

COPY docker/railway/nginx.conf /etc/nginx/sites-available/default
COPY docker/railway/supervisord.conf /etc/supervisor/conf.d/empiremart.conf
COPY docker/railway/entrypoint.sh /usr/local/bin/empiremart-entrypoint
RUN chmod +x /usr/local/bin/empiremart-entrypoint \
    && rm -f /etc/nginx/sites-enabled/default \
    && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/empiremart-entrypoint"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
