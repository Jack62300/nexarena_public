#!/bin/bash

PROJECT_DIR="/var/www/nexarena"
USER="jack"
WEB_USER="www-data"

echo "➡️ Installation ACL si nécessaire"
apt update -y
apt install acl -y

echo "➡️ Vérification du dossier projet"
if [ ! -d "$PROJECT_DIR" ]; then
  echo "❌ Le dossier $PROJECT_DIR n'existe pas"
  exit 1
fi

echo "➡️ Attribution du propriétaire"
chown -R $USER:$WEB_USER $PROJECT_DIR

echo "➡️ Permissions Linux de base"
find $PROJECT_DIR -type d -exec chmod 755 {} \;
find $PROJECT_DIR -type f -exec chmod 644 {} \;

cd $PROJECT_DIR || exit

echo "➡️ Droits d'écriture Symfony sur var/"
setfacl -R -m u:$WEB_USER:rwX -m u:$USER:rwX var
setfacl -dR -m u:$WEB_USER:rwX -m u:$USER:rwX var

echo "➡️ Accès lecture public/"
setfacl -R -m u:$WEB_USER:rX public
setfacl -dR -m u:$WEB_USER:rX public

echo "➡️ Accès lecture vendor/"
setfacl -R -m u:$WEB_USER:rX vendor
setfacl -dR -m u:$WEB_USER:rX vendor

echo "➡️ Accès traversal racine projet"
setfacl -R -m u:$WEB_USER:rX .
setfacl -dR -m u:$WEB_USER:rX .

echo "➡️ Redémarrage PHP-FPM et Nginx"
systemctl restart php8.3-fpm
systemctl restart nginx

echo "✅ Permissions Symfony configurées avec succès !"