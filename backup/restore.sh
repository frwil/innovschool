#!/usr/bin/env bash
#
# Restauration d'un dump créé par backup.sh (machine distante, prod).
# Usage : bash backup/restore.sh /var/backups/innovskool/hourly/innovskool-XXX.sql.gz
# ⚠️ Remplace les données actuelles de la base.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/lib.sh"

DUMP="${1:-}"
if [[ -z "$DUMP" || ! -f "$DUMP" ]]; then
    echo "Usage : $0 <chemin-du-dump.sql.gz>" >&2
    exit 1
fi

load_db_credentials || exit 1

echo "Restauration de « $DB_NAME » depuis $DUMP"
echo "⚠️  Les données actuelles de la base seront remplacées."
read -r -p "Taper OUI pour confirmer : " CONFIRM
[[ "$CONFIRM" == "OUI" ]] || { echo "Abandon."; exit 1; }

gzip -cd "$DUMP" | docker exec -i -e MYSQL_PWD="$DB_PASS" "$DB_CONTAINER" \
    mysql -u"$DB_USER" "$DB_NAME"

echo "Restauration terminée."
