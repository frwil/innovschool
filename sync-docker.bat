@echo off
setlocal enabledelayedexpansion

echo ========================================
echo  SYNCHRONISATION DES CHANGEMENTS DOCKER
echo ========================================

echo [1/6] Arret des services...
docker compose down

echo [2/6] Nettoyage du cache Docker...
docker system prune -f
docker volume prune -f

echo [3/6] Reconstruction des images...
docker compose build --no-cache --pull

echo [4/6] Redemarrage des services...
docker compose up -d --force-recreate

echo [5/6] Attente du demarrage des services...
echo En attente que les services soient prets...
timeout /t 5 /nobreak

echo [6/6] Verification de l'etat des services...
docker compose ps

echo ✅ Synchronisation terminee!
echo.
echo 📊 Etat des services:
docker compose ps

echo.
echo 🔍 Logs des workers Messenger:
docker compose logs php | findstr "messenger" | tail -5

echo.
echo 🌐 Application accessible sur: http://localhost:8000
echo 📊 Redis Commander sur: http://localhost:8081
echo 🗄️  Adminer sur: http://localhost:8082

timeout /t 10 /nobreak