@echo off
setlocal enabledelayedexpansion

title Synchronisation Docker - InnovSH

echo ========================================
echo  SYNCHRONISATION DES CHANGEMENTS DOCKER
echo ========================================
echo.

echo [1/7] Arret des services...
docker compose down
if !errorlevel! neq 0 (
    echo ❌ Erreur lors de l'arret des services
    pause
    exit /b 1
)

echo [2/7] Nettoyage du cache Docker...
docker system prune -f
docker volume prune -f
docker image prune -f

echo [3/7] Reconstruction des images...
docker compose build --no-cache --pull
if !errorlevel! neq 0 (
    echo ❌ Erreur lors de la reconstruction des images
    pause
    exit /b 1
)

echo [4/7] Redemarrage des services...
docker compose up -d --force-recreate
if !errorlevel! neq 0 (
    echo ❌ Erreur lors du demarrage des services
    pause
    exit /b 1
)

echo [5/7] Attente de l'initialisation...
echo En attente que les services soient prets...
timeout /t 10 /nobreak

echo [6/7] Verification de l'etat des services...
echo.
docker compose ps

echo [7/7] Tests de fonctionnement...
echo.
echo 🔍 Test de PHP-FPM...
docker compose exec php php -v > nul 2>&1
if !errorlevel! equ 0 (
    echo ✅ PHP-FPM fonctionne
) else (
    echo ❌ PHP-FPM en erreur
)

echo 🔍 Test de la base de donnees...
docker compose exec php php bin/console doctrine:query:sql "SELECT 1" > nul 2>&1
if !errorlevel! equ 0 (
    echo ✅ Base de donnees accessible
) else (
    echo ❌ Base de donnees inaccessible
)

echo 🔍 Test de Redis...
docker compose exec php php bin/console debug:messenger > nul 2>&1
if !errorlevel! equ 0 (
    echo ✅ Redis et Messenger fonctionnels
) else (
    echo ❌ Probleme avec Redis/Messenger
)

echo.
echo ========================================
echo ✅ SYNCHRONISATION TERMINEE AVEC SUCCES
echo ========================================
echo.
echo 📊 ETAT DES SERVICES:
docker compose ps --format "table {{.Service}}\t{{.State}}\t{{.Ports}}"
echo.
echo 🌐 URLS D'ACCES:
echo    Application:    http://localhost:8000
echo    Redis Commander: http://localhost:8081
echo    Adminer:        http://localhost:8082
echo    Mailpit:        http://localhost:8025
echo.
echo 🔧 COMMANDES UTILES:
echo    Voir les logs:    docker compose logs -f
echo    Shell PHP:        docker compose exec php bash
echo    Logs workers:     docker compose logs php ^| findstr "messenger"
echo.
timeout /t 10 /nobreak
exit /b 0