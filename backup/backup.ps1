# Sauvegarde horaire de la base de données (Windows 11 + Docker, prod ou dev).
# À planifier dans le Planificateur de tâches (voir README.md).
# Le dump passe par le conteneur et est copié avec docker cp (binaire sûr).
$ErrorActionPreference = "Stop"

$ScriptDir    = Split-Path -Parent $MyInvocation.MyCommand.Path
$EnvFile      = Join-Path (Split-Path -Parent $ScriptDir) ".env"
$BackupRoot   = if ($env:INNOVSKOOL_BACKUP_DIR) { $env:INNOVSKOOL_BACKUP_DIR } else { Join-Path $env:USERPROFILE "backups\innovskool" }
$DbContainer  = $env:INNOVSKOOL_DB_CONTAINER  # surcharge possible ; sinon auto-détection ci-dessous
# Cibles rclone : cible 1 (cloud) et cible 2 (réplication croisée), ex.
# "secret:dev" ou "sftp-maison:backups/replicas" ; vide = ignorée.
$RcloneTargets = @($env:INNOVSKOOL_RCLONE_TARGET, $env:INNOVSKOOL_RCLONE_TARGET2) | Where-Object { $_ }
# Répertoire qui reçoit les dumps de l'autre machine (réplication croisée) ;
# même rétention que les dumps locaux. Vide = pas de réplication.
$ReplicaDir   = if ($env:INNOVSKOOL_REPLICA_DIR) { [Environment]::ExpandEnvironmentVariables($env:INNOVSKOOL_REPLICA_DIR) } else { $null }
$KeepPerDay   = if ($env:INNOVSKOOL_KEEP_PER_DAY) { [int]$env:INNOVSKOOL_KEEP_PER_DAY } else { 16 }
$MaxAgeDays   = if ($env:INNOVSKOOL_MAX_AGE_DAYS) { [int]$env:INNOVSKOOL_MAX_AGE_DAYS } else { 30 }
$LogFile      = Join-Path $BackupRoot "backup.log"

foreach ($dir in @("hourly")) {
    New-Item -ItemType Directory -Force -Path (Join-Path $BackupRoot $dir) | Out-Null
}

function Log([string]$msg) {
    Add-Content -Path $LogFile -Value "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $msg"
}

# Docker doit tourner.
try { & docker info 2>&1 | Out-Null } catch {}
if ($LASTEXITCODE -ne 0) { Log "Docker non démarré : sauvegarde ignorée."; exit 0 }

# Conteneur de base : variable INNOVSKOOL_DB_CONTAINER sinon auto-détection
# du conteneur dont l'image est MySQL ou MariaDB (le nom de service
# « database » dans le réseau Docker n'est pas forcément le nom du conteneur).
if (-not $DbContainer) {
    try { $psLines = (& docker ps --format "{{.Names}} {{.Image}}" 2>&1) } catch { $psLines = @() }
    $DbContainer = ($psLines | Where-Object { $_ -match "(mysql|mariadb)" } | Select-Object -First 1) -replace " .*$", ""
}
if (-not $DbContainer) { Log "Aucun conteneur MySQL/MariaDB en marche : sauvegarde ignorée."; exit 0 }

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

# Rétention commune : par jour, ne conserver que les $KeepPerDay sauvegardes
# les plus récentes (16 = les heures de présence, 6 h → 22 h), rien au-delà
# de $MaxAgeDays jours, et garde-fou : la sauvegarde la plus récente n'est
# jamais supprimée — si elle est seule (machine éteinte longtemps), on garde
# le dernier point connu plutôt que rien.
function Apply-Retention([string]$root) {
    if (-not (Test-Path $root)) { return }
    Get-ChildItem $root -Recurse -Filter *.gz -File |
        Group-Object { $_.LastWriteTime.Date } |
        ForEach-Object {
            $_.Group | Sort-Object LastWriteTime -Descending |
                Select-Object -Skip $KeepPerDay |
                Remove-Item -Force
        }
    $all = @(Get-ChildItem $root -Recurse -Filter *.gz -File)
    if ($all.Count -gt 1) {
        $cutoff = (Get-Date).AddDays(-$MaxAgeDays)
        $newest = $all | Sort-Object LastWriteTime -Descending | Select-Object -First 1
        $old = @($all | Where-Object { $_.LastWriteTime -lt $cutoff -and $_.FullName -ne $newest.FullName })
        if ($old.Count -gt 0) {
            $old | Remove-Item -Force
            Log "Suppression des sauvegardes de plus de $MaxAgeDays jours : $($old.Count) fichier(s) supprimé(s)."
        }
    }
}

Apply-Retention $BackupRoot
# Dumps reçus de l'autre machine (réplication croisée) : même rétention.
# Scopé au sous-dossier « hourly » : d'autres projets (GS) poussent leurs
# dumps dans des sous-dossiers voisins (replicas\gs) et ne doivent pas
# être écrêtés par la rétention d'innovskool.
if ($ReplicaDir) { Apply-Retention (Join-Path $ReplicaDir "hourly") }

# Synchronisation (rclone) : cloud et/ou réplication croisée, si configurées.
if ($RcloneTargets.Count -gt 0) {
    # rclone peut ne pas être dans le PATH (shell ouvert avant l'installation
    # par winget) : repli sur le lien winget standard.
    $rcloneCmd = Get-Command rclone -ErrorAction SilentlyContinue
    if (-not $rcloneCmd) {
        $rcloneCmd = Get-Command (Join-Path $env:LOCALAPPDATA "Microsoft\WinGet\Links\rclone.exe") -ErrorAction SilentlyContinue
    }
    if ($rcloneCmd) {
        foreach ($target in $RcloneTargets) {
            foreach ($level in @("hourly")) {
                & $rcloneCmd.Source sync (Join-Path $BackupRoot $level) "$target/$level" `
                    --log-file (Join-Path $BackupRoot "rclone.log") --log-level ERROR
                if ($LASTEXITCODE -ne 0) { Log "AVERTISSEMENT : synchronisation échouée ($target/$level)." }
            }
            Log "Synchronisation terminée vers $target."
        }
    } else {
        Log "AVERTISSEMENT : rclone absent, pas de synchronisation."
    }
}

# Le journal ne doit pas grossir indéfiniment.
if ((Test-Path $LogFile) -and ((Get-Item $LogFile).Length -gt 1MB)) {
    Get-Content $LogFile -Tail 500 | Set-Content "$LogFile.tmp" -Encoding utf8
    Move-Item "$LogFile.tmp" $LogFile -Force
}
