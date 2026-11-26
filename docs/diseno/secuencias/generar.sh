#!/usr/bin/env bash
# Genera los diagramas de secuencia (PlantUML 1.2025, requiere Java y Graphviz):
#   svg/  vectoriales, para insertar en el documento
#   png/  a 200 ppp, para vistas previas
# El jar se obtiene del paquete npm plantuml-pipe (licencia LGPL) y se guarda fuera del repositorio.
set -euo pipefail
cd "$(dirname "$0")"
JAR="${PLANTUML_JAR:-$HOME/.cache/sigccr/plantuml.jar}"
if [ ! -f "$JAR" ]; then
  mkdir -p "$(dirname "$JAR")" && tmp=$(mktemp -d)
  (cd "$tmp" && npm pack plantuml-pipe@1.7.0 --silent >/dev/null && tar xzf plantuml-pipe-1.7.0.tgz)
  cp "$tmp/package/vendor/plantuml.jar" "$JAR" && rm -rf "$tmp"
fi
archivos=("${@:-}")
[ -z "${archivos[0]}" ] && archivos=(*.puml)
java -DPLANTUML_LIMIT_SIZE=16384 -jar "$JAR" -tsvg -o svg "${archivos[@]}"
java -DPLANTUML_LIMIT_SIZE=16384 -jar "$JAR" -tpng -Sdpi=200 -o png "${archivos[@]}"
