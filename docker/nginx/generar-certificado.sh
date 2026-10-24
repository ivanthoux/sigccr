#!/usr/bin/env sh
# Certificado autofirmado para desarrollo. En producción, reemplazar por el certificado de la entidad.
set -e
cd "$(dirname "$0")/certs"
openssl req -x509 -nodes -newkey rsa:2048 -days 825 \
  -keyout sigccr.key -out sigccr.crt \
  -subj "/CN=localhost" -addext "subjectAltName=DNS:localhost,IP:127.0.0.1"
echo "Certificado generado en docker/nginx/certs"
