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
# Inicio de sesión de las copias (D-45): como en inicializar.sh, la contraseña se genera si el .env no la tiene
COPIAS_PASSWORD="$(leer_env database.copias.password)"
if [[ -z "$COPIAS_PASSWORD" ]]; then
    COPIAS_PASSWORD="Sc$(head -c 18 /dev/urandom | od -An -tx1 | tr -d ' \n').9"
    if grep -qE '^[[:space:]]*database\.copias\.password[[:space:]]*=' .env; then
        sed -i -E "s|^[[:space:]]*database\.copias\.password[[:space:]]*=.*|database.copias.password = ${COPIAS_PASSWORD}|" .env
    else
        printf '\ndatabase.copias.hostname = db\ndatabase.copias.database = master\ndatabase.copias.username = sigccr_copias\ndatabase.copias.password = %s\ndatabase.copias.DBDriver = SQLSRV\ndatabase.copias.port = 1433\ndatabase.copias.encrypt = false\ndatabase.copias.DBDebug = true\n' "$COPIAS_PASSWORD" >> .env
    fi
fi
sqlcmd -C -S db -U sa -P "$MSSQL_SA_PASSWORD" -b -i docker/db/crear_base.sql \
    -v OWNER_PASSWORD="$(leer_env database.migraciones.password)" APP_PASSWORD="$(leer_env database.default.password)" COPIAS_PASSWORD="$COPIAS_PASSWORD"
echo "== Migraciones"
spark SIGCCR_DB_GRUPO=migraciones php spark migrate --all
echo "== Semillas"
spark SIGCCR_DB_GRUPO=migraciones php spark db:seed SigccrSeeder
echo "== Administrador inicial"
spark SIGCCR_DB_GRUPO=migraciones SIGCCR_ADMIN_CORREO=admin@prueba.local SIGCCR_ADMIN_NOMBRE='Iván Ezequiel' \
    SIGCCR_ADMIN_APELLIDO=Thoux SIGCCR_ADMIN_DNI=20999888 SIGCCR_ADMIN_CLAVE=Administracion-Prueba-2026 \
    php spark db:seed AdministradorInicialSeeder
mkdir -p writable/correos writable/copias && chown -R www-data:www-data writable
# Volumen de copias (D-45): en la pila de pruebas no hay scheduler y copias:procesar corre en app
mkdir -p /var/opt/sigccr/backups && chown 10001:www-data /var/opt/sigccr/backups && chmod 2775 /var/opt/sigccr/backups
echo "Base de pruebas lista."
