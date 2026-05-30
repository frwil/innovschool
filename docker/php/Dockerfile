FROM php:8.3-fpm-alpine

# Installation des dépendances système
RUN apk add --no-cache git curl zip unzip mysql-client netcat-openbsd \
    libpng-dev oniguruma-dev libxml2-dev libzip-dev icu-dev \
    autoconf build-base

# Installation de Redis avec PECL (méthode officielle)
RUN pecl install redis && \
    docker-php-ext-enable redis

# Extensions PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl zip

# Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configuration PHP mémoire
RUN echo "memory_limit = 4096M" > /usr/local/etc/php/conf.d/memory.ini

# Script de démarrage simplifié
COPY docker/php/startup.sh /usr/local/bin/startup.sh
RUN chmod +x /usr/local/bin/startup.sh

# Création de l'utilisateur non-root
RUN addgroup -g ${GROUP_ID:-1000} www 2>/dev/null || true && \
    adduser -u ${USER_ID:-1000} -G www -s /bin/sh -D www 2>/dev/null || true

WORKDIR /var/www

# Copier les fichiers de l'application
COPY --chown=www:www . /var/www/

RUN mkdir -p var/cache var/log var/mysql && \
    chown -R www:www var

# Optimisation Composer — classmap optimisé évite les stat() sur le filesystem
RUN composer dump-autoload --optimize --classmap-authoritative && \
    composer clear-cache

USER www

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/startup.sh"]
CMD ["php-fpm", "-F"]