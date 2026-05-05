#!/usr/bin/env bash

# Limpiar caché y optimizar
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Ejecutar migraciones
php artisan migrate --force

# Iniciar Apache en primer plano
apache2-foreground
