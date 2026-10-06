FROM php:8.3-cli-bookworm

ENV DEBIAN_FRONTEND=noninteractive
WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip curl ca-certificates \
        libicu-dev libxml2-dev libzip-dev libonig-dev \
        libcurl4-openssl-dev libssl-dev libgmp-dev \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev libxpm-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-xpm \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath calendar curl exif gd intl mbstring pdo_mysql soap sockets zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY . .

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

RUN mkdir -p \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/cache \
        storage/framework/testing \
        storage/logs \
        bootstrap/cache \
    && chmod -R a+rwX storage bootstrap/cache

COPY docker/railway-installer/entrypoint.sh /usr/local/bin/empiremart-install
RUN chmod +x /usr/local/bin/empiremart-install

ENTRYPOINT ["/usr/local/bin/empiremart-install"]
