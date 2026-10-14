FROM php:8.2-apache

RUN docker-php-ext-install mysqli

COPY frontend/ /var/www/html/
COPY backend/ /var/www/html/api/

RUN echo "DirectoryIndex index.html index.php" \
    > /etc/apache2/conf-available/directory-index.conf \
    && a2enconf directory-index

EXPOSE 80
