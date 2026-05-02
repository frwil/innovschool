#!/bin/bash
set -e

echo "=== DÉMARRAGE DES WORKERS ==="
echo "Vérification des dépendances..."

# Vérifier que PHP est disponible
if ! command -v php &> /dev/null; then
    echo "❌ PHP n'est pas installé ou accessible"
    exit 1
fi

echo "Configuration mémoire PHP: $(php -r "echo ini_get('memory_limit');")"

# Vérification des services
wait_for_service() {
    local host=$1
    local port=$2
    local service=$3
    
    echo "En attente de $service ($host:$port)..."
    for i in {1..30}; do
        if timeout 1 bash -c "cat < /dev/null > /dev/tcp/$host/$port" 2>/dev/null; then
            echo "✓ $service est prêt!"
            return 0
        fi
        echo "Tentative $i/30 - $service n'est pas encore prêt..."
        sleep 2
    done
    echo "❌ $service n'est pas accessible après 60 secondes"
    return 1
}

wait_for_service redis 6379 "Redis"
wait_for_service database 3306 "MySQL"

# Créer les dossiers nécessaires
echo "Création des dossiers..."
mkdir -p /var/www/var/cache /var/www/var/log /var/www/var/mysql

# Vérifier que Symfony est configuré
echo "Vérification de la configuration Symfony..."
if [ ! -f ".env" ]; then
    echo "❌ Fichier .env manquant"
    exit 1
fi

# Tester la connexion à la base de données
echo "Test de la connexion à la base de données..."
php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1 || {
    echo "❌ Impossible de se connecter à la base de données"
    exit 1
}
echo "✓ Connexion à la base de données OK"

# Vérifier que Messenger est configuré
echo "Vérification de la configuration Messenger..."
php bin/console debug:messenger > /dev/null 2>&1 || {
    echo "❌ Messenger n'est pas configuré correctement"
    exit 1
}
echo "✓ Configuration Messenger OK"

# Nettoyage du cache (optionnel, décommentez si nécessaire)
# echo "Nettoyage du cache Symfony..."
# php bin/console cache:clear --no-warmup || true
# php bin/console cache:warmup || true

echo ""
echo "=== DÉMARRAGE DU WORKER ==="
echo "Mémoire configurée: $(php -r "echo ini_get('memory_limit');")"

# Lancer le worker avec un time-limit plus court pour les tests
exec php bin/console messenger:consume async \
    --time-limit=3600 \
    --memory-limit=4096M \
    --limit=50 \
    --sleep=1000 \
    -vv