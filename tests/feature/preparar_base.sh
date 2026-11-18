#!/usr/bin/env bash
# Prepara la base de las pruebas de integración desde cero (D-49). Lo ejecuta «composer test:integracion» antes de
# PHPUnit; también se puede ejecutar solo:
#     docker compose exec app bash tests/feature/preparar_base.sh
#
# 1. Borra y crea sigccr_pruebas (docker/db/crear_base_pruebas.sql), con el usuario sa.
# 2. Aplica todas las migraciones y carga los datos semilla con el propietario del esquema, en el entorno de pruebas:
#    Config\Database dirige todos los grupos a sigccr_pruebas, así que la base de desarrollo no se toca.
#
# Variables: MSSQL_SA_PASSWORD (obligatoria; en Docker la define .env.docker), SIGCCR_DB_HOST (por defecto db) y
# SQLCMD (por defecto sqlcmd). En la integración continua, SIGCCR_CREAR_INICIOS=1 crea antes los inicios de sesión con
# docker/db/crear_base.sql, tomando OWNER_PASSWORD, APP_PASSWORD y COPIAS_PASSWORD del entorno.
set -euo pipefail
cd "$(dirname "$0")/../.."

HOST="${SIGCCR_DB_HOST:-db}"
SQLCMD="${SQLCMD:-sqlcmd}"
: "${MSSQL_SA_PASSWORD:?Falta MSSQL_SA_PASSWORD (en Docker la define .env.docker)}"

echo "== Base de pruebas: sigccr_pruebas en ${HOST}"
if [[ "${SIGCCR_CREAR_INICIOS:-0}" = "1" ]]; then
    "$SQLCMD" -C -S "$HOST" -U sa -P "$MSSQL_SA_PASSWORD" -b -i docker/db/crear_base.sql \
        -v OWNER_PASSWORD="$OWNER_PASSWORD" APP_PASSWORD="$APP_PASSWORD" COPIAS_PASSWORD="${COPIAS_PASSWORD:-$OWNER_PASSWORD}"
fi
"$SQLCMD" -C -S "$HOST" -U sa -P "$MSSQL_SA_PASSWORD" -b -i docker/db/crear_base_pruebas.sql

# spark puede terminar con código 0 aunque haya lanzado una excepción: también se revisa la salida
ejecutar() {
    local salida
    salida="$(env CI_ENVIRONMENT=testing SIGCCR_DB_GRUPO=migraciones "$@" 2>&1)" || { echo "$salida"; exit 1; }
    echo "$salida"
    if grep -qE '^\[[A-Za-z\\]*Exception\]' <<<"$salida"; then
        echo "Falló: $*" >&2
        exit 1
    fi
}
echo "== Migraciones"
ejecutar php spark migrate --all
echo "== Datos semilla"
ejecutar php spark db:seed SigccrSeeder

tablas="$("$SQLCMD" -C -S "$HOST" -U sa -P "$MSSQL_SA_PASSWORD" -d sigccr_pruebas -h -1 -W -b \
    -Q "SET NOCOUNT ON; SELECT COUNT(*) FROM sys.tables" | tr -d '[:space:]')"
echo "== sigccr_pruebas lista: ${tablas} tablas."
