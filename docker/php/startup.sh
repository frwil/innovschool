#!/bin/sh
set -e

echo ">>> Verification de l'extension Redis..."
if php -m | grep -q redis; then
    echo ">>> Extension Redis OK"
else
    echo ">>> ERREUR: Extension Redis non trouvee"
    php -m
    exit 1
fi

# S'assurer que var/ a les bonnes permissions
chown -R www:www /var/www/var 2>/dev/null || true

# Executer la commande passee en argument
exec "$@"