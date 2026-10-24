# SIG-CCR · Arquitectura ejecutable

[![CI](https://github.com/ivanthoux/sigccr/actions/workflows/ci.yml/badge.svg?branch=develop)](https://github.com/ivanthoux/sigccr/actions/workflows/ci.yml)

Sistema Integral de Gestión de Créditos, Cobranzas y Refinanciación. Este repositorio contiene la **arquitectura ejecutable** de la actividad 6 del cronograma (hito de fin de Elaboración, 27/10/2026): entorno Docker, esquema de la base de datos, componentes base y motor financiero con sus pruebas.

Stack: PHP 8.3 · CodeIgniter 4 (4.6 o posterior; el appstarter instala la última 4.x) · SQL Server 2022 · Bootstrap 5 · Docker (WSL 2 en desarrollo, Docker Engine en producción).

## Contenido

| Ruta | Contenido | Referencia |
|---|---|---|
| `docker-compose.yml`, `docker/` | Contenedores web (Nginx), app (PHP-FPM), scheduler (cron) y db (SQL Server) | 4.4.5, RNF-04 |
| `app/Libraries/Finanzas/` | Motor financiero: decimal BCMath, amortización francesa, calendario de cuotas, punitorios, TEA y CFTEA, capacidad de pago, imputación, cancelación anticipada, refinanciación, modelo de evaluación, horas hábiles | RN-29, RN-34 a RN-35, RN-49 a RN-53, RN-63, RN-67 a RN-70, RNF-15 |
| `tests/unit/Finanzas/` | 52 pruebas con los casos de referencia | RNF-15 |
| `tests/referencia/` | Cálculo independiente en Python de los valores esperados y catálogo de casos | Plan preventivo de R3 |
| `app/Database/Migrations/`, `app/Database/Sql/` | Una migración por subsistema (70 tablas), generadas desde la especificación del modelo de datos | 4.3 |
| `app/Database/Seeds/` | Roles, tipos de documentación, parámetros iniciales y Administrador inicial | 3.5.1 |
| `app/Services/` | Servicio base transaccional (transacciones, UPDLOCK, `version_fila`), auditoría, alertas, parámetros versionados | 4.4.2, RN-24, RN-45, RN-46 |
| `app/Commands/` | Comando base de procesos programados (`sp_getapplock`, `ejecuciones_proceso`, transacción por elemento, fechas omitidas) y `sigccr:verificar-entorno` | RNF-10, R8 |
| `app/Filters/`, `app/Config/Filters.php` | Sesión, permisos por ruta, límite de intentos por IP, mantenimiento del portal, HTTPS y CSRF | RNF-01, RNF-11, RNF-12, RN-62 |
| `app/Adapters/` | Interfaces de integración y simuladores (BCRA, pasarela, correo) | RNF-05, RNF-08, RNF-09 |

## Instalación en desarrollo (Windows + WSL 2)

Requisitos: Docker Desktop con integración WSL 2, y el repositorio clonado **dentro del sistema de archivos de WSL** (por ejemplo `~/proyectos/sigccr`), no en `/mnt/c`, para que el montaje sea rápido.

```bash
# 1. Variables de los contenedores y certificado de desarrollo
cp .env.docker.example .env.docker          # editar MSSQL_SA_PASSWORD (sin $, comillas ni espacios)
sh docker/nginx/generar-certificado.sh

# 2. Construir la imagen de PHP (incluye msodbcsql18, sqlsrv, pdo_sqlsrv y bcmath)
docker compose build

# 3. Estructura de CodeIgniter 4, dependencias y .env
docker compose run --rm --no-deps app bash docker/bootstrap.sh
#    editar .env: database.default.password (sigccr_app) y database.migraciones.password (sigccr_owner)

# 4. Levantar los servicios
docker compose up -d
docker compose ps                           # esperar a que db figure como healthy

# 5. Inicializar y verificar todo en un paso: base e inicios de sesión, migraciones, datos semilla,
#    Administrador inicial (pide sus datos), verificar-entorno, pruebas del motor financiero y /estado
docker compose exec app bash docker/inicializar.sh
```

`inicializar.sh` toma las contraseñas del `.env` y de `.env.docker`, se puede repetir sin efectos adversos y deja su salida en `inicializar.log`. Los pasos sueltos, por si hace falta repetir alguno:

```bash
docker compose exec app composer migrar      # SIGCCR_DB_GRUPO=migraciones php spark migrate --all
docker compose exec app composer semillas    # SIGCCR_DB_GRUPO=migraciones php spark db:seed SigccrSeeder
docker compose exec app bash -c 'SIGCCR_DB_GRUPO=migraciones php spark db:seed AdministradorInicialSeeder'
docker compose exec app php spark sigccr:verificar-entorno
docker compose exec app composer test:finanzas
docker compose exec app composer cobertura   # informe en build/cobertura/index.html
curl -k https://localhost/estado
```

Las migraciones y los seeders se ejecutan con el propietario del esquema (`SIGCCR_DB_GRUPO=migraciones`): CodeIgniter guarda el historial de migraciones con el grupo por defecto y `sigccr_app` no tiene permiso para crear tablas.

**Panel administrativo:** `https://localhost/admin/login`, con el correo y la contraseña del Administrador inicial. En desarrollo los correos (por ejemplo, el enlace de recuperación de acceso) no se envían: el simulador los guarda en `writable/correos/` dentro del contenedor (`docker compose exec app ls writable/correos`).

**Problemas frecuentes**

- *db unhealthy* y en `docker compose logs db` aparece `Login failed for user 'sa'`: la contraseña de `.env.docker` cambió después del primer arranque y el volumen conserva la original. Con la base vacía: `docker compose down -v && docker compose up -d`.
- *No tests executed!* con `Configuration: phpunit.dist.xml`: es el archivo del appstarter; borrarlo (`rm phpunit.dist.xml`). Los scripts de Composer ya indican `-c phpunit.xml.dist`.
- *Cache unable to write to writable/cache*: el volumen de `writable/` se recreó vacío. Desde esta versión el contenedor lo prepara al arrancar: `docker compose up -d --force-recreate app scheduler`.

Para producción se usan las mismas imágenes; cambian `.env.docker` (`MSSQL_PID=Express`), `.env` (`CI_ENVIRONMENT = production`, credenciales y adaptadores reales) y el certificado de Nginx (RNF-04).

## Criterios del hito del 27/10 (2.2.2)

| Criterio | Cómo se verifica |
|---|---|
| Contenedores en funcionamiento | `docker compose ps`: web, app, scheduler y db en estado *running* / *healthy* |
| Conexión de CodeIgniter con SQL Server, transacciones, DECIMAL y datetimeoffset (R8) | `php spark sigccr:verificar-entorno` sin fallas |
| Esquema completo | `php spark migrate:status -g migraciones`: 9 migraciones; el verificador informa 70 tablas |
| Motor financiero validado por sus pruebas | `composer test:finanzas` en verde y `composer cobertura` con al menos 90 % de líneas (RNF-15) |
| Punta a punta navegador → Nginx → PHP → SQL Server | `https://localhost/estado` responde 200 con `"base_de_datos": "conectada"` |

## Pruebas del motor financiero

```bash
composer test:finanzas                 # PHPUnit (en el contenedor)
python3 tests/referencia/casos_referencia.py   # recalcula los valores esperados de forma independiente
php tests/_local/run.php               # ejecutor mínimo sin Composer (usa un reemplazo de BCMath en PHP puro)
```

`tests/_local/` existe solo para ejecutar las pruebas en un entorno sin Composer ni BCMath; en el proyecto se usa PHPUnit.

## Conexión cifrada con SQL Server

El controlador ODBC 18 exige un certificado de confianza para cifrar la conexión. En desarrollo la conexión viaja por la red interna de Docker sin cifrar (`database.default.encrypt = false`). Para cifrarla: configurar un certificado TLS en SQL Server (`mssql-conf set network.tlscert/tlskey`), agregar su autoridad a la imagen `app` (`/usr/local/share/ca-certificates` + `update-ca-certificates`) y poner `encrypt = true`.

## Seguimiento del proyecto

- Tareas: [issues](https://github.com/ivanthoux/sigccr/issues), agrupadas en un [hito por fase](https://github.com/ivanthoux/sigccr/milestones) del cronograma (2.2).
- Flujo de trabajo, ramas y versiones: [CONTRIBUTING.md](CONTRIBUTING.md).
- Decisiones de diseño: [docs/decisiones.md](docs/decisiones.md).
- Cambios por versión: [CHANGELOG.md](CHANGELOG.md).

## Convenciones

- PSR-12 (`composer cs`) y PHPStan nivel 6 (`composer stan`), RNF-05.
- Importes siempre como cadenas o `Decimal`, nunca `float` (RNF-15). El controlador `sqlsrv` devuelve los decimales como texto y puede omitir el cero inicial (`.5`); `Decimal::of()` lo admite.
- Instantes en UTC (`DATETIMEOFFSET`), fechas de negocio en America/Argentina/Buenos_Aires (3.5.1).
- Los controladores no contienen reglas de negocio: llaman a un método de servicio por operación (RNF-05).
