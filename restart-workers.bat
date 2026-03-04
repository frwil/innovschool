@echo off
echo ========================================
echo  REDEMARRAGE DES WORKERS MESSENGER
echo ========================================
echo.

echo 🔄 Redemarrage des workers...
docker compose exec php supervisorctl restart messenger-worker-1
docker compose exec php supervisorctl restart messenger-worker-2
docker compose exec php supervisorctl restart messenger-worker-3

echo.
echo 📊 Etat des workers:
docker compose exec php supervisorctl status

echo.
echo 📨 File d'attente:
docker compose exec php php bin/console messenger:stats

echo.
timeout /t 10 /nobreak