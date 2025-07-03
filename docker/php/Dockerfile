FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
  libzip-dev \
  unzip

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli zip

# Install Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php -r "if (hash_file('sha384', 'composer-setup.php') === 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
RUN php composer-setup.php --install-dir=/usr/local/bin --filename=composer
RUN php -r "unlink('composer-setup.php');"

# Enable Apache rewrite module
RUN a2enmod rewrite

RUN chmod -R 755 /var/www/html
RUN chown -R www-data:www-data /var/www/html

# Copy custom virtual host configuration to override Apache's default
COPY ./docker/vhost.conf /etc/apache2/sites-available/000-default.conf

# Copy composer files and install dependencies
COPY ../../composer.json /var/www/html/composer.json
COPY ../../composer.lock /var/www/html/composer.lock
RUN composer install --no-dev --optimize-autoloader

# Copy the rest of the application code
COPY ../../src /var/www/html

COPY ../.env /var/www/html/.env

# Create a directory for our custom SSL certs within the image
RUN mkdir -p /etc/ssl/certs_custom

# Copy your cacert.pem file into the image
COPY docker/php/cacert.pem /etc/ssl/certs_custom/cacert.pem

# Create a custom PHP configuration file to point to the CA bundle.
# This file will be loaded by PHP along with the main php.ini.
# Using a file in conf.d is generally safer than modifying the main php.ini.
RUN echo "[curl]" > /usr/local/etc/php/conf.d/99-custom-cacert.ini \
  && echo "curl.cainfo=/etc/ssl/certs_custom/cacert.pem" >> /usr/local/etc/php/conf.d/99-custom-cacert.ini \
  && echo "\n[openssl]" >> /usr/local/etc/php/conf.d/99-custom-cacert.ini \
  && echo "openssl.cafile=/etc/ssl/certs_custom/cacert.pem" >> /usr/local/etc/php/conf.d/99-custom-cacert.ini

# Set document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN sed -ri 's/DirectoryIndex\s+(.*)/DirectoryIndex index.php index.html/' /etc/apache2/mods-enabled/dir.conf

WORKDIR /var/www/html
