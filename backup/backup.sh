#!/usr/bin/env bash
#
# Sauvegarde horaire de la base de données innovschool (machine distante, prod).
# À exécuter sur l'hôte (le dump passe par le conteneur « database ») :
#   bash backup/backup.sh
# ou via cron (voir README.md). Configuration : variables de lib.sh,
# surchargeables par le fichier optionnel backup/backup.conf.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/lib.sh"

mkdir -p "$BACKUP_ROOT/hourly" "$BACKUP_ROOT/daily" "$BACKUP_ROOT/weekly" "$BACKUP_ROOT/monthly"

# Une seule sauvegarde à la fois (évite le chevauchement si un dump traîne)
exec 9>"$BACKUP_ROOT/.lock"
if ! flock -n 9; then
    log "Sauvegarde déjà en cours, abandon."
    exit 0
fi

load_db_credentials || exit 1

# --set-gtid-purged n'existe pas sous MariaDB
SERVER_VERSION="$(docker exec "$DB_CONTAINER" sh -c 'mysqld --version 2>/dev/null || mariadbd --version 2>/dev/null' 2>/dev/null || true)"
GTID_FLAG="--set-gtid-purged=OFF"
[[ "$SERVER_VERSION" == *MariaDB* ]] && GTID_FLAG=""

STAMP="$(date '+%Y%m%d-%H%M%S')"
TMP_FILE="$BACKUP_ROOT/hourly/innovskool-$STAMP.sql.gz.tmp"
FINAL_FILE="$BACKUP_ROOT/hourly/innovskool-$STAMP.sql.gz"

log "Début de la sauvegarde de la base « $DB_NAME »."

# Dump cohérent (InnoDB), procédures/événements/déclencheurs inclus,
# compressé en gzip. Le mot de passe passe par MYSQL_PWD (pas en argument).
if ! docker exec -e MYSQL_PWD="$DB_PASS" "$DB_CONTAINER" \
        mysqldump --single-transaction --quick --routines --events --triggers $GTID_FLAG \
        -u"$DB_USER" "$DB_NAME" 2>>"$LOG_FILE" \
        | gzip -9 > "$TMP_FILE"; then
    rm -f "$TMP_FILE"
    log "ERREUR : mysqldump a échoué (voir journal)."
    exit 1
fi

# Un dump sain fait bien plus de 10 Ko : refuse les fichiers vides ou tronqués.
SIZE="$(stat -c%s "$TMP_FILE")"
if [[ "$SIZE" -lt 10240 ]]; then
    rm -f "$TMP_FILE"
    log "ERREUR : dump anormalement petit (${SIZE} o), abandon."
    exit 1
fi
if ! gzip -t "$TMP_FILE" 2>>"$LOG_FILE"; then
    rm -f "$TMP_FILE"
    log "ERREUR : dump corrompu (échec de gzip -t)."
    exit 1
fi
mv "$TMP_FILE" "$FINAL_FILE"
log "Sauvegarde OK : $FINAL_FILE ($((SIZE / 1024)) Ko)."

# Rotation : ne conserver que les N fichiers les plus récents de chaque niveau.
prune() { # $1 répertoire, $2 nombre à conserver
    [[ -n "$(ls "$1"/*.gz 2>/dev/null)" ]] || return 0
    ls -1t "$1"/*.gz 2>/dev/null | tail -n +$(($2 + 1)) | xargs -r rm -f --
}
prune "$BACKUP_ROOT/hourly" "$KEEP_HOURLY"

# Quotidienne à 02h, hebdomadaire le dimanche à 03h, mensuelle le 1er à 05h
# (chaque passage horaire décide lui-même de son niveau).
HOUR="$(date +%-H)"
if [[ "$HOUR" == "2" ]]; then
    cp "$FINAL_FILE" "$BACKUP_ROOT/daily/" && prune "$BACKUP_ROOT/daily" "$KEEP_DAILY"
    log "Copie quotidienne conservée."
fi
if [[ "$HOUR" == "3" && "$(date +%u)" == "7" ]]; then
    cp "$FINAL_FILE" "$BACKUP_ROOT/weekly/" && prune "$BACKUP_ROOT/weekly" "$KEEP_WEEKLY"
    log "Copie hebdomadaire conservée."
fi
if [[ "$HOUR" == "5" && "$(date +%-d)" == "1" ]]; then
    cp "$FINAL_FILE" "$BACKUP_ROOT/monthly/" && prune "$BACKUP_ROOT/monthly" "$KEEP_MONTHLY"
    log "Copie mensuelle conservée."
fi

# Synchronisation cloud (rclone), si configurée.
if [[ -n "$RCLONE_TARGET" ]]; then
    if command -v rclone >/dev/null 2>&1; then
        for LEVEL in hourly daily weekly monthly; do
            if ! rclone sync "$BACKUP_ROOT/$LEVEL" "$RCLONE_TARGET/$LEVEL" \
                    --log-file="$BACKUP_ROOT/rclone.log" --log-level ERROR; then
                log "AVERTISSEMENT : synchronisation cloud échouée ($LEVEL)."
            fi
        done
        log "Synchronisation cloud terminée vers $RCLONE_TARGET."
    else
        log "AVERTISSEMENT : rclone absent, pas de synchronisation cloud."
    fi
fi

# Le journal ne doit pas grossir indéfiniment.
if [[ -f "$LOG_FILE" && "$(stat -c%s "$LOG_FILE")" -gt 1048576 ]]; then
    tail -n 500 "$LOG_FILE" > "$LOG_FILE.tmp" && mv "$LOG_FILE.tmp" "$LOG_FILE"
fi
