# EmpireMart production image.
# Build context: repository root. This deliberately builds the checked-out
# EmpireMart source; it never clones the upstream Bagisto repository.

FROM node:22-bookworm-slim AS assets
WORKDIR /app

COPY package.json ./
RUN npm install --no-audit --no-fund --package-lock=false

COPY . .
RUN npm run build


FROM php:8.3-apache-bookworm

ENV DEBIAN_FRONTEND=noninteractive \
    APACHE_DOCUMENT_ROOT=/var/www/html/public \
    APP_ENV=production \
    APP_DEBUG=false \
    TZ=Asia/Kabul

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libgmp-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libwebp-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath calendar curl exif gd gmp intl mbstring pdo_mysql soap sockets zip \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY . .

# Install dependencies from this repository's lock file and discover Bagisto
# packages. No database migrations or seeders run during image construction.
RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --no-progress

COPY --from=assets /app/public/build /var/www/html/public/build
COPY docker/railway/entrypoint.sh /usr/local/bin/empiremart-entrypoint

RUN chmod +x /usr/local/bin/empiremart-entrypoint \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/public \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache \
    && printf '%s\\n' \
        'date.timezone=Asia/Kabul' \
        'memory_limit=512M' \
        'upload_max_filesize=64M' \
        'post_max_size=64M' \
        'max_execution_time=120' \
        > /usr/local/etc/php/conf.d/99-empiremart.ini \
    && printf '%s\\n' 'Listen 8080' > /etc/apache2/ports.conf \
    && printf '%s\\n' \
        '<VirtualHost *:8080>' \
        '    ServerName localhost' \
        '    DocumentRoot /var/www/html/public' \
        '    <Directory /var/www/html/public>' \
        '        Options FollowSymLinks' \
        '        AllowOverride All' \
        '        Require all granted' \
        '    </Directory>' \
        '    ErrorLog ${APACHE_LOG_DIR}/error.log' \
        '    CustomLog ${APACHE_LOG_DIR}/access.log combined' \
        '</VirtualHost>' \
        > /etc/apache2/sites-available/000-default.conf

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/empiremart-entrypoint"]
CMD ["apache2-foreground"]
