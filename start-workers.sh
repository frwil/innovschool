#!/bin/sh

echo "Vérification des dépendances pour les workers Messenger..."

# Attendre que Redis soit prêt
while ! nc -z redis 6379; do
  echo "En attente de Redis..."
  sleep 2
done

echo "✓ Redis est prêt!"

# Attendre que la base de données soit prête
while ! nc -z database 3306; do
  echo "En attente de MySQL..."
  sleep 2
done

echo "✓ MySQL est prêt!"

# Créer le répertoire var si nécessaire
mkdir -p /var/www/var
chown -R www:www /var/www/var
chmod -R 755 /var/www/var

echo "✓ Répertoire var configuré"

# Lancer les workers Messenger
echo "Démarrage des workers Messenger..."
exec php /var/www/bin/console messenger:consume async --time-limit=3600 --memory-limit=4096M