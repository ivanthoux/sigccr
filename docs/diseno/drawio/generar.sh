#!/usr/bin/env bash
# Exporta cada página de los .drawio de fuentes/ a svg/<archivo>/ y png/<archivo>/ (PNG a unos 400 ppp para el documento).
# Requiere Node con Playwright (Chromium) y Python 3. Descarga mxGraph 4.2.2 (Apache 2.0) la primera vez.
set -euo pipefail
cd "$(dirname "$0")"
CACHE="${HOME}/.cache/sigccr"; MX="$CACHE/mxgraph/package/javascript/mxClient.min.js"
if [[ ! -f "$MX" ]]; then
  mkdir -p "$CACHE/mxgraph"; (cd "$CACHE/mxgraph" && npm pack mxgraph@4.2.2 >/dev/null && tar xzf mxgraph-4.2.2.tgz)
fi
export MXCLIENT="$MX"
for f in fuentes/*.drawio; do
  base="$(basename "$f" .drawio)"; mkdir -p salida; tmp="salida/$base.json"
  python3 herramientas/paginas.py "$f" > "$tmp"
  node herramientas/render.js "$tmp" "salida/$base"
  mkdir -p "svg/$base" "png/$base"; mv "salida/$base"/*.svg "svg/$base/"; mv "salida/$base"/*.png "png/$base/"
  rm -rf "salida/$base" "$tmp"
done
rmdir salida 2>/dev/null || true
