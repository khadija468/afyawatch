#!/usr/bin/env bash
# Update a running AfyaWatch install after `git pull`. Run from /var/www/afyawatch as the deploy user.
set -euo pipefail

php artisan down --retry=30
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
