FROM php:8.2-fpm-alpine

# Installation des dépendances système
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    mysql-client \
    netcat-openbsd

# Installer les extensions PHP
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    intl \
    zip

# Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
# Installation de Redis
RUN apk add --no-cache pcre-dev ${PHPIZE_DEPS} \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del pcre-dev ${PHPIZE_DEPS}

# Configuration PHP mémoire
COPY docker/php/conf.d/memory.ini /usr/local/etc/php/conf.d/memory.ini

# Création de l'utilisateur non-root
RUN addgroup -g ${GROUP_ID} www || true && \
    adduser -u ${USER_ID} -G www -s /bin/sh -D www || true

# Définir le répertoire de travail
WORKDIR /var/www

# Créer les dossiers nécessaires
RUN mkdir -p var/cache var/log var/mysql

# Copier les fichiers de l'application avec les bonnes permissions
COPY --chown=www:www . /var/www/

USER www

EXPOSE 9000

CMD ["php-fpm", "-F"]