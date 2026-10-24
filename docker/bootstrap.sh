#!/usr/bin/env bash
# Prepara el proyecto CodeIgniter 4 dentro del contenedor app (actividad 6).
# Uso:  docker compose run --rm --no-deps app bash docker/bootstrap.sh
set -euo pipefail
cd /var/www/html

if [ ! -f spark ]; then
  echo ">> Descargando la estructura base de CodeIgniter 4 (appstarter)..."
  composer create-project codeigniter4/appstarter /tmp/ci4 "^4.6" --no-interaction --no-install
  # cp -n no reemplaza los archivos propios del SIG-CCR (composer.json, app/Config/Filters.php, Routes.php, etc.)
  cp -rn /tmp/ci4/. /var/www/html/
  rm -rf /tmp/ci4
fi

echo ">> Instalando dependencias..."
composer install --no-interaction

echo ">> Preparando writable/..."
mkdir -p writable/{cache,logs,session,uploads,debugbar}
chown -R www-data:www-data writable
chmod -R u+rwX,g+rwX writable

if [ ! -f .env ]; then
  cp env.sigccr.example .env
  php spark key:generate --force
  echo ">> Se creó .env a partir de env.sigccr.example: completar las contraseñas antes de migrar."
fi
# El contenedor corre como root: los archivos nuevos (vendor/, spark, app/, .env) se devuelven al dueño de la
# carpeta del proyecto en WSL, para poder editarlos desde VS Code sin sudo.
DUENO="$(stat -c '%u:%g' /var/www/html)"
find /var/www/html -path /var/www/html/writable -prune -o ! -user "${DUENO%%:*}" -exec chown -h "$DUENO" {} +
echo ">> Listo. Siguiente paso: docker compose up -d y docker compose exec app bash docker/inicializar.sh"
