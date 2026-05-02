@echo off
echo ========================================
echo  REPARATION COMPLETE DU PROJET
echo ========================================

echo [1/6] Correction PHP-FPM...
call fix-php-fpm.bat

echo [2/6] Attente du demarrage...
timeout /t 10 /nobreak

echo [3/6] Installation des dependances...
docker compose exec php composer install --no-dev --optimize-autoloader --no-scripts

echo [4/6] Nettoyage du cache...
docker compose exec php php bin/console cache:clear --env=prod --no-debug

echo [5/6] Prechauffage du cache...
docker compose exec php php bin/console cache:warmup --env=prod

echo [6/6] Verification finale...
docker compose exec php php -r "echo 'PHP: ' . PHP_VERSION . PHP_EOL;"
docker compose exec php php -r "echo 'OPcache: ' . (opcache_get_status()['opcache_enabled'] ? 'OK' : 'NOK') . PHP_EOL;"

echo ✅ REPARATION TERMINEE!
echo Application disponible sur: http://localhost:8000

pause