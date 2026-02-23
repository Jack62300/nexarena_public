#!/usr/bin/env bash
set -e

echo "=============================="
echo "   MENU DEPLOY / CACHE"
echo "=============================="
echo "1) Clear uniquement (PHP + cache)"
echo "2) Git pull + Clear complet"
echo "=============================="
read -p "Choix : " choix

fix_permissions() {
  echo "Fix permissions var/..."
  sudo chown -R www-data:www-data var/
}

clear_and_warmup() {
  echo "Clear + Warmup cache (prod) as www-data..."
  sudo -u www-data php8.4 bin/console cache:clear --env=prod --no-debug
  sudo -u www-data php8.4 bin/console cache:warmup --env=prod --no-debug
}

restart_php() {
  echo "Restart PHP-FPM..."
  sudo systemctl restart php8.4-fpm
}

git_pull() {
  echo "Git pull..."
  git pull --rebase
}

case $choix in
  1)
    echo "Mode CLEAR uniquement"
    fix_permissions
    clear_and_warmup
    restart_php
    ;;
  2)
    echo "Mode GIT + CLEAR"
    git_pull
    fix_permissions
    clear_and_warmup
    restart_php
    ;;
  *)
    echo "Choix invalide"
    exit 1
    ;;
esac

echo "Terminé."
