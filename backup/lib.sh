# Configuration partagée des scripts de sauvegarde
# (sourcée par backup.sh et restore.sh — machine distante Linux).

# Répertoire racine des sauvegardes (hors du dépôt)
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups/innovskool}"
# Nombre de sauvegardes conservées par niveau de rotation
# Par jour, ne conserver que les N sauvegardes les plus récentes
# (16 = une journée de présence, 6 h → 22 h)
KEEP_PER_DAY="${KEEP_PER_DAY:-16}"
# Âge maximal des sauvegardes (jours) : rien n'est conservé au-delà
MAX_AGE_DAYS="${MAX_AGE_DAYS:-30}"
# Journal
LOG_FILE="${LOG_FILE:-$BACKUP_ROOT/backup.log}"
# Conteneur MySQL/MariaDB (auto-détecté s'il n'est pas fourni)
DB_CONTAINER="${DB_CONTAINER:-}"
# Cible rclone « remote:chemin » (vide = pas de synchronisation cloud)
RCLONE_TARGET="${RCLONE_TARGET:-}"
# Deuxième cible rclone (ex. la machine partenaire en SFTP) et répertoire
# local qui reçoit les dumps de cette machine (réplication croisée).
RCLONE_TARGET2="${RCLONE_TARGET2:-}"
REPLICA_DIR="${REPLICA_DIR:-}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Surcharges locales éventuelles (fichier optionnel à côté des scripts,
# par exemple : RCLONE_TARGET="secret:prod")
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

# Conteneur de base : variable DB_CONTAINER sinon auto-détection du conteneur
# dont l'image est MySQL ou MariaDB (le nom de service « database » du réseau
# Docker n'est pas forcément le nom du conteneur).
find_db_container() {
    if [[ -n "$DB_CONTAINER" ]]; then
        return 0
    fi
    DB_CONTAINER="$(docker ps --format '{{.Names}} {{.Image}}' 2>/dev/null \
        | awk 'tolower($2) ~ /mysql|mariadb/ {print $1; exit}')"
    [[ -n "$DB_CONTAINER" ]]
}
