# ============================================================
# Script de rebuild Docker pour InnovSchool
# ============================================================
param(
    [string]$Mode = "rebuild",  # rebuild | restart
    [string]$DbName = "innovsh"
)

$ErrorActionPreference = "Continue"
$PHP_CONTAINER = "innovschool-php-1"
$WORKER_CONTAINER = "innovschool-messenger-worker-1"

Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  InnovSchool - Script de rebuild Docker" -ForegroundColor Cyan
Write-Host "  Mode: $Mode" -ForegroundColor Cyan
Write-Host "  Base de donnees: $DbName" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

# ---- 1. Vérifier Docker ----
Write-Host "[1/10] Verification de Docker..." -ForegroundColor Yellow
try {
    docker ps *>$null
    if ($LASTEXITCODE -ne 0) { throw "Docker n'est pas accessible" }
    Write-Host "  ✅ Docker OK" -ForegroundColor Green
} catch {
    Write-Host "❌ ERREUR: Docker n'est pas disponible" -ForegroundColor Red
    exit 1
}

# ---- 2. Nettoyage complet ----
Write-Host "[2/10] Nettoyage complet..." -ForegroundColor Yellow
Write-Host "  Arret des conteneurs..." -ForegroundColor Gray
docker compose down -v 2>$null | Out-Null
Write-Host "  ✅ Nettoyage termine" -ForegroundColor Green

# ---- 3. Rebuild des images ----
if ($Mode -eq "rebuild") {
    Write-Host "[3/10] Rebuild des images..." -ForegroundColor Yellow
    docker compose build
    if ($LASTEXITCODE -ne 0) { 
        Write-Host "❌ ERREUR: Le build a echoue" -ForegroundColor Red
        exit 1 
    }
    Write-Host "  ✅ Build termine" -ForegroundColor Green
} else {
    Write-Host "[3/10] Rebuild saute (mode=$Mode)" -ForegroundColor Yellow
}

# ---- 4. Demarrer MySQL et Redis seulement (pas PHP) ----
Write-Host "[4/10] Demarrage de MySQL et Redis..." -ForegroundColor Yellow
docker compose up -d database redis
Start-Sleep -Seconds 5

# Attendre MySQL
Write-Host "  Attente de MySQL..." -ForegroundColor Gray
$retries = 0
do {
    Start-Sleep -Seconds 3
    docker exec mysql_container mysqladmin ping -h localhost -u root -proot --silent 2>$null
    if ($LASTEXITCODE -eq 0) { break }
    Write-Host "    MySQL: $retries/30" -ForegroundColor Gray
    $retries++
} while ($retries -lt 30)
Write-Host "  ✅ MySQL pret" -ForegroundColor Green

# Attendre Redis
Write-Host "  Attente de Redis..." -ForegroundColor Gray
$retries = 0
do {
    Start-Sleep -Seconds 2
    docker exec redis_container redis-cli ping 2>$null | Out-Null
    if ($LASTEXITCODE -eq 0) { break }
    Write-Host "    Redis: $retries/15" -ForegroundColor Gray
    $retries++
} while ($retries -lt 15)
Write-Host "  ✅ Redis pret" -ForegroundColor Green

# ---- 5. Creer la base et la table messenger_messages ----
Write-Host "[5/10] Creation de la base et des tables..." -ForegroundColor Yellow

Write-Host "  Creation de la base '$DbName'..." -ForegroundColor Gray
docker exec mysql_container mysql -u root -proot -e "CREATE DATABASE IF NOT EXISTS $DbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1

Write-Host "  Creation de la table messenger_messages..." -ForegroundColor Gray
docker exec mysql_container mysql -u root -proot $DbName -e "
CREATE TABLE IF NOT EXISTS messenger_messages (
    id BIGINT AUTO_INCREMENT NOT NULL,
    body LONGTEXT NOT NULL,
    headers LONGTEXT NOT NULL,
    queue_name VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL,
    available_at DATETIME NOT NULL,
    delivered_at DATETIME DEFAULT NULL,
    INDEX IDX_75EA56E0FB7336F0 (queue_name),
    INDEX IDX_75EA56E0E3BD61CE (available_at),
    INDEX IDX_75EA56E016BA31DB (delivered_at),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
" 2>&1
Write-Host "  ✅ Table messenger_messages creee" -ForegroundColor Green

# ---- 6. Demarrer TOUS les autres services (PHP, Nginx, worker, etc.) ----
Write-Host "[6/10] Demarrage de tous les services..." -ForegroundColor Yellow
docker compose up -d
if ($LASTEXITCODE -ne 0) { 
    Write-Host "❌ ERREUR: Le demarrage a echoue" -ForegroundColor Red
    docker compose logs --tail 30
    exit 1
}
Write-Host "  ✅ Tous les services demarres" -ForegroundColor Green

# ---- 7. Attendre que PHP soit pret ----
Write-Host "[7/10] Attente de PHP..." -ForegroundColor Yellow
Start-Sleep -Seconds 15

$retries = 0
do {
    $health = docker inspect --format='{{.State.Health.Status}}' $PHP_CONTAINER 2>$null
    if ($health -eq "healthy") { break }
    Start-Sleep -Seconds 3
    Write-Host "    PHP status: $health (tentative $retries/10)" -ForegroundColor Gray
    $retries++
} while ($retries -lt 10)
Write-Host "  ✅ PHP pret" -ForegroundColor Green

# ---- 8. Verifier/installer Redis dans PHP ----
Write-Host "[8/10] Verification de Redis dans PHP..." -ForegroundColor Yellow
$redisCheck = docker exec $PHP_CONTAINER php -m 2>&1 | Select-String "redis"
if ($redisCheck) {
    Write-Host "  ✅ Extension Redis OK" -ForegroundColor Green
} else {
    Write-Host "  ⚠️ Installation de Redis..." -ForegroundColor Yellow
    docker exec $PHP_CONTAINER sh -c "
        apk add --no-cache --update --repository http://dl-cdn.alpinelinux.org/alpine/edge/community php83-redis 2>/dev/null
        echo 'extension=redis.so' > /usr/local/etc/php/conf.d/docker-php-ext-redis.ini
    " 2>&1
    docker restart $PHP_CONTAINER
    Start-Sleep -Seconds 10
}

# ---- 9. Mise a jour du schema Doctrine et cache ----
Write-Host "[9/10] Mise a jour du schema Doctrine..." -ForegroundColor Yellow

# Verifier les tables existantes
Write-Host "  Tables dans la base:" -ForegroundColor Gray
docker exec mysql_container mysql -u root -proot $DbName -e "SHOW TABLES;" 2>&1

# Mise a jour du schema
Write-Host "  doctrine:schema:update --force..." -ForegroundColor Gray
docker exec $PHP_CONTAINER php bin/console doctrine:schema:update --force --env=prod 2>&1

# Nettoyage du cache
Write-Host "  cache:clear..." -ForegroundColor Gray
docker exec $PHP_CONTAINER php bin/console cache:clear --env=prod 2>&1
Write-Host "  ✅ Configuration terminee" -ForegroundColor Green

# ---- 10. Redemarrer le worker pour etre sur ----
Write-Host "[10/10] Redemarrage du worker..." -ForegroundColor Yellow
docker restart $WORKER_CONTAINER 2>&1 | Out-Null
Start-Sleep -Seconds 5

# Verification finale
Write-Host ""
Write-Host "============================================================" -ForegroundColor Green
Write-Host "  ✅ REBUILD TERMINE AVEC SUCCES !" -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Green
Write-Host ""
Write-Host "Application    : http://localhost:8000" -ForegroundColor Cyan
Write-Host "Adminer (MySQL): http://localhost:8082" -ForegroundColor Cyan
Write-Host "Mailpit        : http://localhost:8025" -ForegroundColor Cyan
Write-Host "Redis Commander: http://localhost:8081" -ForegroundColor Cyan
Write-Host ""

Write-Host "Conteneurs actifs:" -ForegroundColor White
docker ps --format "table {{.Names}}\t{{.Status}}"

Write-Host ""
Write-Host "Logs du worker (20 dernieres lignes):" -ForegroundColor White
docker logs $WORKER_CONTAINER --tail 20 2>&1

Write-Host ""
Write-Host "Commandes utiles:" -ForegroundColor White
Write-Host "  docker logs -f $WORKER_CONTAINER" -ForegroundColor Gray
Write-Host "  docker logs -f $PHP_CONTAINER" -ForegroundColor Gray
Write-Host "  docker logs -f innovschool-nginx-1" -ForegroundColor Gray
Write-Host ""