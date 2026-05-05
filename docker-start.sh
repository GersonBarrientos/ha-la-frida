#!/usr/bin/env bash

# Ejecutar migraciones (crea tablas de Laravel + Ha La Frida)
php artisan migrate --force

# Ejecutar seeder de datos iniciales (solo inserta si las tablas están vacías)
php artisan db:seed --class=HaLaFridaPostgresSeeder --force

# Limpiar caché y optimizar
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Iniciar Apache en primer plano
apache2-foreground
