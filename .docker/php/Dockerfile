FROM php:8.4-fpm
RUN apt-get update
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN apt-get install -y git libzip-dev zip unzip npm
RUN docker-php-ext-install pdo pdo_mysql zip
RUN apt update && apt install -y libicu-dev && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install intl
