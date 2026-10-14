# Sauvegardes de la base de données

Dumps SQL horaires (mysqldump + gzip) avec rétention **16 sauvegardes par jour** (les plus récentes, 6 h → 22 h) et plafond d'âge **30 jours** (la sauvegarde la plus récente n'est jamais supprimée), sur les deux machines (toutes deux Windows 11 + Docker), avec **deux copies distantes possibles** : le cloud (Backblaze B2 région EU Central, chiffré côté client par rclone) et la **réplication croisée** entre les deux machines (chacune reçoit les dumps de l'autre).

| Machine | Script | Ordonnancement | Cloud (cible 1) | Réplication (cible 2) |
|---|---|---|---|---|
| Distante (prod, Docker) | `backup.ps1` | Planificateur de tâches, toutes les heures à :17 | `secret:prod` | `sftp-maison:backups/replicas` |
| Windows (dev, Docker Desktop) | `backup.ps1` | Planificateur de tâches, toutes les heures à :17 | `secret:dev` | `sftp-ecole:backups/replicas` |

Le dump est fait **dans** le conteneur MySQL/MariaDB (`--single-transaction`, procédures/événements/déclencheurs inclus), compressé en gzip, vérifié (`gzip -t`, taille minimale), copié en binaire via `docker cp`, puis écrêté à 16 par jour ; toute sauvegarde de plus de 30 jours est supprimée — jamais la plus récente, même si elle est seule. Les identifiants sont lus dans le `.env` de l'application (`DATABASE_URL`) — rien à dupliquer. Le conteneur de base est **auto-détecté** (`docker ps`, image `mysql` ou `mariadb`) ; surcharge possible par la variable `INNOVSKOOL_DB_CONTAINER` (PowerShell) ou `DB_CONTAINER` (variante Linux). Docker arrêté → la tâche journalise et se retire sans erreur. Aucun dump n'est écrit dans le dépôt.

> Variante Linux (si l'hôte devient un jour Linux) : `backup.sh`, `lib.sh`, `restore.sh` + cron — voir la dernière section.

---

## Machine distante (Windows 11, prod)

Prérequis : le dépôt à jour (`git pull`), Docker démarré, le conteneur MySQL en marche.

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

3. **Cloud (cible 1) — Backblaze B2 (EU Central), chiffré** :
   - Créer un compte [Backblaze B2](https://www.backblaze.com/b2/cloud-storage.html) (10 Go gratuits, **aucune carte bancaire** demandée) en choisissant **EU Central** comme région à l'inscription (menu déroulant « Region », US West par défaut — la région du compte ne peut plus être changée ensuite ; EU Central = centre de données d'Amsterdam), puis créer un **bucket** privé `innovskool-backups` et une **Application Key** limitée à ce bucket en **Read & Write** (Account → Application Keys) : elle fournit `keyID` et `applicationKey`.
   - Configurer rclone (aucun navigateur, tout en ligne de commande) :
     ```powershell
     winget install Rclone.Rclone
     rclone config create b2 b2 account <keyID> key <applicationKey>
     rclone config create secret crypt remote=b2:innovskool-backups ^
       password <mot-de-passe-long> password2 <autre-mot-de-passe-long>
     setx INNOVSKOOL_RCLONE_TARGET "secret:prod"   # « secret:dev » sur la machine de dev
     ```
     ⚠️ Conserver les deux mots de passe `crypt` précieusement (gestionnaire de mots de passe — et/ou le fichier local `%USERPROFILE%\backups\rclone-crypt.txt`) : perdus = dumps du cloud illisibles. Le chiffrement est fait **localement** par rclone avant l'envoi : B2 ne stocke que du bruit, à Amsterdam.
     (Nouvelle session requise pour que la tâche planifiée voie la variable ; pour un test immédiat : définir `$env:INNOVSKOOL_RCLONE_TARGET` dans la même fenêtre PowerShell.)
     Vérifier : `rclone lsf -R secret:prod/hourly` (après un passage horaire).
   - Autres fournisseurs possibles : OVH Object Storage, Google Drive, OneDrive… seul le nom du remote change (Scaleway Object Storage exige une carte physique validée — bloqué avec une carte virtuelle ; MediaFire n'est pas géré par rclone).

4. **Réplication croisée (cible 2)** — chaque machine envoie ses dumps sur l'autre (aucun tiers, copie hors site même si le cloud tombe) :
   1. Installer **Tailscale** sur les deux machines (`winget install Tailscale.Tailscale`, puis `tailscale up` et se connecter) : elles se voient ensuite par des noms stables (ex. `innovskool-ecole`, `innovskool-maison`), même derrière les box.
   2. Activer le **serveur OpenSSH** sur les deux machines (PowerShell **administrateur**) :
      ```powershell
      Add-WindowsCapability -Online -Name OpenSSH.Server~~~~0.0.1.0
      Start-Service sshd
      Set-Service -Name sshd -StartupType Automatic
      ```
   3. Déclarer l'autre machine dans rclone (sur chaque machine) :
      ```powershell
      rclone obscure <mot-de-passe-ssh>    # copier la sortie
      rclone config create sftp-maison sftp host innovskool-maison ^
        user <utilisateur> pass <sortie-obscurcie> shell_type powershell ^
        md5sum_command none sha1sum_command none
      ```
      (sur l'autre machine : `sftp-ecole`, hôte `innovskool-ecole`)
   4. Variables (sur cette machine, prod) :
      ```powershell
      setx INNOVSKOOL_RCLONE_TARGET2 "sftp-maison:backups/replicas"
      setx INNOVSKOOL_REPLICA_DIR "%USERPROFILE%\backups\replicas"
      ```
      Sur l'autre machine : cible 2 = `sftp-ecole:backups/replicas`, même `INNOVSKOOL_REPLICA_DIR`. `backups/replicas` est relatif au répertoire personnel SSH de l'autre machine ; le script y applique la même rétention (16/jour, 30 jours, jamais la plus récente).
      Vérifier (après un passage horaire) : `dir $env:USERPROFILE\backups\replicas\hourly`.

---

## Machine de développement (Windows 11)

Identique, avec la cible cloud `secret:dev`, la cible 2 `sftp-ecole:backups/replicas` et le même `INNOVSKOOL_REPLICA_DIR`.

---

## Restauration

**Depuis le local, sur n'importe laquelle des deux machines** (remplace la base actuelle) — `CONTAINER` est le nom du conteneur MySQL (visible avec `docker ps` ; en PowerShell : `(docker ps --format "{{.Names}} {{.Image}}" | Select-String "mysql|mariadb").ToString().Split(" ")[0]`) :
```powershell
docker cp <chemin>\innovskool-XXXX.sql.gz CONTAINER:/tmp/restore.sql.gz
docker exec -e MYSQL_PWD=<mot-de-passe> CONTAINER sh -c "gzip -cd /tmp/restore.sql.gz | mysql -uroot <nom-de-base>"
docker exec CONTAINER rm -f /tmp/restore.sql.gz
```
(utilisateur, mot de passe et nom de base sont dans le `DATABASE_URL` du `.env` ; base `innovsh` par défaut)

**Depuis le cloud** : `rclone copy secret:prod/hourly/innovskool-XXX.sql.gz .` (ou `secret:dev/hourly/…` pour les dumps de dev) puis la même procédure.

**Depuis l'autre machine** (réplication croisée) : `dir $env:USERPROFILE\backups\replicas\hourly` — l'autre machine y a poussé ses dumps ; même procédure.

> Tester une restauration de temps en temps (par exemple sur la base dev) : une sauvegarde jamais restaurée n'est qu'une promesse.

---

## Variante Linux (si l'hôte devient Linux)

`backup.sh` (sauvegarde + rétention + rclone), `lib.sh` (config partagée, identifiants depuis `.env`) et `restore.sh` (restauration avec confirmation). Premier test : `bash backup/backup.sh` (écrit dans `/var/backups/innovskool/`). Cron :

```cron
17 * * * * bash /chemin/vers/innovschool/backup/backup.sh
```

Configuration : fichier `backup/backup.conf` à côté des scripts avec `RCLONE_TARGET="secret:prod"`, `RCLONE_TARGET2="sftp-maison:backups/replicas"` et `REPLICA_DIR="/var/backups/replicas"`. Restauration : `bash backup/restore.sh /var/backups/innovskool/hourly/innovskool-XXX.sql.gz`.

---

## Notes

- **Espace disque** : un dump gzip mesuré (base dev) fait ~2 Mo ; même en tablant large pour la prod (×10), 16 par jour sur 30 jours ≈ 480 fichiers, soit environ 1 à 10 Go par machine, localement, chez l'autre machine et dans le cloud.
- **Rétention identique partout** : `rclone sync` répercute les suppressions locales — le cloud et la machine partenaire ne gardent rien au-delà de 30 jours non plus, et la sauvegarde la plus récente y est protégée de la même façon.
- **Journal** : `%USERPROFILE%\backups\innovskool\backup.log`, auto-tronqué à 1 Mo. Le journal `rclone.log` reçoit les erreurs de synchronisation.
- **Hors périmètre** : les fichiers téléversés (`public/uploads/`), les secrets et la config serveur ne sont pas couverts — dumps de la base uniquement.
- **Limites du dump SQL** : pas de reprise point-dans-le-temps à la minute (il faudrait les binlogs) ; `--single-transaction` garantit un instantané cohérent des données engagées.
- Les **scripts** sont versionnés dans `backup/` (livrés avec le dépôt) ; les **dumps**, eux, vivent hors du dépôt (`%USERPROFILE%\backups\…`) et ne sont jamais commités.
