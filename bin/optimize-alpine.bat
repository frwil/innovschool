@echo off
echo ========================================
echo  OPTIMISATION SYMFONY
echo ========================================

echo Attente du demarrage des services...
timeout /t 10 /nobreak

echo [1/6] Installation des dependances Composer...
docker-compose exec php composer install --no-dev --optimize-autoloader --no-scripts

echo [2/6] Nettoyage du cache...
docker-compose exec php php bin/console cache:clear --env=prod --no-debug

echo [3/6] Prechauffage du cache...
docker-compose exec php php bin/console cache:warmup --env=prod

echo [4/6] Optimisation de l'autoloader...
docker-compose exec php composer dump-autoload --optimize --no-dev

echo [5/6] Mise a jour de la base de donnees...
docker-compose exec php php bin/console doctrine:schema:update --force --env=prod

echo [6/6] Verifications...
docker-compose exec php php -r "echo 'OPcache: ' . (opcache_get_status()['opcache_enabled'] ? 'OK' : 'NOK') . PHP_EOL;"

echo ========================================
echo  OPTIMISATION TERMINEE!
echo ========================================