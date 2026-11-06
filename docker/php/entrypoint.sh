#!/usr/bin/env bash
# Punto de entrada de los contenedores app y scheduler.
# writable/ es un volumen con nombre (storage): si se recrea (docker compose down -v) queda vacío, así que en cada
# arranque se crean sus subcarpetas y se le devuelve la propiedad a www-data, que es quien ejecuta PHP-FPM y cron.
set -e
W=/var/www/html/writable
mkdir -p "$W"/cache "$W"/logs "$W"/session "$W"/uploads "$W"/debugbar
chown -R www-data:www-data "$W"
chmod -R ug+rwX "$W"
# El scheduler copia la tabla de cron del repositorio en cada arranque, en lugar de montarla como archivo: un
# montaje de un solo archivo queda atado al archivo original y, cuando git pull lo reemplaza, Docker Desktop ya no
# puede volver a montarlo ("error mounting ... /etc/cron.d/sigccr: no such file or directory").
if [ "${1:-}" = "cron" ]; then
    install -m 0644 -o root -g root /var/www/html/docker/scheduler/crontab /etc/cron.d/sigccr
fi
exec docker-php-entrypoint "$@"
