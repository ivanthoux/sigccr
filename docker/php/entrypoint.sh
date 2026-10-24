#!/usr/bin/env bash
# Punto de entrada de los contenedores app y scheduler.
# writable/ es un volumen con nombre (storage): si se recrea (docker compose down -v) queda vacío, así que en cada
# arranque se crean sus subcarpetas y se le devuelve la propiedad a www-data, que es quien ejecuta PHP-FPM y cron.
set -e
W=/var/www/html/writable
mkdir -p "$W"/cache "$W"/logs "$W"/session "$W"/uploads "$W"/debugbar
chown -R www-data:www-data "$W"
chmod -R ug+rwX "$W"
exec docker-php-entrypoint "$@"
