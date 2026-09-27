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

WORKDIR /var/www

# Dépendances Composer d'abord : cette couche reste en cache tant que
# composer.lock ne change pas (vendor/ est exclu du contexte via .dockerignore).
# Cache monté + plusieurs tentatives : le réseau vers codeload.github.com est
# instable depuis la VM Docker, chaque essai reprend les paquets déjà en cache.
COPY composer.json composer.lock ./

# Téléchargement du plus gros paquet (mpdf ~47 Mo) avant composer install :
# codeload ne supporte pas la reprise de transfert (-C), on repart donc de
# zéro à chaque essai — sur une connexion saine un seul essai suffit (24 s
# mesurées). Le rm -f purge un éventuel fichier partiel laissé par un essai
# interrompu, que composer prendrait sinon pour un cache valide.
RUN --mount=type=cache,target=/tmp/composer-cache \
    mkdir -p /tmp/composer-cache/cache/files/mpdf/mpdf && \
    cd /tmp/composer-cache/cache/files/mpdf/mpdf && \
    rm -f e175b05e3e00977b85feb96a8cccb174ac63621f.zip && \
    for i in 1 2 3 4 5; do \
        curl -fsSL --connect-timeout 30 --max-time 600 \
            -o e175b05e3e00977b85feb96a8cccb174ac63621f.zip \
            https://codeload.github.com/mpdf/mpdf/legacy.zip/e175b05e3e00977b85feb96a8cccb174ac63621f \
            && echo ">>> mpdf telecharge (essai $i)" && exit 0; \
        echo ">>> Echec mpdf (essai $i), nouvel essai..."; sleep 3; \
    done; \
    echo ">>> mpdf inaccessible apres 5 tentatives"; exit 1
RUN --mount=type=cache,target=/tmp/composer-cache \
    for i in 1 2 3 4 5; do \
        COMPOSER_CACHE_DIR=/tmp/composer-cache composer install --no-dev --no-scripts \
            --no-progress --prefer-dist --no-interaction --optimize-autoloader && exit 0; \
        echo ">>> Echec du telechargement (tentative $i), nouvel essai..."; sleep 3; \
    done; \
    echo ">>> Composer a echoue apres 5 tentatives"; exit 1

# Copier les fichiers de l'application (contexte allégé : ~8 Mo au lieu de ~500 Mo)
COPY . .

# Configuration PHP mémoire
RUN echo "memory_limit = 4096M" > /usr/local/etc/php/conf.d/memory.ini

# Script de démarrage simplifié
COPY docker/php/startup.sh /usr/local/bin/startup.sh
RUN chmod +x /usr/local/bin/startup.sh

# Création de l'utilisateur non-root
RUN addgroup -g ${GROUP_ID:-1000} www 2>/dev/null || true && \
    adduser -u ${USER_ID:-1000} -G www -s /bin/sh -D www 2>/dev/null || true

RUN mkdir -p var/cache var/log var/mysql && \
    chown -R www:www var

USER www

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/startup.sh"]
CMD ["php-fpm", "-F"]
