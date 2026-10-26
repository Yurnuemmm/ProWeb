FROM php:8.3-apache

# Instalar dependencias del sistema y extensiones de PHP requeridas (incluyendo soporte MariaDB/MySQL)
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Habilitar el módulo rewrite de Apache
RUN a2enmod rewrite

# Directorio de trabajo en el contenedor
WORKDIR /var/www/html

# Copiar el código del proyecto al contenedor
COPY . /var/www/html/

# Ajustar permisos para que Apache (www-data) pueda leer y escribir datos (ej. api/data)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
