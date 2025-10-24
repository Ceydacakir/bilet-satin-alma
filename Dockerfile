FROM php:8.1-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    libsqlite3-dev \
    libzip-dev \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_sqlite zip gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.* ./
RUN composer install --no-dev --optimize-autoloader
COPY . .

RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!<Directory /var/www/>!<Directory /var/www/html/public>!g' /etc/apache2/apache2.conf

RUN a2enmod rewrite

RUN mkdir -p /var/www/html/database \
    && touch /var/www/html/database/hop_bilet.db \
    && chmod 777 /var/www/html/database \
    && chmod 666 /var/www/html/database/hop_bilet.db