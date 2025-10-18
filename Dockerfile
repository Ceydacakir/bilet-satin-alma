FROM php:8.1-apache

# Sistem paketlerini güncelle ve gerekli paketleri yükle
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

# SQLite PDO extension'ını etkinleştir
RUN docker-php-ext-install pdo pdo_sqlite

# Apache mod_rewrite'ı etkinleştir
RUN a2enmod rewrite

# Çalışma dizinini ayarla
WORKDIR /var/www/html

# Proje dosyalarını kopyala
COPY . .

# Gerekli dizinleri oluştur ve izinleri ayarla
RUN mkdir -p database uploads uploads/logos && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Apache yapılandırması
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

# Veritabanı dosyası için izinleri ayarla
RUN chmod 666 database/bus_tickets.db 2>/dev/null || true

# Port 80'i expose et
EXPOSE 80

# Apache'yi başlat
CMD ["apache2-foreground"]

