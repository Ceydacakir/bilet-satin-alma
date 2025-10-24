# Resmi PHP 8.1 Apache imajını temel al
FROM php:8.1-apache

# Gerekli sistem bağımlılıklarını ve PHP eklentilerini kur
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    libsqlite3-dev \
    libzip-dev \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    # Bağımlılıklar kurulduktan sonra önbelleği temizle
    && rm -rf /var/lib/apt/lists/*

# PHP eklentilerini yapılandır ve kur
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_sqlite zip gd

# Composer'ı kur (Çok aşamalı yapıyla temiz bir kurulum)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Çalışma dizinini ayarla
# Bu, Compose ile bind mount edilmeden önceki varsayılan dizinidir.
WORKDIR /var/www/html


# DocumentRoot'u /var/www/html/public olarak ayarlar
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    # Ayrıca, Apache'nin dizin izinlerini de /var/www/html/public olarak güncelle
    && sed -ri -e 's!<Directory /var/www/>!<Directory /var/www/html/public>!g' /etc/apache2/apache2.conf

# Mod_rewrite'ı (URL yönlendirmeleri için sıkça kullanılır) etkinleştir
RUN a2enmod rewrite