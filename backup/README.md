# Sauvegardes de la base de données

Dumps SQL horaires (mysqldump + gzip) avec rotation **24 h / 7 jours / 4 semaines / 12 mois**, sur les deux machines (toutes deux Windows 11 + Docker), et synchronisation cloud optionnelle via rclone.

| Machine | Script | Ordonnancement | Cible cloud rclone |
|---|---|---|---|
| Distante (prod, Docker) | `backup.ps1` | Planificateur de tâches, toutes les heures à :17 | `…/prod` |
| Windows (dev, Docker Desktop) | `backup.ps1` | Planificateur de tâches, toutes les heures à :17 | `…/dev` |

Le dump est fait **dans** le conteneur `database` (`--single-transaction`, procédures/événements/déclencheurs inclus), compressé en gzip, vérifié (`gzip -t`, taille minimale), copié en binaire via `docker cp`, puis tourné. Les identifiants sont lus dans le `.env` de l'application (`DATABASE_URL`) — rien à dupliquer. Docker arrêté → la tâche journalise et se retire sans erreur. Aucun dump n'est écrit dans le dépôt.

> Variante Linux (si l'hôte devient un jour Linux) : `backup.sh`, `lib.sh`, `restore.sh` + cron — voir la dernière section.

---

## Machine distante (Windows 11, prod)

Prérequis : le dépôt à jour (`git pull`), Docker démarré, le conteneur `database` en marche.

1. **Premier test manuel** (PowerShell) :
   ```powershell
   powershell -NoProfile -ExecutionPolicy Bypass -File <chemin-vers-innovschool>\backup\backup.ps1
   dir $env:USERPROFILE\backups\innovskool\hourly
   type $env:USERPROFILE\backups\innovskool\backup.log
   ```
   Autre emplacement : `setx INNOVSKOOL_BACKUP_DIR "D:\backups\innovskool"` (nouvelle session requise).

2. **Planificateur de tâches** (toutes les heures à :17) :
   ```
   schtasks /Create /TN "InnovskoolBackup" /F /SC HOURLY /MO 1 /ST 00:17 ^
     /TR "powershell.exe -NoProfile -ExecutionPolicy Bypass -File <chemin-vers-innovschool>\backup\backup.ps1"
   ```
   ⚠️ Par défaut la tâche ne tourne que si l'utilisateur est connecté. Pour qu'elle tourne toujours (session verrouillée comprise) : ajouter `/RU <utilisateur> /RP <mot-de-passe>`, ou cocher « Exécuter même si l'utilisateur n'est pas connecté » dans le Planificateur graphique (il demandera le mot de passe). Docker Desktop doit être configuré pour démarrer à l'ouverture de session.

3. **Cloud (rclone, optionnel)** — exemple Google Drive :
   ```powershell
   winget install Rclone.Rclone
   rclone config
   # n (nouveau) → name: gdrive → type: drive → client_id/client_secret: vide
   # (identifiants rclone par défaut) → scope: drive → autorisation via navigateur
   setx INNOVSKOOL_RCLONE_TARGET "gdrive:innovskool-backups/prod"
   ```
   (nouvelle session requise pour que la tâche planifiée voie la variable ; pour un test immédiat : définir `$env:INNOVSKOOL_RCLONE_TARGET` dans la même fenêtre PowerShell.)
   Vérifier : `rclone lsf gdrive:innovskool-backups/prod/hourly | select -First 5` (après un passage horaire).

---

## Machine de développement (Windows 11)

Identique, avec la cible cloud `…/dev` si rclone est utilisé :

1. Test manuel : même commande `backup.ps1` ci-dessus.
2. Tâche planifiée : même commande `schtasks`, même nom `InnovskoolBackup`.
3. rclone : `setx INNOVSKOOL_RCLONE_TARGET "gdrive:innovskool-backups/dev"`.

---

## Restauration

**Sur n'importe laquelle des deux machines** (remplace la base actuelle) :
```powershell
docker cp <chemin>\innovskool-XXXX.sql.gz database:/tmp/restore.sql.gz
docker exec -e MYSQL_PWD=<mot-de-passe> database sh -c "gzip -cd /tmp/restore.sql.gz | mysql -uroot <nom-de-base>"
docker exec database rm -f /tmp/restore.sql.gz
```
(utilisateur, mot de passe et nom de base sont dans le `DATABASE_URL` du `.env` ; base `innovsh` par défaut)

**Depuis le cloud** : `rclone copy gdrive:innovskool-backups/prod/hourly/innovskool-XXX.sql.gz .` puis la même procédure.

> Tester une restauration de temps en temps (par exemple sur la base dev) : une sauvegarde jamais restaurée n'est qu'une promesse.

---

## Variante Linux (si l'hôte devient Linux)

`backup.sh` (sauvegarde + rotation + rclone), `lib.sh` (config partagée, identifiants depuis `.env`) et `restore.sh` (restauration avec confirmation). Premier test : `bash backup/backup.sh` (écrit dans `/var/backups/innovskool/`). Cron :

```cron
17 * * * * bash /chemin/vers/innovschool/backup/backup.sh
```

Configuration cloud : fichier `backup/backup.conf` à côté des scripts avec `RCLONE_TARGET="gdrive:innovskool-backups/prod"`. Restauration : `bash backup/restore.sh /var/backups/innovskool/hourly/innovskool-XXX.sql.gz`.

---

## Notes

- **Espace disque** : un dump gzip fait typiquement 20–60 Mo pour une base d'école ; l'ensemble (24 h + 7 j + 4 sem + 12 mois) occupe environ 1 à 3 Go par machine, localement et dans le cloud.
- **Journal** : `%USERPROFILE%\backups\innovskool\backup.log`, auto-tronqué à 1 Mo. Le journal `rclone.log` reçoit les erreurs de synchronisation.
- **Hors périmètre** : les fichiers téléversés (`public/uploads/`), les secrets et la config serveur ne sont pas couverts — dumps de la base uniquement.
- **Limites du dump SQL** : pas de reprise point-dans-le-temps à la minute (il faudrait les binlogs) ; `--single-transaction` garantit un instantané cohérent des données engagées.
- Les **scripts** sont versionnés dans `backup/` (livrés avec le dépôt) ; les **dumps**, eux, vivent hors du dépôt (`%USERPROFILE%\backups\…`) et ne sont jamais commités.
