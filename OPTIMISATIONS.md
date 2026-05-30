# Optimisations — innovschool

Date : 2026-05-19

## 1. Base de données

- Base renommée `innovsh` → `innovsh_v2` dans `.env`, `.env.local.php` et `compose.yaml` (`MYSQL_DATABASE`)
- 67 espaces parasites nettoyés dans `user.full_name` (leading/trailing/double spaces)
- 43 emails corrigés dans `user.email` (double/triple points → simple point)

## 2. Stabilité AJAX (saisie des notes)

5 corrections dans `src/Controller/EvaluationController.php` :

| Fichier | Ligne | Correction |
|---------|-------|------------|
| `EvaluationController.php` | ~2095 | `OperationLogger::log()` déplacé avant `commit()` pour atomicité |
| `EvaluationController.php` | ~2095 | `$entityManager` → `$this->entityManager` (fix stale EM après `resetManager()`) |
| `EvaluationController.php` | ~2160 | `usleep($retryDelay * 1000)` corrigé (était `* $attempt`, faisait 1s/2s/3s au lieu de 1s fixe) |
| `EvaluationController.php` | ~2238 | Ajout `$entityManager->flush()` après `persist()` dans `getEvaluationNotes()` (fix ghost entities) |

1 correction dans `templates/evaluation/saisie.notes.index.html.twig` :

| Fichier | Ligne | Correction |
|---------|-------|------------|
| `saisie.notes.index.html.twig` | 673 | Timeout JS AJAX : 15000 → 30000ms |

## 3. Performance Docker

### Volume nommé pour le cache

`compose.yaml` — volume nommé `var_data` pour `/var/www/var` afin d'éviter le bind mount Windows lent pour les fichiers de cache :

```yaml
volumes:
  var_data:
    driver: local

services:
  php:
    volumes:
      - var_data:/var/www/var
      - .:/var/www:delegated
```

### OPCache production

`docker/php/conf.d/memory.ini` :

```ini
opcache.validate_timestamps = 0
opcache.revalidate_freq = 0
opcache.interned_strings_buffer = 16
opcache.enable_file_override = 1
```

## 4. Correction Dockerfile

`docker/php/Dockerfile` :

- Ajout `icu-dev` et `icu-data-full` pour l'extension PHP `intl`
- `chown -R 1000:1000 /var/www/var` (UID/GID numériques au lieu de `www:www` qui n'existe pas encore à cette étape)

## 5. Résultats de performance

| Métrique | Avant | Après |
|----------|-------|-------|
| GET `/evaluation/saisie-notes` | 6-7s | 120-140ms |
| POST `/evaluation/notes/update` | 6-7s / timeout | 64-200ms |

Test de charge : 100 requêtes, 0 échec (50 séquentielles + 50 parallèles).

## 6. Maintenance

Après un déploiement ou `docker compose build`, toujours lancer :

```bash
docker compose exec php php bin/console cache:clear --no-interaction
docker compose exec php php bin/console cache:warmup --no-interaction
```

En environnement `prod` (`APP_ENV=prod`), les modifications de code source PHP nécessitent un `cache:clear` pour être prises en compte.
