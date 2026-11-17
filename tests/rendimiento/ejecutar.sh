#!/usr/bin/env bash
# Pruebas de rendimiento del SIG-CCR (RNF-03, RNF-06, RNF-07 y RNF-10), desde la carpeta del proyecto:
#     bash tests/rendimiento/ejecutar.sh
# Variables: JMETER (ruta del ejecutable, por omisión jmeter), RENDIMIENTO_DURACION (segundos de la carga mixta, 600).
#
# 1. Levanta la pila de pruebas y carga los datos base con la batería de punta a punta (especificaciones 01 a 16).
# 2. Limita los contenedores a 4 vCPU y 8 GB en total, el servidor de referencia de RNF-03.
# 3. Carga el volumen de referencia (RendimientoSeeder) y exporta los clientes para JMeter.
# 4. Ejecuta la carga mixta (carga.jmx), la concurrencia (concurrencia.jmx) y mide los procesos automáticos.
# 5. Evalúa los resultados (evaluar.py): tests/rendimiento/resultados/resumen.md e informe HTML de JMeter.
set -uo pipefail
cd "$(dirname "$0")/../.."
DIR=tests/rendimiento
RES="$DIR/resultados"
JMETER="${JMETER:-jmeter}"
DURACION="${RENDIMIENTO_DURACION:-600}"
export COMPOSE_PROJECT_NAME=sigccr_e2e
# Puertos estándar: las redirecciones de la aplicación usan app.baseURL (https://localhost/), sin puerto
export E2E_HTTP_PORT="${E2E_HTTP_PORT:-80}" E2E_HTTPS_PORT="${E2E_HTTPS_PORT:-443}"
dc() { docker compose -f docker-compose.yml -f tests/e2e/docker-compose.e2e.yml "$@"; }
sql() {
  local consulta="$1"
  dc exec -T app sh -c 'sqlcmd -C -S db -U sa -P "$MSSQL_SA_PASSWORD" -d sigccr -b -h -1 -W -s "," -Q "SET NOCOUNT ON; $1"' sh "$consulta"
}

rm -rf "$RES" && mkdir -p "$RES"
CLAVE="Rend-$(head -c 12 /dev/urandom | od -An -tx1 | tr -d ' \n')-2026"

echo "== 1. Pila y datos base (batería de punta a punta 01 a 16)"
E2E_CONSERVAR=1 bash tests/e2e/ejecutar.sh "0[0-9]-" "1[0-6]-" > "$RES/preparacion_e2e.log" 2>&1
echo "Batería de preparación: código $? (el detalle queda en preparacion_e2e.log)"
dc ps --status running --services | grep -qx app || { echo "La pila no quedó levantada"; tail -50 "$RES/preparacion_e2e.log"; exit 1; }

echo "== 2. Recursos del servidor de referencia: 4 vCPU y 8 GB"
docker update --cpus 2 --memory 4g --memory-swap 4g "$(dc ps -q db)" >/dev/null
docker update --cpus 1.5 --memory 3g --memory-swap 3g "$(dc ps -q app)" >/dev/null
docker update --cpus 0.5 --memory 1g --memory-swap 1g "$(dc ps -q web)" >/dev/null
docker inspect --format '{{.Name}}: {{.HostConfig.NanoCpus}} nanoCPU, {{.HostConfig.Memory}} bytes' $(dc ps -q db app web) | tee "$RES/recursos.txt"
nproc | sed 's/^/CPU del equipo de prueba: /' | tee -a "$RES/recursos.txt"

# Se mide como en producción: sin la barra de depuración ni el registro detallado del entorno de desarrollo
sed -i 's/^CI_ENVIRONMENT = .*/CI_ENVIRONMENT = production/' .env
grep '^CI_ENVIRONMENT' .env | tee -a "$RES/recursos.txt"

echo "== 3. Volumen de referencia"
SECONDS=0
dc exec -T -e RENDIMIENTO_CLAVE="$CLAVE" -e SIGCCR_DB_GRUPO=migraciones app php spark db:seed RendimientoSeeder | tee "$RES/volumen.txt" || exit 1
echo "Carga del volumen: ${SECONDS} s" | tee -a "$RES/volumen.txt"
sql "SELECT (SELECT COUNT(*) FROM dbo.clientes), (SELECT COUNT(*) FROM dbo.creditos), (SELECT COUNT(*) FROM dbo.cuotas)" \
  | sed 's/^/Totales de la base (clientes, créditos, cuotas): /' | tee -a "$RES/volumen.txt"
RENDIMIENTO="c.correo LIKE '%@rendimiento.local'"
# Cliente reservado para la concurrencia: tiene cuotas a cobrar y no figura en la lista de los Cajeros
CONC_CLIENTE=$(sql "SELECT TOP 1 c.id FROM dbo.clientes c WHERE $RENDIMIENTO AND EXISTS (SELECT 1 FROM dbo.creditos cr JOIN dbo.cuotas q ON q.credito_id = cr.id WHERE cr.cliente_id = c.id AND q.estado = 'Vencida') ORDER BY c.id DESC" | tr -d '\r ')
sql "SELECT c.id, ph.nro_documento, c.cuit_cuil, ph.apellido FROM dbo.clientes c JOIN dbo.personas_humanas ph ON ph.cliente_id = c.id WHERE $RENDIMIENTO ORDER BY NEWID()" \
  | tr -d '\r' > "$RES/clientes.csv"
sql "SELECT c.id, ph.nro_documento, c.cuit_cuil, CONCAT(ph.apellido, ' ', ph.nombre) FROM dbo.clientes c JOIN dbo.personas_humanas ph ON ph.cliente_id = c.id WHERE $RENDIMIENTO AND c.id <> $CONC_CLIENTE AND EXISTS (SELECT 1 FROM dbo.creditos cr JOIN dbo.cuotas q ON q.credito_id = cr.id WHERE cr.cliente_id = c.id AND q.estado IN ('Pendiente', 'Vencida')) ORDER BY NEWID()" \
  | tr -d '\r' > "$RES/cobros.csv"
wc -l "$RES/clientes.csv" "$RES/cobros.csv"

dc exec -T app php spark cache:clear >/dev/null  # límite de intentos de inicio de sesión por dirección (RN-62)
echo "== 4. Carga mixta: 10 usuarios durante ${DURACION} s (RP-01 a RP-05)"
( while true; do docker stats --no-stream --format '{{.Name}},{{.CPUPerc}},{{.MemUsage}}' $(dc ps -q db app web) | sed "s/^/$(date +%T),/"; sleep 15; done ) > "$RES/recursos_durante_la_carga.csv" &
MONITOR=$!
"$JMETER" -n -t "$DIR/carga.jmx" -l "$RES/carga.jtl" -j "$RES/jmeter_carga.log" -e -o "$RES/informe-carga" \
  -Jclave="$CLAVE" -Jdatos="$RES" -Jduracion="$DURACION" -Jhost=localhost -Jpuerto="$E2E_HTTPS_PORT" | tail -15
kill "$MONITOR" 2>/dev/null

echo "== 5. Decisiones simultáneas (RP-06)"
CONC_COBRO=$(sql "SELECT TOP 1 id FROM dbo.cobros WHERE estado = 'Confirmado' AND canal = 'Ventanilla' ORDER BY id DESC" | tr -d '\r ')
"$JMETER" -n -t "$DIR/concurrencia.jmx" -l "$RES/concurrencia.jtl" -j "$RES/jmeter_concurrencia.log" \
  -Jclave="$CLAVE" -Jhost=localhost -Jpuerto="$E2E_HTTPS_PORT" -Jcliente_concurrencia="$CONC_CLIENTE" -Jcobro_concurrencia="$CONC_COBRO" | tail -5
{
  echo "cliente=$CONC_CLIENTE"
  echo "cobros_confirmados_del_cliente=$(sql "SELECT COUNT(*) FROM dbo.cobros WHERE cliente_id = $CONC_CLIENTE AND estado = 'Confirmado'" | tr -d '\r ')"
  echo "cobro=$CONC_COBRO"
  echo "estado_del_cobro=$(sql "SELECT estado FROM dbo.cobros WHERE id = $CONC_COBRO" | tr -d '\r ')"
  echo "anulaciones_del_cobro=$(sql "SELECT COUNT(*) FROM dbo.anulaciones_cobro WHERE cobro_id = $CONC_COBRO" | tr -d '\r ')"
  echo "saldos_a_favor_de_la_anulacion=$(sql "SELECT COUNT(*) FROM dbo.saldos_a_favor s JOIN dbo.anulaciones_cobro a ON a.saldo_a_favor_id = s.id WHERE a.cobro_id = $CONC_COBRO" | tr -d '\r ')"
} | tee "$RES/concurrencia.txt"

echo "== 6. Procesos automáticos con el volumen completo (RP-07)"
# El cálculo de punitorios procesa cada día una sola vez (RN-53): se olvida la ejecución de hoy para medirlo
# (procesa el día anterior, D-33): se olvida la de ayer para que recorra todas las cuotas vencidas del volumen
sql "DELETE FROM dbo.ejecuciones_proceso WHERE proceso = 'cartera:punitorios' AND fecha_referencia >= DATEADD(day, -1, CAST(SYSDATETIMEOFFSET() AT TIME ZONE 'Argentina Standard Time' AS date))" >/dev/null
: > "$RES/procesos.txt"
for p in cartera:punitorios fondos:proyectar vencimientos:controlar evaluacion:ejecutar; do
  inicio=$(date +%s.%N)
  dc exec -T app php spark "$p" > "$RES/proceso_${p/:/_}.log" 2>&1
  rc=$?
  echo "$p,$(awk -v i="$inicio" -v f="$(date +%s.%N)" 'BEGIN { printf "%.1f", f - i }'),$rc" | tee -a "$RES/procesos.txt"
done
sql "SELECT COUNT(*) FROM dbo.cuotas WHERE punitorio_pendiente > 0" | sed 's/^/Cuotas con punitorios después del cálculo: /' | tee -a "$RES/procesos.txt"

dc exec -T app sh -c 'cat writable/logs/log-*.log 2>/dev/null' > "$RES/aplicacion.log"
grep -c "^CRITICAL\|^ERROR" "$RES/aplicacion.log" | sed 's/^/Errores en el registro de la aplicación: /'
sed -i 's/^CI_ENVIRONMENT = .*/CI_ENVIRONMENT = development/' .env

echo "== 7. Evaluación"
python3 "$DIR/evaluar.py" "$RES"
rc=$?
if [[ -z "${E2E_CONSERVAR:-}" ]]; then dc --profile pruebas down -v >/dev/null 2>&1; fi
exit $rc
