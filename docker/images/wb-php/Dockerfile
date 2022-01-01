FROM php:8.1-cli

RUN apt-get update \
	&& apt-get install -y libzip-dev zip

RUN docker-php-ext-install zip
RUN docker-php-ext-install pdo pdo_mysql

RUN apt-get install -y mariadb-client
