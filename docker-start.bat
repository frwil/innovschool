@echo off
setlocal enabledelayedexpansion

echo ========================================
echo   Advanced Docker Launcher
echo ========================================
echo.

:: Vérifier l'existence de Docker Desktop
set "dockerPath=C:\Program Files\Docker\Docker\Docker Desktop.exe"
if not exist "%dockerPath%" (
    echo ERROR: Docker Desktop not found at:
    echo %dockerPath%
    echo.
    echo Please check your Docker installation.
    pause
    exit /b 1
)

:: Vérifier si Docker est déjà en cours d'exécution
echo Checking Docker status...
docker version >nul 2>&1
if !errorlevel! equ 0 (
    echo Docker is already running!
    goto :success
)

echo Starting Docker Desktop...
start "" "%dockerPath%"

:: Attendre le démarrage avec timeout
set "timeout_count=0"
set "max_wait=60" :: 60 secondes maximum

:wait_loop
docker version >nul 2>&1
if !errorlevel! equ 0 (
    goto :success
)

set /a "timeout_count+=1"
if !timeout_count! gtr !max_wait! (
    echo ERROR: Docker failed to start within !max_wait! seconds
    echo Please check Docker Desktop manually
    pause
    exit /b 1
)

echo Waiting for Docker... (!timeout_count!/!max_wait!s)
timeout /t 1 /nobreak >nul
goto :wait_loop

:success
echo.
echo ================================
echo DOCKER STARTED SUCCESSFULLY!
echo ================================
echo.
docker --version
echo.
echo You can now use Docker commands

start sync-changes.bat

timeout /t 10 /nobreak

start http://localhost:8000

timeout /t 10 /nobreak
exit /b 0