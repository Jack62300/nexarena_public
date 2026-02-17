#!/usr/bin/env bash

set -e  # stop si une commande échoue

echo "Restart PHP-FPM..."
sudo systemctl restart php8.4-fpm

echo "Warmup cache (prod)..."
php bin/console cache:warmup --env=prod

echo "Clear cache (prod)..."
php bin/console cache:clear --env=prod

echo "Terminé."