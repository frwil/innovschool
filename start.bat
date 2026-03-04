@echo off
echo ========================================
echo  DEMARRAGE DOCKER SYMFONY
echo ========================================

echo [1/4] Verification de Docker Desktop...
docker version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERREUR: Docker Desktop n'est pas demarre!
    echo Veuillez demarrer Docker Desktop et reessayer.
    pause
    exit /b 1
)

echo [2/4] Creation du fichier .env...
if not exist .env (
    (
        echo USER_ID=1000
        echo GROUP_ID=1000
        echo APP_ENV=prod
        echo APP_DEBUG=0
        echo APP_SECRET=my_secret_key_change_this
        echo DATABASE_URL=mysql://root:root@database:3306/innovsh?serverVersion=8.0
    ) > .env
    echo Fichier .env cree!
) else (
    echo Fichier .env existe deja.
)

echo [3/4] Construction des containers...
docker-compose build --no-cache php

echo [4/4] Demarrage des services...
docker-compose up -d

echo ========================================
echo  SERVICES DEMARRES!
echo ========================================
echo PHP: http://localhost:8000
echo Adminer: http://localhost:8082
echo Mailpit: http://localhost:8025
echo ========================================

timeout /t 5 /nobreak

echo Execution des optimisations Symfony...
call optimize.bat

pause