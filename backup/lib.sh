# Configuration partagée des scripts de sauvegarde
# (sourcée par backup.sh et restore.sh — machine distante Linux).

# Répertoire racine des sauvegardes (hors du dépôt)
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/innovskool}"
# Nombre de sauvegardes conservées par niveau de rotation
KEEP_HOURLY="${KEEP_HOURLY:-24}"
KEEP_DAILY="${KEEP_DAILY:-7}"
KEEP_WEEKLY="${KEEP_WEEKLY:-4}"
KEEP_MONTHLY="${KEEP_MONTHLY:-12}"
# Journal
LOG_FILE="${LOG_FILE:-$BACKUP_ROOT/backup.log}"
# Conteneur MySQL/MariaDB
DB_CONTAINER="${DB_CONTAINER:-database}"
# Cible rclone « remote:chemin » (vide = pas de synchronisation cloud)
RCLONE_TARGET="${RCLONE_TARGET:-}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Surcharges locales éventuelles (fichier optionnel à côté des scripts,
# par exemple : RCLONE_TARGET="gdrive:innovskool-backups/prod")
if [[ -f "$SCRIPT_DIR/backup.conf" ]]; then
    # shellcheck disable=SC1091
    source "$SCRIPT_DIR/backup.conf"
fi

# .env de l'application (dépôt racine, gitignoré)
ENV_FILE="${APP_ENV_FILE:-$SCRIPT_DIR/../.env}"

urldecode() {
    local s="$1"
    s="${s//+/ }"
    printf '%b' "${s//%/\\x}"
}

# Identifiants de connexion depuis le DATABASE_URL de l'application.
# Les valeurs URL-encodées (mot de passe avec caractères spéciaux) sont décodées.
load_db_credentials() {
    DATABASE_URL=""
    if [[ -f "$ENV_FILE" ]]; then
        DATABASE_URL="$(grep -E '^DATABASE_URL=' "$ENV_FILE" | tail -n1 | cut -d= -f2- | tr -d '"' | tr -d "'" | tr -d '[:space:]')"
    fi
    if [[ -z "$DATABASE_URL" ]]; then
        echo "ERREUR : DATABASE_URL introuvable dans $ENV_FILE" >&2
        return 1
    fi
    DB_USER="$(urldecode "$(echo "$DATABASE_URL" | sed -E 's#^mysql://([^:]+):.*#\1#')")"
    DB_PASS="$(urldecode "$(echo "$DATABASE_URL" | sed -E 's#^mysql://[^:]+:([^@]+)@.*#\1#')")"
    DB_NAME="$(urldecode "$(echo "$DATABASE_URL" | sed -E 's#^mysql://[^/]+/([^?]+).*#\1#')")"
}

log() { echo "[$(date '+%F %T')] $*" >> "$LOG_FILE"; }
