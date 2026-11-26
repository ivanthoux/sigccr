#!/usr/bin/env bash
# Genera los PNG de los diagramas de secuencia (PlantUML 1.2025, requiere Java y Graphviz).
# El jar se obtiene del paquete npm plantuml-pipe (licencia LGPL) y se guarda fuera del repositorio.
set -euo pipefail
cd "$(dirname "$0")"
JAR="${PLANTUML_JAR:-$HOME/.cache/sigccr/plantuml.jar}"
if [ ! -f "$JAR" ]; then
  mkdir -p "$(dirname "$JAR")" && tmp=$(mktemp -d)
  (cd "$tmp" && npm pack plantuml-pipe@1.7.0 --silent >/dev/null && tar xzf plantuml-pipe-1.7.0.tgz)
  cp "$tmp/package/vendor/plantuml.jar" "$JAR" && rm -rf "$tmp"
fi
java -jar "$JAR" -tpng -o png "${@:-*.puml}"
