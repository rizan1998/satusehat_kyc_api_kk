FROM php:8.1.0-apache

WORKDIR /var/www

# Install dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev zip \
    zlib1g-dev \
    libjpeg-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libpng-dev

# Configure PHP extensions
RUN docker-php-ext-install mysqli && a2enmod rewrite && service apache2 restart
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable pdo_mysql
RUN docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg
RUN docker-php-ext-install -j$(nproc) gd
RUN docker-php-ext-install zip

# Install Composer
RUN curl -sS http://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Node.js
RUN apt update && apt install -y nodejs npm

# Copy configuration files
COPY ./000-default.conf /etc/apache2/sites-available/
COPY php.ini /usr/local/etc/php/conf.d/

# Copy application files
COPY ./medisy-satusehat-master/. .

# Set proper ownership and permissions before composer install
RUN chown -R www-data:www-data /var/www \
    && find /var/www -type d -exec chmod 755 {} \; \
    && find /var/www -type f -exec chmod 644 {} \; \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

# Install Composer dependencies
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-dev --optimize-autoloader
RUN composer install --no-scripts --no-autoloader

# Final permission fix
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache