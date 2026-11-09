FROM php:8.4-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl git unzip zip tar mariadb-client util-linux \
        libcurl4-openssl-dev libicu-dev libonig-dev libzip-dev libxml2-dev \
    && docker-php-ext-install -j"$(nproc)" curl intl mbstring pdo_mysql zip dom simplexml xml xmlwriter sockets pcntl \
    && a2enmod headers setenvif access_compat \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY apache.conf /etc/apache2/sites-available/000-default.conf
COPY php.ini /usr/local/etc/php/conf.d/website-backup-dev.ini
COPY entrypoint.sh /usr/local/bin/website-backup-dev-entrypoint
RUN chmod +x /usr/local/bin/website-backup-dev-entrypoint
WORKDIR /workspace/src
ENTRYPOINT ["website-backup-dev-entrypoint"]
CMD ["apache2-foreground"]
