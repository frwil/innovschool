#!/bin/bash

echo "🚀 Optimisation Symfony pour Alpine Linux..."

# Installer les dépendances en mode production
docker-compose exec php composer install --no-dev --optimize-autoloader --no-scripts

# Vider le cache
docker-compose exec php php bin/console cache:clear --env=prod --no-debug

# Réchauffer le cache
docker-compose exec php php bin/console cache:warmup --env=prod

# Optimiser l'autoloader
docker-compose exec php composer dump-autoload --optimize --no-dev

# Vider les caches Doctrine
docker-compose exec php php bin/console doctrine:cache:clear-metadata --env=prod
docker-compose exec php php bin/console doctrine:cache:clear-query --env=prod
docker-compose exec php php bin/console doctrine:cache:clear-result --env=prod

# Dump des assets (si vous en avez)
docker-compose exec php php bin/console asset-map:compile

echo "✅ Optimisation terminée!"
echo "📊 Vérification OPcache:"
docker-compose exec php php -r "print_r(opcache_get_status()['memory_usage']);"