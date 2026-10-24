#!/usr/bin/env bash
# Inicializa y verifica la arquitectura ejecutable en un solo paso (README, pasos 4 a 6).
# Uso, con los contenedores levantados (docker compose up -d):
#     docker compose exec app bash docker/inicializar.sh
# Toma las contraseñas de sigccr_app y sigccr_owner del .env y la de sa de .env.docker, así que no hay que
# escribirlas en la consola. Se puede repetir: cada paso es idempotente. Deja la salida en inicializar.log.
set -uo pipefail
cd /var/www/html
export COMPOSER_ALLOW_SUPERUSER=1
LOG=/var/www/html/inicializar.log
: > "$LOG"
DUENO="$(stat -c '%u:%g' /var/www/html)"
declare -a RESUMEN=()

titulo() { printf '\n\033[1;34m== %s\033[0m\n' "$1" | tee -a "$LOG"; }
ok()     { RESUMEN+=("OK     $1"); }
falla()  { RESUMEN+=("FALLA  $1"); }
cerrar() {
    printf '\n== Resumen\n' | tee -a "$LOG"
    for r in "${RESUMEN[@]}"; do echo "  $r" | tee -a "$LOG"; done
    chown "$DUENO" "$LOG" 2>/dev/null || true
    chown -R www-data:www-data writable 2>/dev/null || true
    echo "Salida completa en inicializar.log (en la carpeta del proyecto)."
    exit "$1"
}
# Ejecuta un comando mostrando y registrando su salida; si falla y el paso es obligatorio, termina.
paso() {
    local nombre="$1" obligatorio="$2"; shift 2
    if "$@" 2>&1 | tee -a "$LOG"; then ok "$nombre"; return 0; fi
    falla "$nombre"
    [ "$obligatorio" = "si" ] && cerrar 1
    return 1
}
leer_env() {   # valor de una clave del .env, sin comillas ni espacios
    grep -E "^[[:space:]]*$1[[:space:]]*=" .env | tail -n 1 \
        | sed -E "s/^[^=]*=[[:space:]]*//; s/[[:space:]]+$//; s/^['\"]//; s/['\"]$//"
}

titulo "1. Carpeta writable"
mkdir -p writable/{cache,logs,session,uploads,debugbar} && chown -R www-data:www-data writable && chmod -R ug+rwX writable
echo "writable/ preparada." | tee -a "$LOG"; ok "Carpeta writable"

titulo "2. Configuración"
[ -f spark ] || { echo "Falta la estructura de CodeIgniter: ejecutar antes docker/bootstrap.sh" | tee -a "$LOG"; falla "Configuración"; cerrar 1; }
[ -f .env ]  || { echo "Falta .env: cp env.sigccr.example .env" | tee -a "$LOG"; falla "Configuración"; cerrar 1; }
APP_PASSWORD="$(leer_env database.default.password)"
OWNER_PASSWORD="$(leer_env database.migraciones.password)"
CLAVE_CIFRADO="$(leer_env encryption.key)"
error=0
[ -n "${MSSQL_SA_PASSWORD:-}" ] || { echo "MSSQL_SA_PASSWORD no está definida: revisar .env.docker" | tee -a "$LOG"; error=1; }
[ -n "$APP_PASSWORD" ]          || { echo "Falta database.default.password en .env" | tee -a "$LOG"; error=1; }
[ -n "$OWNER_PASSWORD" ]        || { echo "Falta database.migraciones.password en .env" | tee -a "$LOG"; error=1; }
[ "$APP_PASSWORD" != "$OWNER_PASSWORD" ] || { echo "Las contraseñas de default y migraciones deben ser distintas" | tee -a "$LOG"; error=1; }
case "$APP_PASSWORD$OWNER_PASSWORD" in *"'"*) echo "Las contraseñas no pueden contener comillas simples" | tee -a "$LOG"; error=1 ;; esac
if [ -z "$CLAVE_CIFRADO" ]; then
    php spark key:generate --force >> "$LOG" 2>&1 && echo "Se generó encryption.key en .env" | tee -a "$LOG"
fi
[ "$error" = 0 ] || { falla "Configuración"; cerrar 1; }
echo ".env y .env.docker completos." | tee -a "$LOG"; ok "Configuración"

titulo "3. Conexión con SQL Server"
listo=0
for _ in $(seq 1 30); do
    if sqlcmd -C -S db -U sa -P "$MSSQL_SA_PASSWORD" -Q "SELECT 1" -b -o /dev/null 2>/dev/null; then listo=1; break; fi
    sleep 2
done
if [ "$listo" = 1 ]; then echo "SQL Server responde." | tee -a "$LOG"; ok "Conexión con SQL Server"
else
    sqlcmd -C -S db -U sa -P "$MSSQL_SA_PASSWORD" -Q "SELECT 1" -b 2>&1 | tee -a "$LOG"
    echo "Si dice 'Login failed for user sa', la contraseña de .env.docker no es la del primer arranque (ver README)." | tee -a "$LOG"
    falla "Conexión con SQL Server"; cerrar 1
fi

titulo "4. Base de datos e inicios de sesión"
paso "Base e inicios de sesión" si sqlcmd -C -S db -U sa -P "$MSSQL_SA_PASSWORD" -b -i docker/db/crear_base.sql \
    -v OWNER_PASSWORD="$OWNER_PASSWORD" APP_PASSWORD="$APP_PASSWORD"

titulo "5. Esquema (migraciones)"
paso "Migraciones" si php spark migrate --all -g migraciones

titulo "6. Datos semilla"
paso "Datos semilla" si php spark db:seed SigccrSeeder -g migraciones

titulo "7. Administrador inicial"
# Es interactivo (pide los datos y la contraseña sin mostrarla), por eso no pasa por tee.
if php spark db:seed AdministradorInicialSeeder -g migraciones; then ok "Administrador inicial"; else falla "Administrador inicial"; fi

titulo "8. Verificación del entorno (R8)"
paso "Verificación del entorno" no php spark sigccr:verificar-entorno

titulo "9. Pruebas del motor financiero (RNF-15)"
paso "Pruebas del motor financiero" no composer test:finanzas

titulo "10. Punta a punta: Nginx → PHP-FPM → SQL Server"
respuesta="$(curl -sk -H 'Host: localhost' -w '\nHTTP %{http_code}' https://web/estado 2>&1)"
echo "$respuesta" | tee -a "$LOG"
case "$respuesta" in *'"conectada"'*'HTTP 200'*) ok "Punta a punta (/estado)" ;; *) falla "Punta a punta (/estado)" ;; esac

for r in "${RESUMEN[@]}"; do case "$r" in FALLA*) cerrar 1 ;; esac; done
printf '\n\033[1;32mArquitectura ejecutable verificada.\033[0m\n' | tee -a "$LOG"
cerrar 0
