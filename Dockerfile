FROM php:8.4-apache

WORKDIR /var/www

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip \
    libpng-dev libjpeg-dev libfreetype6-dev \
    nodejs npm \
    libonig-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo_mysql mysqli mbstring gd zip \
    && a2enmod rewrite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy configuration files
COPY ./000-default.conf /etc/apache2/sites-available/
COPY php.ini /usr/local/etc/php/conf.d/

# Copy application files
COPY ./medisy-satusehat-master/. .

# Permissions and composer install
RUN chown -R www-data:www-data /var/www \
    && find /var/www -type d -exec chmod 755 {} \; \
    && find /var/www -type f -exec chmod 644 {} \; \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache \
    && composer install --no-dev --optimize-autoloader --ignore-platform-req=php \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

EXPOSE 80

CMD ["apache2-foreground"]
