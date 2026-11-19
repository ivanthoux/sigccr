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
#    editar .env: database.default.password (sigccr_app) y database.migraciones.password (sigccr_owner);
#    database.copias.password (sigccr_copias, copias de seguridad) la genera inicializar.sh si queda vacía

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

**Copias de seguridad (CU-61 a CU-63, D-45):** las genera y restaura el contenedor `scheduler` con el inicio de sesión `sigccr_copias` (BACKUP en la base y `dbcreator`), que crea `inicializar.sh`. El panel solo las solicita y las descarga, porque monta el volumen `backups` de solo lectura. La copia automática corre a las 02:00 (`copias:generar`) y las pedidas desde el panel, cada minuto (`copias:procesar`). Las copias se cifran con una clave derivada de `encryption.key`: **sin esa clave no se pueden descifrar**, así que hay que resguardarla en un lugar seguro, separada de las copias que se llevan fuera del servidor. Una copia descargada se descifra con `php spark copias:descifrar <copia.sccr> <destino.zip>`.

**Panel administrativo:** `https://localhost/admin/login`, con el correo y la contraseña del Administrador inicial. En desarrollo los correos (por ejemplo, el enlace de recuperación de acceso) no se envían: el simulador los guarda en `writable/correos/` dentro del contenedor. Para ver el último con su enlace: `docker compose exec app php spark sigccr:correos` (o `sigccr:correos 5` para los cinco últimos).

**Correo de desarrollo con Mailpit (D-49):** para ver los correos en el navegador, como los recibiría el destinatario:

1. Levantar Mailpit, una sola vez: `docker compose --profile correo up -d`. Queda activo aunque se reinicie Docker.
2. En el `.env`, cambiar el correo a SMTP:
   ```
   sigccr.adaptadorComunicaciones = real
   sigccr.smtpHost = mailpit
   sigccr.smtpPuerto = 1025
   sigccr.smtpCifrado =
   ```
3. Abrir `http://localhost:8025`. Llegan todos los correos del panel, del portal y de los procesos, con sus enlaces; ninguno sale del equipo.

Las direcciones que simulan errores de envío (`falla.transitoria@…`, `falla.definitiva@…`, `incierto@…`) solo funcionan con el simulador: para probarlas se vuelve a `sigccr.adaptadorComunicaciones = simulador`. La batería de punta a punta usa siempre el simulador. Si el `.env` está en SMTP, `tests/e2e/ejecutar.sh` lo cambia mientras corre y lo restaura al terminar.

**Portal de clientes:** un cliente registrado activa su acceso en `https://localhost/portal/activar` con su documento (o CUIT) y su correo verificado (CU-04), y después ingresa en `https://localhost/portal/login` con ese correo y su contraseña (CU-01 a CU-03). La cuenta de un cliente no sirve para el panel ni la de un usuario interno para el portal (RN-08). **Cola de comunicaciones:** el contenedor scheduler ejecuta `comunicaciones:enviar` cada minuto para los reintentos (CU-95); a mano: `docker compose exec app php spark comunicaciones:enviar`. Con `sigccr.adaptadorComunicaciones = real` y los datos `sigccr.smtp*` del `.env`, los correos salen por SMTP.

**Problemas frecuentes**

- *db unhealthy* y en `docker compose logs db` aparece `Login failed for user 'sa'`: la contraseña de `.env.docker` cambió después del primer arranque y el volumen conserva la original. Con la base vacía: `docker compose down -v && docker compose up -d`.
- *No tests executed!* con `Configuration: phpunit.dist.xml`: es el archivo del appstarter; borrarlo (`rm phpunit.dist.xml`). Los scripts de Composer ya indican `-c phpunit.xml.dist`.
- *404 de Nginx en todas las páginas* (incluso `/estado`) después de `git pull` o de cambiar de rama: el contenedor web quedó apuntando a la carpeta `public/` anterior. Recrear los contenedores de la aplicación (la base no se toca): `docker compose up -d --force-recreate web app scheduler`. Conviene hacerlo siempre después de un `git pull`.
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

## Pruebas de integración (D-51)

```bash
docker compose exec app composer test:integracion    # recrea sigccr_pruebas y ejecuta la suite Integracion
docker compose exec app vendor/bin/phpunit -c phpunit.xml.dist --testsuite Integracion --filter IN-09   # un caso
```

Verifican cada campo de cada formulario con cada clase de dato de su tipo (vacío, enviado como lista, código HTML o SQL, límites de largo, formatos alternativos, valores fuera de catálogo, fechas imposibles, importes y porcentajes límite) y los controles comunes de cada operación (sesión, permiso, token CSRF, versión de fila, identificadores), contra SQL Server. El catálogo de campos y las clases de datos están en el Anexo B del plan de pruebas; cada prueba lleva el identificador de su caso (IN-xx) y la clase (por ejemplo, `IN-09 · cuit_cuil · CUI-04 verificador incorrecto`).

`tests/feature/preparar_base.sh` borra y vuelve a crear la base `sigccr_pruebas` en cada ejecución, aplica las migraciones y carga las semillas. Las pruebas se conectan con el usuario de la aplicación (`sigccr_app`) y, en el entorno de pruebas, `Config\Database` dirige todos los grupos a `sigccr_pruebas`: la base de desarrollo no se toca. La integración continua las ejecuta en cada *pull request* con SQL Server como servicio.

## Pruebas de punta a punta

```bash
bash tests/e2e/ejecutar.sh                 # todas, con la base vacía
bash tests/e2e/ejecutar.sh --grep R-01     # solo los casos que coinciden
E2E_CONSERVAR=1 bash tests/e2e/ejecutar.sh # deja la pila levantada al terminar
```

Levanta una pila aparte (proyecto `sigccr_e2e`, puertos 18080 y 18443) con SQL Server vacío, aplica las migraciones y las semillas, crea el Administrador `admin@prueba.local` y recorre con Playwright el circuito de la prueba funcional integral (`docs/pruebas/prueba_funcional_integral_1_a_4.md`). Cada prueba lleva el identificador de su caso (R-xxx). El informe con las capturas de cada paso queda en `tests/e2e/informe/index.html`. No toca los contenedores ni la base del entorno de desarrollo. La integración continua la ejecuta en cada *pull request* y publica el informe como artefacto.

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
