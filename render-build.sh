#!/usr/bin/env bash
# Exit on error
set -o errexit

# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Install Node dependencies and build Vite assets
npm install
npm run build

# Run database migrations
php artisan migrate --force

# Clear and cache configurations
php artisan config:cache
php artisan route:cache
php artisan view:cache
