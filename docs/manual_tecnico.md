# Manual técnico del SIG-CCR

Destinatario: quien instala, opera y mantiene el sistema (administrador de sistemas). Este manual cubre la instalación, la configuración, el despliegue, las copias de seguridad, la operación diaria y la resolución de problemas. El uso de las pantallas está en el manual de usuario, y el diseño en el Trabajo Final (capítulo 4) y en `docs/decisiones.md`.

Ningún comando de este manual lleva contraseñas: se guardan solo en `.env` y `.env.docker`, que no se versionan.

## 1. Visión general

| Contenedor | Imagen | Función |
|---|---|---|
| `web` | `nginx:1.27-alpine` | Termina HTTPS (TLS 1.2 y 1.3), redirige el puerto 80 al 443 y deriva a PHP-FPM. |
| `app` | `sigccr/php:8.3` (se construye aquí) | PHP-FPM con CodeIgniter 4: panel administrativo, portal de clientes y `/estado`. |
| `scheduler` | `sigccr/php:8.3` | `cron` con los procesos programados (sección 7) y las copias de seguridad. |
| `db` | `mssql/server:2022-CU28-ubuntu-22.04` | SQL Server 2022, intercalación `Modern_Spanish_CI_AI`. |
| `mailpit` | `axllent/mailpit` | Solo desarrollo, con el perfil `correo`. No se usa en producción. |

Volúmenes con nombre: `sqldata` (datos de SQL Server), `storage` (`writable/`: adjuntos, sesiones, caché y registros), `backups` (copias cifradas) y `correos` (Mailpit). **Los datos del sistema están en `sqldata`, `storage` y `backups`: no se borran con `docker compose down`, pero sí con `docker compose down -v`.**

Red: una sola red interna (`sigccr_net`). Solo `web` publica puertos (80 y 443). El puerto 1433 de la base no se publica.

## 2. Requisitos

**Servidor de producción** (el de referencia de RNF-03, con el que se hizo la prueba de rendimiento):

- 4 vCPU, 8 GB de memoria y disco para los datos, los adjuntos y las copias. La base ocupa poco, pero el volumen `backups` guarda 30 días de copias con adjuntos: dimensionarlo con ese dato.
- Linux de 64 bits con Docker Engine y el complemento Compose v2.
- Un nombre de dominio (o dirección fija) y un certificado TLS emitido por una entidad reconocida.
- Salida a Internet hacia: `api.bcra.gob.ar` (Central de Deudores), el servidor SMTP elegido, la pasarela de pagos y, para la primera carga de localidades, `infra.datos.gob.ar`.
- SQL Server Express (`MSSQL_PID=Express`) alcanza para el volumen de referencia; su límite es de 10 GB por base.

**Equipo de desarrollo:** Windows con Docker Desktop y WSL 2, y el repositorio clonado dentro del sistema de archivos de WSL (no en `/mnt/c`).

## 3. Instalación

Los pasos son los mismos en desarrollo y en producción; cambian los valores de los archivos de configuración (sección 4).

```bash
git clone https://github.com/ivanthoux/sigccr.git && cd sigccr
git checkout main                              # producción: siempre una versión etiquetada (git checkout v1.0.0)

# 1. Variables de los contenedores y certificado
cp .env.docker.example .env.docker             # editar MSSQL_SA_PASSWORD y MSSQL_PID
sh docker/nginx/generar-certificado.sh         # solo desarrollo; en producción, ver 4.3

# 2. Imagen de PHP (incluye msodbcsql18, sqlsrv, pdo_sqlsrv y bcmath)
docker compose build

# 3. Estructura de CodeIgniter, dependencias y .env
docker compose run --rm --no-deps app bash docker/bootstrap.sh
# editar .env (sección 4.2)

# 4. Servicios
docker compose up -d
docker compose ps                              # esperar a que db figure como healthy

# 5. Inicialización y verificación en un paso
docker compose exec app bash docker/inicializar.sh
```

`docker/inicializar.sh` hace, en orden: prepara `writable/`, valida `.env` y `.env.docker`, espera a SQL Server, crea la base `sigccr` y los inicios de sesión, aplica las migraciones, carga los datos semilla, **pide los datos del Administrador inicial** (la contraseña no se muestra), verifica el entorno, ejecuta las pruebas unitarias y mide la cobertura del motor financiero. Es repetible y deja su salida en `inicializar.log`. Termina con un resumen *OK / FALLA* por paso.

Al terminar, comprobar:

```bash
curl -k https://localhost/estado               # 200 y "base_de_datos": "conectada"
docker compose exec app php spark sigccr:verificar-entorno
docker compose exec app php spark migrate:status -g migraciones
```

El panel queda en `https://<servidor>/admin/login` y el portal de clientes en `https://<servidor>/portal/login`.

### 3.1 Inicios de sesión de la base

`crear_base.sql` crea tres inicios de sesión con el mínimo privilegio. Las contraseñas son las de `.env`.

| Inicio de sesión | Se configura en | Lo usa | Permisos |
|---|---|---|---|
| `sigccr_app` | `database.default.*` | La aplicación (PHP-FPM y los procesos) | Lectura y escritura de datos; no crea tablas. |
| `sigccr_owner` | `database.migraciones.*` | Migraciones y semillas, con `SIGCCR_DB_GRUPO=migraciones` | Propietario del esquema. |
| `sigccr_copias` | `database.copias.*` | Solo el contenedor `scheduler` (copias y restauración) | Operador de copias de `sigccr` y `dbcreator`; se conecta a `master`. |

`sa` solo se usa para crear la base y para el chequeo de salud del contenedor.

## 4. Configuración

### 4.1 `.env.docker` (variables de los contenedores)

| Variable | Valor |
|---|---|
| `MSSQL_SA_PASSWORD` | Contraseña de `sa`. Mínimo 8 caracteres con mayúsculas, minúsculas, números y símbolos; sin `$`, comillas ni espacios. |
| `MSSQL_PID` | `Developer` en desarrollo y `Express` en producción. |
| `HTTP_PORT`, `HTTPS_PORT` | Opcionales; por omisión 80 y 443. |

La contraseña de `sa` solo se aplica en el primer arranque del volumen `sqldata`: si se cambia después, la base sigue con la original (ver 9).

### 4.2 `.env` (CodeIgniter)

Se crea desde `env.sigccr.example`; `bootstrap.sh` lo hace y genera `encryption.key`.

| Clave | Desarrollo | Producción |
|---|---|---|
| `CI_ENVIRONMENT` | `development` | `production` (no muestra errores) |
| `app.baseURL` | `https://localhost/` | `https://<dominio>/` |
| `app.forceGlobalSecureRequests` | `true` | `true` |
| `database.default.password`, `database.migraciones.password` | Distintas entre sí | Distintas entre sí, sin comillas simples |
| `database.copias.password` | Vacía: la genera `inicializar.sh` | Ídem |
| `encryption.key` | Generada | Generada y **resguardada fuera del servidor** (ver 6) |
| `sigccr.adaptadorInformacionCrediticia` | `simulador` | `real` (Central de Deudores del BCRA, API pública sin credenciales) |
| `sigccr.adaptadorPadron` | `simulador` | `real` (nombre por CUIT/CUIL en el alta) |
| `sigccr.adaptadorPasarela` | `simulador` | `real`, con `sigccr.pasarelaToken` y `sigccr.pasarelaSecretoNotificaciones` |
| `sigccr.adaptadorComunicaciones` | `simulador` (o `real` con Mailpit) | `real`, con `sigccr.smtp*` |
| `sigccr.smtpHost`, `smtpPuerto`, `smtpCifrado`, `smtpUsuario`, `smtpClave` | Mailpit: `mailpit`, `1025`, vacío | Los del proveedor; `tls` con 587 o `ssl` con 465 |
| `sigccr.correoRemitente` | `no-responder@sigccr.local` | Una dirección del dominio de la entidad |
| `sigccr.entidadRazonSocial`, `entidadCuit`, `entidadDomicilio` | Opcionales | **Obligatorias en la práctica**: figuran en el contrato de préstamo (RN-78) |
| `sigccr.entidadCorreoDatos` | Opcional | Correo para ejercer los derechos sobre los datos (Ley 25.326); se muestra en la política de datos |
| `sigccr.portalEnMantenimiento` | `false` | `true` apaga el portal de clientes (página de mantenimiento) |

Con los valores por omisión de `app/Config/Sigccr.php`: la sesión del panel vence a los 30 minutos de inactividad y la del portal a los 15; la duración máxima es de 12 horas; 5 intentos fallidos por cuenta bloquean 15 minutos; las copias se conservan 30 días y llevan los adjuntos. Cualquiera se redefine con `sigccr.<nombre> = valor` en `.env`.

Recomendaciones: `.env` y `.env.docker` con permisos `600` y dueño del usuario que administra; copia de ambos en un lugar seguro, separada de las copias de la base.

### 4.3 Certificado TLS en producción

Nginx lee `docker/nginx/certs/sigccr.crt` (cadena completa) y `docker/nginx/certs/sigccr.key`. En producción se reemplazan los autofirmados por los de la entidad certificadora, sin cambiar el compose, y se recarga con `docker compose exec web nginx -s reload`. Renovar antes del vencimiento. El script `generar-certificado.sh` es solo para desarrollo (825 días).

### 4.4 Conexión cifrada con la base

Entre contenedores la conexión viaja por la red interna sin cifrar (`encrypt = false`). Si el requisito de la entidad lo exige: instalar un certificado en SQL Server (`mssql-conf set network.tlscert` y `network.tlskey`), agregar su autoridad a la imagen `app` (`update-ca-certificates`) y poner `encrypt = true` en los tres grupos de `database.*`.

## 5. Despliegue en producción

### 5.1 Primera instalación

1. Preparar el servidor: Docker Engine, usuario no administrador en el grupo `docker`, firewall con 80 y 443 abiertos y nada más hacia afuera (22 solo desde direcciones conocidas), hora sincronizada.
2. Clonar el repositorio y pasar a la versión etiquetada.
3. Crear `.env.docker` (`MSSQL_PID=Express`) y `.env` (`CI_ENVIRONMENT = production`, adaptadores reales, datos de la entidad), y colocar el certificado (4.3).
4. Ejecutar los pasos 2 a 5 de la sección 3.
5. Cargar las localidades para el domicilio de los clientes: `docker compose exec app php spark geografia:importar`.
6. Configurar los parámetros del negocio desde el panel (líneas de crédito, tasas, parámetros versionados) con el Administrador inicial, y crear los usuarios internos con sus roles.
7. Hacer una copia manual desde el panel (CU-61), descargarla (CU-62), guardar `encryption.key` y verificar la restauración en otro entorno (sección 6.3).
8. Pasar la lista de comprobación de la sección 5.3.

### 5.2 Actualización a una versión nueva

Las migraciones son aditivas y se aplican con `migrate --all`; el esquema nunca se modifica a mano.

```bash
# 1. Copia previa: panel → Copias de seguridad → Generar copia (o esperar a la de las 02:00) y descargarla
# 2. Traer la versión
git fetch --tags && git checkout v1.x.y
# 3. Recrear la aplicación (la base no se toca) y aplicar migraciones
docker compose build
docker compose up -d --force-recreate web app scheduler
docker compose exec app composer migrar
docker compose exec app php spark sigccr:verificar-entorno
curl -k https://localhost/estado
```

Recrear `web`, `app` y `scheduler` después de cada cambio de código es obligatorio: si no, Nginx queda apuntando a una carpeta `public/` anterior y responde 404.

**Vuelta atrás:** volver a la etiqueta anterior (`git checkout v1.x.(y-1)`), recrear los contenedores y, si la migración ya cambió el esquema, restaurar la copia previa desde el panel (CU-63) o con el procedimiento de 6.3. Por eso la copia previa es el paso 1.

### 5.3 Lista de comprobación posterior al despliegue

- [ ] `docker compose ps`: `web`, `app`, `scheduler` en ejecución y `db` *healthy*.
- [ ] `https://<dominio>/estado` responde 200 y `entorno` indica `production`.
- [ ] El navegador acepta el certificado y `http://` redirige a `https://`.
- [ ] `docker compose exec app php spark sigccr:verificar-entorno` sin fallas.
- [ ] `migrate:status -g migraciones` sin migraciones pendientes.
- [ ] Ingreso al panel con el Administrador y al portal con un cliente de prueba.
- [ ] Un correo real de recuperación de acceso llega a su destino.
- [ ] La Central de Deudores responde (una evaluación de prueba o `evaluacion:ejecutar`).
- [ ] `writable/logs/cron.log` muestra ejecuciones del último minuto.
- [ ] Hay una copia de seguridad *Verificada* en el panel.
- [ ] `docker compose config` no publica el puerto 1433.

## 6. Copias de seguridad y restauración

### 6.1 Cómo funcionan (D-45)

- El panel **solicita** la copia (CU-61) y la **descarga** (CU-62); no la ejecuta. La ejecuta el contenedor `scheduler` con `sigccr_copias`, porque PHP-FPM no tiene permiso para copiar ni restaurar.
- Copia automática: todos los días a las 02:00 (`copias:generar`). Las copias pedidas desde el panel y las restauraciones se procesan cada minuto (`copias:procesar`).
- Cada copia es la base más los adjuntos del legajo, empaquetados y cifrados con AES-256-GCM con una clave derivada de `encryption.key`. Se verifica con `RESTORE VERIFYONLY` y se registra su SHA-256.
- Se conservan 30 días de copias verificadas (siempre queda la más reciente). Una falla de la copia automática alerta al Administrador.

### 6.2 Qué debe resguardarse fuera del servidor

1. **Una copia reciente, descargada desde el panel.** Una copia que solo está en el mismo servidor no protege de una pérdida del servidor. Frecuencia mínima: semanal, y antes de cada actualización.
2. **`encryption.key`, guardada aparte de las copias.** Sin ella las copias no se pueden descifrar. Quien tiene la copia y la clave puede leer todos los datos: guardarlas en lugares distintos.
3. Los archivos `.env` y `.env.docker`.

### 6.3 Restauración

**Desde el panel (CU-63):** el Administrador elige la copia y escribe `RESTAURAR`. El sistema pasa a modo mantenimiento (503), detiene los procesos programados, verifica la copia, genera una copia previa, restaura, verifica con `DBCC CHECKDB`, aplica migraciones pendientes y vuelve a abrirse. Si falla, intenta volver al estado anterior. El resultado queda en la tabla `restauraciones`, en la auditoría y en `writable/logs/restauraciones.log`. Las operaciones posteriores a la copia se pierden y el sistema informa cuántas son, para reconstruirlas.

**En otro equipo (prueba mensual o recuperación ante la pérdida del servidor):**

```bash
# Con la misma encryption.key en el .env del equipo de destino
docker compose exec app php spark copias:descifrar writable/copia.sccr writable/copia.zip
# El zip trae sigccr.bak y la carpeta adjuntos/. Restaurar sigccr.bak en SQL Server (RESTORE DATABASE) y copiar
# adjuntos/ a writable/uploads.
```

Para recuperar un servidor perdido: instalar (sección 3) con el mismo `encryption.key`, restaurar el `.bak`, copiar los adjuntos y ejecutar `docker compose exec app composer migrar`.

**Prueba de restauración mensual (plan preventivo de R2):** descifrar la última copia descargada, restaurarla en un entorno de prueba, ejecutar `sigccr:verificar-entorno` y abrir el panel. Registrar fecha, copia y resultado.

## 7. Procesos programados

Los ejecuta `cron` en el contenedor `scheduler`, en hora de Buenos Aires, con el usuario `www-data` y la salida en `writable/logs/cron.log`. La tabla de cron es `docker/scheduler/crontab` y se reinstala en cada arranque del contenedor.

| Comando | Frecuencia | Qué hace | CU |
|---|---|---|---|
| `evaluacion:ejecutar` | cada 5 min | Evalúa las solicitudes pendientes | CU-86 |
| `cartera:punitorios` | 00:05 | Registra punitorios del día y marca cuotas vencidas | CU-88 |
| `vencimientos:controlar` | cada 15 min | Vence simulaciones, propuestas, órdenes de pago y autorizaciones | CU-89 |
| `pagos:conciliar` | cada 30 min | Concilia con la pasarela las órdenes pendientes | CU-91 |
| `pagos:notificaciones` | cada minuto | Procesa las notificaciones de la pasarela | CU-90 |
| `fondos:proyectar` | 06:00 | Proyecta la disponibilidad de fondos | CU-92 |
| `campanas:cerrar` | cada 15 min | Cierra campañas vencidas o con cupo agotado | CU-94 |
| `regimen:generar` | 07:00 | Genera el régimen informativo del mes cerrado | CU-105 |
| `comunicaciones:enviar` | cada minuto | Envía correos pendientes y reintentos | CU-95 |
| `copias:generar` | 02:00 | Copia automática y depuración | CU-61 |
| `copias:procesar` | cada minuto | Copias manuales y restauración pedidas | CU-61, CU-63 |
| `sigccr:verificar-entorno --silencioso` | cada hora | Verifica PHP, SQL Server y el motor financiero | R8 |

Garantías de los procesos: un bloqueo (`sp_getapplock`) impide que dos instancias del mismo proceso corran a la vez; cada elemento se procesa en su propia transacción, de modo que un error no deshace los demás; cada ejecución queda en la tabla `ejecuciones_proceso`; y si el sistema estuvo caído, la siguiente ejecución recupera las fechas omitidas. Durante una restauración los procesos se omiten solos.

Cualquier proceso se puede ejecutar a mano: `docker compose exec app php spark <comando>`. Los comandos auxiliares son `sigccr:correos [n]` (últimos correos del simulador), `geografia:importar` y `copias:descifrar`.

## 8. Operación diaria

**Revisión diaria (5 minutos):**

1. `docker compose ps`.
2. `curl -k https://<dominio>/estado`.
3. Panel → alertas del Administrador (fallas de copia, de procesos, de fondos).
4. Última copia automática en estado *Verificada*.

**Registros:**

| Registro | Ubicación (dentro del volumen `storage`) |
|---|---|
| Aplicación y errores | `writable/logs/log-AAAA-MM-DD.log` |
| Procesos programados | `writable/logs/cron.log` |
| Restauraciones | `writable/logs/restauraciones.log` |
| Auditoría del negocio | Tabla de auditoría, desde el panel |
| SQL Server y Nginx | `docker compose logs db` y `docker compose logs web` |

```bash
docker compose exec app tail -n 100 writable/logs/cron.log
docker compose logs --tail 100 db
```

Rotar `cron.log` y los registros de la aplicación según el espacio disponible (por ejemplo con `logrotate` del servidor sobre el volumen, o vaciándolos cuando superen 100 MB).

**Espacio en disco:** vigilar `docker system df -v` (`sqldata`, `backups`, `storage`). Una copia necesita, al generarse, el doble del tamaño de los datos y los adjuntos; si no hay espacio la copia falla y alerta.

**Modo mantenimiento del portal:** `sigccr.portalEnMantenimiento = true` en `.env` y `docker compose up -d --force-recreate app`. El panel sigue disponible.

**Reinicio del servidor:** los contenedores tienen `restart: unless-stopped` y vuelven solos; la base queda *healthy* en menos de un minuto.

**Seguridad operativa:**

- Mantener el sistema operativo y Docker actualizados. La política de dependencias de PHP está en `docs/seguridad/politica_dependencias.md`.
- La versión de SQL Server está fija (`2022-CU28-ubuntu-22.04`, D-62). Para actualizarla: cambiar la etiqueta en `docker-compose.yml`, probarla primero en desarrollo con la integración continua y recién entonces en producción, con copia previa (`docker compose pull db && docker compose up -d db`).
- No usar `docker compose down -v` en producción: borra los datos.
- Los cambios de contraseña de los inicios de sesión se hacen en SQL Server (`ALTER LOGIN`) y en `.env` a la vez, y se recrean `app` y `scheduler`.

## 9. Problemas frecuentes

| Síntoma | Causa | Solución |
|---|---|---|
| `db` *unhealthy* y `Login failed for user 'sa'` en el registro | Se cambió `MSSQL_SA_PASSWORD` después del primer arranque; el volumen conserva la original. | Devolver la contraseña original. Con la base vacía: `docker compose down -v && docker compose up -d`. |
| 404 de Nginx en todas las páginas, incluso `/estado` | Después de `git pull` o de cambiar de rama, `web` quedó con la carpeta `public/` anterior. | `docker compose up -d --force-recreate web app scheduler`. |
| `Cache unable to write to writable/cache` | El volumen `storage` se recreó vacío. | `docker compose up -d --force-recreate app scheduler` (el arranque lo prepara). |
| `No tests executed!` con `phpunit.dist.xml` | Es el archivo del appstarter. | `rm phpunit.dist.xml`. |
| `inicializar.sh` dice FALLA en «Configuración» | Falta una contraseña en `.env`, o las de `default` y `migraciones` son iguales. | Completar `.env` y repetir; el script es repetible. |
| Error de certificado al abrir el sitio | Certificado de desarrollo autofirmado o vencido. | Instalar el certificado de la entidad (4.3) y `nginx -s reload`. |
| 503 en todo el sistema | Restauración de copia en curso (modo mantenimiento). | Esperar: la página se actualiza sola. Ver `restauraciones.log` si no termina. |
| 503 solo en el portal | `sigccr.portalEnMantenimiento = true`. | Ponerlo en `false` y recrear `app`. |
| No llegan los correos | Adaptador en `simulador`, SMTP mal configurado o cola con reintentos. | Revisar `sigccr.adaptadorComunicaciones` y `sigccr.smtp*`; ejecutar `comunicaciones:enviar` y mirar su salida. En desarrollo: `sigccr:correos` o Mailpit. |
| Falla al enviar con `smtpCifrado` | Falta la clave `sigccr.smtpCifrado` en `.env`, o no coincide con el puerto (tls/587, ssl/465). | Agregarla; en Mailpit, vacía. |
| La evaluación crediticia queda pendiente | La Central de Deudores no responde (espera de 10 s y 2 reintentos). | Verificar la salida a `api.bcra.gob.ar`; el proceso reintenta solo. |
| La copia automática falla | Sin espacio en el volumen `backups`, o `sigccr_copias` sin permisos. | Liberar espacio; repetir `inicializar.sh` (vuelve a crear el inicio de sesión). Ver la causa en el panel. |
| La copia descargada no se descifra | `encryption.key` distinta de la usada al generarla. | Usar la clave original; sin ella no hay recuperación. |
| `spark` termina sin error pero no hizo nada | CodeIgniter puede salir con código 0 tras una excepción. | Leer la salida completa; `inicializar.sh` la revisa por usted. |
| Las pruebas de integración fallan al conectar | `sigccr_pruebas` no existe o está en uso. | `composer test:integracion` la recrea en cada ejecución. |

## 10. Verificación del sistema

| Qué | Comando |
|---|---|
| Entorno (PHP, base, motor financiero) | `docker compose exec app php spark sigccr:verificar-entorno` |
| Pruebas unitarias | `docker compose exec app composer test:unitarias` |
| Motor financiero | `docker compose exec app composer test:finanzas` |
| Cobertura del motor financiero (mínimo 90 %) | `docker compose exec app composer cobertura` |
| Integración contra SQL Server | `docker compose exec app composer test:integracion` |
| Punta a punta con navegador | `bash tests/e2e/ejecutar.sh` (pila aparte, no toca la base de desarrollo) |
| Rendimiento | Flujo *Rendimiento* de GitHub Actions o `bash tests/rendimiento/ejecutar.sh` |
| Estilo y análisis estático | `composer cs` y `composer stan` |

No ejecutar las pruebas de integración ni las de punta a punta sobre una base con datos reales. En producción solo corresponde `sigccr:verificar-entorno` y `/estado`.

## 11. Referencias

- `README.md`: instalación en desarrollo y criterios del hito de Elaboración.
- `docs/decisiones.md`: D-45 (copias), D-49 (correo de desarrollo), D-62 (versión fija de SQL Server) y las demás decisiones de diseño.
- `docs/seguridad/`: ASVS nivel 1, Ley 25.326 y política de dependencias.
- `docs/pruebas/`: pruebas funcionales por iteración, la integral y la de rendimiento.
- `CONTRIBUTING.md` y `CHANGELOG.md`: ramas, versiones y cambios.
