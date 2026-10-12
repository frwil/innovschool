# Sauvegarde horaire de la base de données (Windows 11 + Docker, prod ou dev).
# À planifier dans le Planificateur de tâches (voir README.md).
# Le dump passe par le conteneur et est copié avec docker cp (binaire sûr).
$ErrorActionPreference = "Stop"

$ScriptDir    = Split-Path -Parent $MyInvocation.MyCommand.Path
$EnvFile      = Join-Path (Split-Path -Parent $ScriptDir) ".env"
$BackupRoot   = if ($env:INNOVSKOOL_BACKUP_DIR) { $env:INNOVSKOOL_BACKUP_DIR } else { Join-Path $env:USERPROFILE "backups\innovskool" }
$DbContainer  = if ($env:INNOVSKOOL_DB_CONTAINER) { $env:INNOVSKOOL_DB_CONTAINER } else { "database" }
$RcloneTarget = $env:INNOVSKOOL_RCLONE_TARGET  # ex. "gdrive:innovskool-backups/dev" ; vide = pas de cloud
$KeepHourly   = 24
$KeepDaily    = 7
$KeepWeekly   = 4
$KeepMonthly  = 12
$LogFile      = Join-Path $BackupRoot "backup.log"

foreach ($dir in @("hourly", "daily", "weekly", "monthly")) {
    New-Item -ItemType Directory -Force -Path (Join-Path $BackupRoot $dir) | Out-Null
}

function Log([string]$msg) {
    Add-Content -Path $LogFile -Value "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $msg"
}

# Docker doit tourner et le conteneur de base exister.
try { & docker info 2>&1 | Out-Null } catch {}
if ($LASTEXITCODE -ne 0) { Log "Docker non démarré : sauvegarde ignorée."; exit 0 }
try { $running = (& docker ps --filter "name=^$DbContainer$" --format "{{.Names}}" 2>&1) -join " " } catch { $running = "" }
if (-not $running) { Log "Conteneur « $DbContainer » absent : sauvegarde ignorée."; exit 0 }

# Identifiants depuis le DATABASE_URL de l'application (valeurs URL-encodées décodées).
if (-not (Test-Path $EnvFile)) { Log "ERREUR : $EnvFile introuvable."; exit 1 }
$line = (Select-String -Path $EnvFile -Pattern '^DATABASE_URL=' | Select-Object -Last 1).Line
if ($line -notmatch '^DATABASE_URL="?mysql://([^:]+):([^@]+)@[^/]+/([^?"]+)') {
    Log "ERREUR : DATABASE_URL illisible."; exit 1
}
$DbUser = [System.Uri]::UnescapeDataString($Matches[1])
$DbPass = [System.Uri]::UnescapeDataString($Matches[2])
$DbName = [System.Uri]::UnescapeDataString($Matches[3])

# --set-gtid-purged n'existe pas sous MariaDB
try { $ver = (& docker exec $DbContainer sh -c "mysqld --version 2>/dev/null || mariadbd --version 2>/dev/null" 2>&1) -join " " } catch { $ver = "" }
$GtidFlag = if ($ver -match "MariaDB") { "" } else { "--set-gtid-purged=OFF" }

$stamp  = Get-Date -Format "yyyyMMdd-HHmmss"
$remote = "/tmp/innovskool-$stamp.sql.gz"
$final  = Join-Path (Join-Path $BackupRoot "hourly") "innovskool-$stamp.sql.gz"
$sqlCmd = "mysqldump --single-transaction --quick --routines --events --triggers $GtidFlag -u$DbUser $DbName | gzip -9 > $remote"

Log "Début de la sauvegarde de la base « $DbName »."
try { & docker exec -e "MYSQL_PWD=$DbPass" $DbContainer sh -c $sqlCmd 2>&1 | Out-Null } catch {}
if ($LASTEXITCODE -ne 0) {
    Log "ERREUR : mysqldump a échoué (code $LASTEXITCODE)."
    try { & docker exec $DbContainer rm -f $remote 2>&1 | Out-Null } catch {}
    exit 1
}

# Intégrité : test gzip dans le conteneur, puis copie binaire via docker cp.
try { & docker exec $DbContainer gzip -t $remote 2>&1 | Out-Null } catch {}
if ($LASTEXITCODE -ne 0) {
    Log "ERREUR : dump corrompu (échec de gzip -t)."
    try { & docker exec $DbContainer rm -f $remote 2>&1 | Out-Null } catch {}
    exit 1
}
& docker cp "$DbContainer`:$remote" $final
$size = (Get-Item $final).Length
if ($size -lt 10240) {
    Log "ERREUR : dump anormalement petit ($size o)."
    Remove-Item $final -Force
    exit 1
}
try { & docker exec $DbContainer rm -f $remote 2>&1 | Out-Null } catch {}
Log "Sauvegarde OK : $final ($([math]::Round($size / 1KB)) Ko)."

# Rotation : ne conserver que les N fichiers les plus récents de chaque niveau.
function Prune([string]$dir, [int]$keep) {
    Get-ChildItem $dir -Filter *.gz -File |
        Sort-Object LastWriteTime -Descending |
        Select-Object -Skip $keep |
        Remove-Item -Force
}
Prune (Join-Path $BackupRoot "hourly") $KeepHourly

# Quotidienne à 02h, hebdomadaire le dimanche à 03h, mensuelle le 1er à 05h.
$now = Get-Date
if ($now.Hour -eq 2) {
    Copy-Item $final (Join-Path $BackupRoot "daily")
    Prune (Join-Path $BackupRoot "daily") $KeepDaily
    Log "Copie quotidienne conservée."
}
if ($now.Hour -eq 3 -and $now.DayOfWeek -eq "Sunday") {
    Copy-Item $final (Join-Path $BackupRoot "weekly")
    Prune (Join-Path $BackupRoot "weekly") $KeepWeekly
    Log "Copie hebdomadaire conservée."
}
if ($now.Hour -eq 5 -and $now.Day -eq 1) {
    Copy-Item $final (Join-Path $BackupRoot "monthly")
    Prune (Join-Path $BackupRoot "monthly") $KeepMonthly
    Log "Copie mensuelle conservée."
}

# Synchronisation cloud (rclone), si configurée.
if ($RcloneTarget) {
    if (Get-Command rclone -ErrorAction SilentlyContinue) {
        foreach ($level in @("hourly", "daily", "weekly", "monthly")) {
            & rclone sync (Join-Path $BackupRoot $level) "$RcloneTarget/$level" `
                --log-file (Join-Path $BackupRoot "rclone.log") --log-level ERROR
            if ($LASTEXITCODE -ne 0) { Log "AVERTISSEMENT : synchronisation cloud échouée ($level)." }
        }
        Log "Synchronisation cloud terminée vers $RcloneTarget."
    } else {
        Log "AVERTISSEMENT : rclone absent, pas de synchronisation cloud."
    }
}

# Le journal ne doit pas grossir indéfiniment.
if ((Test-Path $LogFile) -and ((Get-Item $LogFile).Length -gt 1MB)) {
    Get-Content $LogFile -Tail 500 | Set-Content "$LogFile.tmp" -Encoding utf8
    Move-Item "$LogFile.tmp" $LogFile -Force
}
