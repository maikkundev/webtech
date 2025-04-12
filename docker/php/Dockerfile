FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
  libzip-dev \
  unzip

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli zip

# Enable Apache rewrite module
RUN a2enmod rewrite

RUN chmod -R 755 /var/www/html
RUN chown -R www-data:www-data /var/www/html

# Copy custom virtual host configuration to override Apache's default
COPY ./docker/vhost.conf /etc/apache2/sites-available/000-default.conf

# Set document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN sed -ri 's/DirectoryIndex\s+(.*)/DirectoryIndex index.php index.html/' /etc/apache2/mods-enabled/dir.conf

WORKDIR /var/www/html
