@echo off
echo ========================================
echo  VERIFICATION RAPIDE DES SERVICES
echo ========================================
echo.

echo 📊 Etat des containers:
docker compose ps

echo.
echo 🔍 Logs recents:
docker compose logs --tail=10

echo.
echo 🐘 Test base de donnees:
docker compose exec php php bin/console doctrine:query:sql "SELECT 1" && echo ✅ DB OK || echo ❌ DB Error

echo.
echo 📨 Test Redis/Messenger:
docker compose exec php php bin/console debug:messenger && echo ✅ Redis OK || echo ❌ Redis Error

echo.
echo 🔄 Workers actifs:
docker compose exec php supervisorctl status

echo.
timeout /t 10 /nobreak