@echo off
echo ========================================
echo  CORRECTION PERMISSIONS PHP-FPM
echo ========================================

echo [1/5] Creation de la configuration PHP-FPM sans slowlog...
if not exist docker\php mkdir docker\php

(
echo [global]
echo daemonize = no
echo error_log = /proc/self/fd/2
echo.
echo [www]
echo user = www
echo group = www
echo listen = 9000
echo listen.owner = www
echo listen.group = www
echo.
echo ; Pool management
echo pm = dynamic
echo pm.max_children = 10
echo pm.start_servers = 2
echo pm.min_spare_servers = 1
echo pm.max_spare_servers = 3
echo pm.max_requests = 500
echo.
echo ; Logging
echo catch_workers_output = yes
echo.
echo ; Security
echo php_admin_value[upload_tmp_dir] = /tmp
echo php_admin_value[session.save_path] = /tmp
) > docker\php\php-fpm.conf

echo [2/5] Arret des services...
docker compose down

echo [3/5] Reconstruction de l'image PHP...
docker compose build --no-cache php

echo [4/5] Redemarrage des services...
docker compose up -d

echo [5/5] Verification...
timeout /t 5 /nobreak
docker compose logs php --tail=10

echo ✅ PHP-FPM corrige avec succes!

pause