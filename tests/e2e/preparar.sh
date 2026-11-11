#!/usr/bin/env bash
# Prepara la base de la pila de pruebas desde cero: base e inicios de sesión, las migraciones, las semillas y el
# Administrador inicial (sin consola, con las variables SIGCCR_ADMIN_*). Se ejecuta dentro del contenedor app.
set -euo pipefail
cd /var/www/html
export COMPOSER_ALLOW_SUPERUSER=1
leer_env() { grep -E "^[[:space:]]*$1[[:space:]]*=" .env | tail -n 1 | sed -E "s/^[^=]*=[[:space:]]*//; s/[[:space:]]+$//; s/^['\"]//; s/['\"]$//"; }
# spark puede terminar con código 0 aunque haya lanzado una excepción: también se revisa la salida
spark() {
    local salida
    salida="$(env "$@" 2>&1)" || { echo "$salida"; return 1; }
    echo "$salida"
    if grep -qE '^\[[A-Za-z\\]*(Exception|Error)\]' <<<"$salida"; then return 1; fi
}

[[ -d vendor ]] || composer install --no-interaction --no-progress
[[ -n "$(leer_env encryption.key)" ]] || php spark key:generate --force

for _ in $(seq 1 60); do
    sqlcmd -C -S db -U sa -P "$MSSQL_SA_PASSWORD" -Q "SELECT 1" -b -o /dev/null 2>/dev/null && break
    sleep 2
done
echo "== Base e inicios de sesión"
sqlcmd -C -S db -U sa -P "$MSSQL_SA_PASSWORD" -b -i docker/db/crear_base.sql \
    -v OWNER_PASSWORD="$(leer_env database.migraciones.password)" APP_PASSWORD="$(leer_env database.default.password)"
echo "== Migraciones"
spark SIGCCR_DB_GRUPO=migraciones php spark migrate --all
echo "== Semillas"
spark SIGCCR_DB_GRUPO=migraciones php spark db:seed SigccrSeeder
echo "== Administrador inicial"
spark SIGCCR_DB_GRUPO=migraciones SIGCCR_ADMIN_CORREO=admin@prueba.local SIGCCR_ADMIN_NOMBRE='Iván Ezequiel' \
    SIGCCR_ADMIN_APELLIDO=Thoux SIGCCR_ADMIN_DNI=20999888 SIGCCR_ADMIN_CLAVE=Administracion-Prueba-2026 \
    php spark db:seed AdministradorInicialSeeder
mkdir -p writable/correos && chown -R www-data:www-data writable
echo "Base de pruebas lista."
