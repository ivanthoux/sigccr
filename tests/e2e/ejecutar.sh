#!/usr/bin/env bash
# Pruebas de punta a punta del SIG-CCR con un solo comando, desde la carpeta del proyecto:
#     bash tests/e2e/ejecutar.sh                  todas las pruebas
#     bash tests/e2e/ejecutar.sh --grep R-01      solo las que coinciden (cualquier opción de Playwright)
#     E2E_CONSERVAR=1 bash tests/e2e/ejecutar.sh  deja la pila levantada al terminar (para revisar a mano)
# Levanta una pila aparte (proyecto sigccr_e2e, puertos 18080 y 18443) con la base vacía, aplica las migraciones y
# las semillas, ejecuta Playwright y deja el informe en tests/e2e/informe/index.html. No toca el entorno de desarrollo.
set -uo pipefail
cd "$(dirname "$0")/../.."
export COMPOSE_PROJECT_NAME=sigccr_e2e HTTP_PORT="${E2E_HTTP_PORT:-18080}" HTTPS_PORT="${E2E_HTTPS_PORT:-18443}"
dc() { docker compose -f docker-compose.yml -f tests/e2e/docker-compose.e2e.yml ${E2E_COMPOSE_EXTRA:+-f "$E2E_COMPOSE_EXTRA"} "$@"; }

[[ -f .env ]] || cp env.sigccr.example .env
# La batería verifica los correos con el simulador (writable/correos y sus direcciones de falla). Si el entorno de
# desarrollo envía por SMTP a Mailpit (D-49), se usa el simulador mientras dura la ejecución y después se restaura.
if grep -qE '^[[:space:]]*sigccr\.adaptadorComunicaciones[[:space:]]*=[[:space:]]*real' .env; then
  sed -i -E 's/^([[:space:]]*sigccr\.adaptadorComunicaciones[[:space:]]*=[[:space:]]*)real/\1simulador/' .env
  trap "sed -i -E 's/^([[:space:]]*sigccr\\.adaptadorComunicaciones[[:space:]]*=[[:space:]]*)simulador/\\1real/' .env" EXIT
  echo "Correo: se usa el simulador durante las pruebas (el .env vuelve a SMTP al terminar)"
fi
[[ -f .env.docker ]] || cp .env.docker.example .env.docker
mkdir -p docker/nginx/certs
[[ -f docker/nginx/certs/sigccr.crt ]] || sh docker/nginx/generar-certificado.sh || exit 1

echo "== Pila de pruebas desde cero"
dc --profile pruebas down -v --remove-orphans >/dev/null 2>&1 || true
dc up -d --build --wait db app web || { dc logs --tail 80; exit 1; }
dc --profile pruebas build playwright || exit 1
dc exec -T app bash tests/e2e/preparar.sh || { echo "Falló la preparación de la base"; dc logs --tail 80 app db; exit 1; }

echo "== Playwright"
dc --profile pruebas run --rm playwright sh -c 'npx playwright test "$@"; rc=$?; chown -R "$(stat -c %u:%g /e2e/src)" /e2e/src/informe /e2e/src/resultados /e2e/src/capturas 2>/dev/null; exit $rc' -- "$@"
rc=$?

if [[ -z "${E2E_CONSERVAR:-}" ]]; then dc --profile pruebas down -v >/dev/null 2>&1; fi
echo "Informe: tests/e2e/informe/index.html"
exit $rc
