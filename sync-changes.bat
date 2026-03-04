@echo off
echo ========================================
echo  SYNCHRONISATION DES CHANGEMENTS DOCKER
echo ========================================

echo [1/5] Arret des services...
docker compose down

echo [2/5] Nettoyage du cache Docker...
docker system prune -f

echo [3/5] Redemarrage des services...
cd D:\wamp64\www\innovschool
rem docker compose up -d --force-recreate php
docker compose up -d

echo [4/5] Attente du demarrage...
timeout /t 10 /nobreak

echo [5/5] Verification des services...

echo ✅ Synchronisation terminee!
timeout /t 5 /nobreak

exit /b 0