#!/usr/bin/env bash
set -e

clear
echo "=============================="
echo "   MENU DEPLOY / CACHE"
echo "=============================="
echo "1) Clear uniquement (PHP + cache)"
echo "2) Git pull + Clear complet"
echo "=============================="
read -p "Choix : " choix

restart_php() {
  echo "Restart PHP-FPM..."
  sudo systemctl restart php8.4-fpm
}

warmup_cache() {
  echo "Warmup cache (prod)..."
  php bin/console cache:warmup --env=prod
}

clear_cache() {
  echo "Clear cache (prod)..."
  php bin/console cache:clear --env=prod
}

git_pull() {
  echo "Git pull..."
  git pull
}

case $choix in
  1)
    echo "Mode CLEAR uniquement"
    restart_php
    warmup_cache
    clear_cache
    ;;
  2)
    echo "Mode GIT + CLEAR"
    git_pull
    restart_php
    warmup_cache
    clear_cache
    ;;
  *)
    echo "Choix invalide"
    exit 1
    ;;
esac

echo "Terminé."