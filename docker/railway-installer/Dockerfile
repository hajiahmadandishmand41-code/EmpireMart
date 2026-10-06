FROM serversideup/php:8.3-cli

USER root

RUN install-php-extensions intl bcmath calendar gd

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY . .

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY docker/railway-installer/entrypoint.sh /usr/local/bin/empiremart-install
RUN chmod +x /usr/local/bin/empiremart-install

ENTRYPOINT ["/usr/local/bin/empiremart-install"]
