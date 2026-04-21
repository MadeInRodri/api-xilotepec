#!/bin/bash

php artisan package:discover --ansi
php artisan config:cache
# Asegurar que el archivo SQLite exista
touch /var/www/html/database/database.sqlite

# Ejecutar las migraciones automáticamente (forzadas porque estamos en "producción" dentro del contenedor)
php artisan migrate --force

# Iniciar Apache en primer plano para mantener el contenedor vivo
exec apache2-foreground