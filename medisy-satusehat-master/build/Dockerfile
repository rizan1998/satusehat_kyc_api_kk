FROM php:8.1.0-apache

WORKDIR /var/www


RUN apt-get update && apt-get install -y  \
    libzip-dev zip \
    zlib1g-dev \
    libjpeg-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libpng-dev

RUN docker-php-ext-install mysqli && a2enmod rewrite && service apache2 restart && chown -R www-data:www-data /var/www 

RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable pdo_mysql

RUN docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg

RUN docker-php-ext-install -j$(nproc) gd

RUN docker-php-ext-install zip

RUN curl -sS http://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY ./app/. .

COPY ./app/public/. ./html/.

RUN composer install 

RUN chmod -R 777 .

