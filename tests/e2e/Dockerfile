# Ejecutor de las pruebas de punta a punta: Playwright con sus navegadores y el cliente de Docker, para ejecutar
# comandos de spark dentro del contenedor app (correos simulados y procesos del Temporizador).
FROM mcr.microsoft.com/playwright:v1.49.1-noble
COPY --from=docker:27-cli /usr/local/bin/docker /usr/local/bin/docker
WORKDIR /e2e
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
# Las pruebas se montan en /e2e/src: los módulos de /e2e/node_modules se resuelven desde ahí.
WORKDIR /e2e/src
