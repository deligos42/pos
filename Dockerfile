FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y libzip-dev \
    && docker-php-ext-install pdo_mysql \
    && a2dismod mpm_prefork mpm_worker || true \
    && a2enmod rewrite \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html
WORKDIR /var/www/html
EXPOSE 80
CMD ["apache2-foreground"]
