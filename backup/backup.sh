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

mkdir -p "$BACKUP_ROOT/hourly"

# Une seule sauvegarde à la fois (évite le chevauchement si un dump traîne)
exec 9>"$BACKUP_ROOT/.lock"
if ! flock -n 9; then
    log "Sauvegarde déjà en cours, abandon."
    exit 0
fi

load_db_credentials || exit 1
find_db_container || { log "Aucun conteneur MySQL/MariaDB en marche, abandon."; exit 1; }

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

# Rétention commune : par jour, ne conserver que les KEEP_PER_DAY sauvegardes
# les plus récentes (16 = les heures de présence, 6 h → 22 h), rien au-delà
# de MAX_AGE_DAYS jours, et garde-fou : la sauvegarde la plus récente n'est
# jamais supprimée (machine éteinte longtemps → on garde le dernier point
# connu plutôt que rien). $1 = racine à écréter (chemins sans espaces).
apply_retention() {
    local root="$1" ALL_GZ CUTOFF DELETED i
    [[ -d "$root" ]] || return 0
    find "$root" -type f -name '*.gz' -printf '%T@ %TY-%Tm-%Td %p\n' \
        | sort -rn \
        | awk -v keep="$KEEP_PER_DAY" '$2 != day { day = $2; n = 0 } { n++ } n > keep { print $3 }' \
        | xargs -r rm -f --
    mapfile -t ALL_GZ < <(find "$root" -type f -name '*.gz' -printf '%T@\t%p\n' | sort -n | cut -f2-)
    if [[ "${#ALL_GZ[@]}" -gt 1 ]]; then
        CUTOFF="$(date -d "$MAX_AGE_DAYS days ago" +%s)"
        DELETED=0
        # on parcourt tous les fichiers sauf le dernier (le plus récent)
        for ((i = 0; i < ${#ALL_GZ[@]} - 1; i++)); do
            if [[ "$(stat -c %Y "${ALL_GZ[$i]}")" -lt "$CUTOFF" ]]; then
                rm -f -- "${ALL_GZ[$i]}"
                DELETED=$((DELETED + 1))
            fi
        done
        if [[ "$DELETED" -gt 0 ]]; then
            log "Suppression des sauvegardes de plus de $MAX_AGE_DAYS jours ($(basename "$root")) : $DELETED fichier(s) supprimé(s)."
        fi
    fi
}

apply_retention "$BACKUP_ROOT"
# Dumps reçus de l'autre machine (réplication croisée) : même rétention.
# Scopé au sous-dossier « hourly » : d'autres projets (GS) poussent leurs
# dumps dans des sous-dossiers voisins (replicas/gs) et ne doivent pas
# être écrêtés par la rétention d'innovskool.
[[ -n "$REPLICA_DIR" ]] && apply_retention "$REPLICA_DIR/hourly"

# Synchronisation (rclone) : cloud et/ou réplication croisée, si configurées.
TARGETS=()
[[ -n "$RCLONE_TARGET" ]] && TARGETS+=("$RCLONE_TARGET")
[[ -n "$RCLONE_TARGET2" ]] && TARGETS+=("$RCLONE_TARGET2")
if [[ "${#TARGETS[@]}" -gt 0 ]]; then
    if command -v rclone >/dev/null 2>&1; then
        for TARGET in "${TARGETS[@]}"; do
            for LEVEL in hourly; do
                if ! rclone sync "$BACKUP_ROOT/$LEVEL" "$TARGET/$LEVEL" \
                        --log-file="$BACKUP_ROOT/rclone.log" --log-level ERROR; then
                    log "AVERTISSEMENT : synchronisation échouée ($TARGET/$LEVEL)."
                fi
            done
            log "Synchronisation terminée vers $TARGET."
        done
    else
        log "AVERTISSEMENT : rclone absent, pas de synchronisation."
    fi
fi

# Le journal ne doit pas grossir indéfiniment.
if [[ -f "$LOG_FILE" && "$(stat -c%s "$LOG_FILE")" -gt 1048576 ]]; then
    tail -n 500 "$LOG_FILE" > "$LOG_FILE.tmp" && mv "$LOG_FILE.tmp" "$LOG_FILE"
fi
