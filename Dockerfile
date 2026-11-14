# ===========================================================================
# Dockerfile — Cauce (PHP 8.3 + Apache + MySQL)
#
# Etapa 1: instala dependencias de Composer sin paquetes de desarrollo.
# Etapa 2: runtime Apache/PHP con pdo_mysql, mbstring, gd, zip y cliente MySQL
#          para inicializar la base de datos desde Kubernetes.
#
# La configuración real llega por variables de entorno desde Kubernetes.
# No se copia ningún archivo .env ni datos locales de storage/.
# ===========================================================================

FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
COPY app ./app

RUN composer install \
      --no-dev \
      --no-interaction \
      --no-scripts \
      --prefer-dist \
      --optimize-autoloader \
      --ignore-platform-req=ext-pdo_mysql

FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      default-mysql-client \
      libzip-dev \
      libpng-dev \
      libjpeg62-turbo-dev \
      libfreetype6-dev \
      libwebp-dev \
      libonig-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring gd zip opcache \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

# DocumentRoot en /public y fallback a index.php para rutas limpias.
RUN a2enmod rewrite headers \
 && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
 && printf '%s\n' \
      '<Directory /var/www/html/public>' \
      '    AllowOverride All' \
      '    Require all granted' \
      '    FallbackResource /index.php' \
      '</Directory>' \
      > /etc/apache2/conf-available/cause.conf \
 && a2enconf cause

RUN { \
      echo 'date.timezone = America/Mexico_City'; \
      echo 'upload_max_filesize = 12M'; \
      echo 'post_max_size = 16M'; \
      echo 'memory_limit = 256M'; \
      echo 'max_execution_time = 120'; \
      echo 'session.save_path = /var/www/html/storage/sessions'; \
      echo 'session.gc_maxlifetime = 604800'; \
      echo 'opcache.enable = 1'; \
      echo 'opcache.validate_timestamps = 0'; \
    } > /usr/local/etc/php/conf.d/cause.ini

WORKDIR /var/www/html

COPY --chown=www-data:www-data . /var/www/html
COPY --from=vendor --chown=www-data:www-data /app/vendor /var/www/html/vendor
COPY docker/init-db.sh /usr/local/bin/init-db.sh

RUN chmod +x /usr/local/bin/init-db.sh \
 && mkdir -p \
      storage/logs \
      storage/cache \
      storage/uploads/gastos \
      storage/estados_cuenta \
      storage/sessions \
 && chown -R www-data:www-data storage \
 && chmod -R ug+rwX storage

EXPOSE 80
