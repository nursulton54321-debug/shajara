FROM php:8.2-apache

# Kerakli system paketlar
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Apache DocumentRoot ni /var/www/html ga mos qoldiramiz
WORKDIR /var/www/html

# Loyiha fayllarini container ichiga nusxalash
COPY . /var/www/html/

# Upload papkasi mavjud bo'lishi va yozish huquqi
RUN mkdir -p /var/www/html/assets/uploads \
    && chown -R www-data:www-data /var/www/html/assets/uploads \
    && chmod -R 775 /var/www/html/assets/uploads

# Apache port
EXPOSE 80