# Sauvegardes de la base de données

Dumps SQL horaires (mysqldump + gzip) avec rotation **24 h / 7 jours / 4 semaines / 12 mois**, pour les deux machines, et synchronisation cloud optionnelle via rclone.

| Machine | Script | Ordonnancement | Base |
|---|---|---|---|
| Distante (prod, Docker) | `backup.sh` | cron, toutes les heures à :17 | conteneur `database` |
| Windows (dev, Docker Desktop) | `backup.ps1` | Planificateur de tâches, toutes les heures à :17 | conteneur `database` |

Le dump est fait **dans** le conteneur (`--single-transaction`, procédures/événements/déclencheurs inclus), compressé en gzip, vérifié (`gzip -t`, taille minimale), puis tourné. Les identifiants sont lus dans le `.env` de l'application (`DATABASE_URL`) — rien à dupliquer. Aucun dump n'est écrit dans le dépôt.

---

## Machine distante (Linux)

Prérequis : le dépôt à jour (`git pull`), Docker en marche, `cron` et `flock` (util-linux, présent partout).

1. **Premier test manuel** (le script crée ses répertoires lui-même) :
   ```bash
   cd /chemin/vers/innovschool
   bash backup/backup.sh
   ls -lh /var/backups/innovskool/hourly/
   cat /var/backups/innovskool/backup.log
   ```
   (pour un autre emplacement : `BACKUP_ROOT=/srv/backups bash backup/backup.sh`)

2. **Cron** (root ou utilisateur du groupe `docker`) :
   ```bash
   sudo crontab -e
   ```
   ```cron
   17 * * * * bash /chemin/vers/innovschool/backup/backup.sh
   ```

3. **Cloud (rclone, optionnel)** — exemple Google Drive :
   ```bash
   sudo curl https://rclone.org/install.sh | sudo bash
   rclone config
   # n (nouveau) → name: gdrive → type: drive → client_id/client_secret: vide (par défaut)
   # → scope: drive → config sur une machine avec navigateur (ou `rclone authorize`)
   ```
   Le plus simple sans navigateur sur le serveur : configurer `gdrive` sur la machine Windows (`rclone config`), puis copier le bloc `[gdrive]` du fichier `rclone.conf` dans `~/.config/rclone/rclone.conf` du serveur.
   Ensuite, créer `backup/backup.conf` à côté des scripts :
   ```bash
   RCLONE_TARGET="gdrive:innovskool-backups/prod"
   ```
   Vérifier : `rclone lsf gdrive:innovskool-backups/prod/hourly | head` (après un passage horaire).

---

## Machine Windows (dev)

Le script tolère Docker Desktop arrêté (il journalise et se retire sans erreur).

1. **Test manuel** (PowerShell) :
   ```powershell
   powershell -NoProfile -ExecutionPolicy Bypass -File D:\innovschool\backup\backup.ps1
   dir $env:USERPROFILE\backups\innovskool\hourly
   ```

2. **Planificateur de tâches** (toutes les heures à :17, en tant qu'utilisateur connecté) :
   ```
   schtasks /Create /TN "InnovskoolBackup" /F /SC HOURLY /MO 1 /ST 00:17 ^
     /TR "powershell.exe -NoProfile -ExecutionPolicy Bypass -File D:\innovschool\backup\backup.ps1"
   ```
   Variante « même déconnecté » : ajouter `/RU MOUTEN /RP <mot-de-passe>` (ou utiliser le Planificateur graphique). Docker Desktop doit être lancé à l'ouverture de session.

3. **Cloud (optionnel)** : installer rclone (`winget install Rclone.Rclone`), configurer `gdrive`, puis définir la variable :
   ```powershell
   setx INNOVSKOOL_RCLONE_TARGET "gdrive:innovskool-backups/dev"
   ```
   (redémarrer la session pour que `setx` s'applique à la tâche planifiée).

---

## Restauration

**Prod (Linux)** — remplace la base actuelle (confirmation demandée) :
```bash
cd /chemin/vers/innovschool
bash backup/restore.sh /var/backups/innovskool/hourly/innovskool-XXXX.sql.gz
```

**Dev (Windows)** :
```powershell
docker cp D:\chemin\dump.sql.gz database:/tmp/restore.sql.gz
docker exec -e MYSQL_PWD=<mot-de-passe> database sh -c "gzip -cd /tmp/restore.sql.gz | mysql -uroot innovsh"
docker exec database rm -f /tmp/restore.sql.gz
```

**Depuis le cloud** : `rclone copy gdrive:innovskool-backups/prod/hourly/innovskool-XXX.sql.gz .` puis restauration comme ci-dessus.

> Tester une restauration de temps en temps (par exemple sur la base dev) : une sauvegarde jamais restaurée n'est qu'une promesse.

---

## Notes

- **Espace disque** : un dump gzip fait typiquement 20–60 Mo pour une base d'école ; l'ensemble (24 h + 7 j + 4 sem + 12 mois) occupe environ 1 à 3 Go, localement et dans le cloud.
- **Journal** : `/var/backups/innovskool/backup.log` (prod) ou `%USERPROFILE%\backups\innovskool\backup.log` (dev), auto-tronqué à 1 Mo. Le journal `rclone.log` reçoit les erreurs de synchronisation.
- **Hors périmètre** : les fichiers téléversés (`public/uploads/`), les secrets et la config serveur ne sont pas couverts — dumps de la base uniquement.
- **Limites du dump SQL** : pas de reprise point-dans-le-temps à la minute (il faudrait les binlogs) ; une transaction en cours au moment du dump n'est pas incluse (`--single-transaction` donne un instantané cohérent).
- Les **scripts** sont versionnés dans `backup/` (livrés avec le dépôt) ; les **dumps**, eux, vivent hors du dépôt (`/var/backups/...` et `%USERPROFILE%\backups\...`) et ne sont jamais commités.
