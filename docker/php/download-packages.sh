#!/bin/sh
# ----------------------------------------------------------------------
# Script a executer sur une machine QUI A INTERNET pour telecharger
# les paquets Alpine necessaires a l'extension Redis (PHP 8.2).
#
# Usage :
#   sh download-packages.sh
#
# Les .apk sont sauvegardes dans docker/php/packages/.
# Committez-les ensuite dans le depot pour un deploiement offline.
# ----------------------------------------------------------------------
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PACKAGES_DIR="$SCRIPT_DIR/packages"

echo ">>> Nettoyage du dossier packages..."
rm -f "$PACKAGES_DIR"/*.apk

echo ">>> Telechargement des paquets Redis (Alpine) via l'image php:8.3-fpm-alpine..."
docker run --rm \
    -v "$PACKAGES_DIR:/out" \
    php:8.3-fpm-alpine \
    sh -c "apk update && apk fetch -R --output /out php83-pecl-redis"

echo ""
echo ">>> Paquets telecharges :"
ls -la "$PACKAGES_DIR"/
echo ""
echo ">>> Termine ! Committez ces fichiers .apk et reconstruisez l'image Docker."
echo ">>> Commande : git add docker/php/packages/*.apk && git commit -m 'Add Redis APK packages for offline install'"
