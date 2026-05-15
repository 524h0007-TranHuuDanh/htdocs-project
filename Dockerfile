FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql mysqli

RUN a2enmod rewrite

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 755 /var/www/html/uploads

RUN cd /var/www/html && composer install --no-interaction --no-dev --optimize-autoloader

EXPOSE 80

CMD ["apache2-foreground"]