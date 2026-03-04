@echo off
php bin/console asset-map:compile
symfony server:start -d
symfony open:local