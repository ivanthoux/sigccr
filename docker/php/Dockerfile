# Imagen única de PHP para los contenedores app y scheduler (plan preventivo de R8).
FROM php:8.3-fpm-bookworm

ARG DEBIAN_FRONTEND=noninteractive

# Dependencias del sistema, cron y repositorio de Microsoft para ODBC Driver 18 y sqlcmd
# Acquire::Retries y --retry: un servidor de paquetes que responde 503 un momento no debe romper la construcción (D-62)
RUN echo 'Acquire::Retries "5";' > /etc/apt/apt.conf.d/80-reintentos \
    && apt-get update && apt-get install -y --no-install-recommends \
        gnupg2 curl ca-certificates apt-transport-https unzip git cron \
        libicu-dev libzip-dev unixodbc-dev \
    && curl --proto =https --tlsv1.2 --retry 5 --retry-all-errors --retry-delay 5 -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && echo "deb [arch=amd64,arm64 signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" \
        > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18 mssql-tools18 \
    && rm -rf /var/lib/apt/lists/*

ENV PATH="${PATH}:/opt/mssql-tools18/bin"

# Extensiones: bcmath (RNF-15), intl (formatos locales, RNF-02), zip, opcache y los controladores de SQL Server
# pcntl (señales en los comandos de spark) y pcov (cobertura de las pruebas, RNF-15; desactivado salvo en composer cobertura)
RUN docker-php-ext-install bcmath intl zip opcache pcntl \
    && pecl install sqlsrv pdo_sqlsrv pcov \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv pcov

# El código se monta desde WSL con otro dueño: git (que Composer consulta) lo acepta igual
RUN git config --system --add safe.directory /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-sigccr.ini

WORKDIR /var/www/html
