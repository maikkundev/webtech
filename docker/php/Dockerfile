FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
  libzip-dev \
  unzip

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli zip

# Enable Apache rewrite module
RUN a2enmod rewrite

# Set document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
