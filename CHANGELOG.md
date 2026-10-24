# Registro de cambios

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/); versionado semántico.

## [Sin publicar]

### Agregado
- Validación de campos con pruebas (`Campos`, `ValidadorUsuarioInterno`): obligatorios, largos, nombres, correo, tipo y número de documento según el tipo, CUIT/CUIL con dígito verificador y motivo; los formularios marcan cada campo con su error.
- Conjunto de pruebas `Unitarias` (`composer test:unitarias`), también en `inicializar.sh` y en la integración continua.
- Comando `sigccr:correos` para ver los correos del simulador con su enlace (desarrollo).
- Usuarios internos (C04): CU-53 Localizar, CU-05 Registrar con autorización de definición inicial de 24 horas, CU-06 Modificar con verificación del correo nuevo, CU-07 Restablecer credenciales, CU-08 Desactivar, CU-09 Reactivar y CU-10 Asignar rol, con las reglas RN-04, RN-05, RN-06 y RN-08 (`ReglasUsuarios`, `UsuarioService`).
- `AutorizacionService`: autorizaciones de un solo uso para recuperación, restablecimiento, definición inicial y verificación de correo.
- Identidad visual del panel: barra lateral de tinta, verde para la acción principal, IBM Plex Sans servida localmente, cifras tabulares, pantalla de acceso en dos columnas y pantalla principal como índice de funciones por área.
- Plantilla del panel con Bootstrap 5.3, Bootstrap Icons y DataTables 2 servidos localmente: menú lateral según los permisos del rol, mensajes, formatos locales y diseño adaptable (C02).
- CU-01 Iniciar Sesión, CU-02 Recuperar Acceso y CU-03 Cerrar Sesión en el panel: mensaje genérico, bloqueo por intentos (RN-62), autorización de un solo uso de 15 minutos con hash SHA-256 (RN-03), política de contraseñas y cierre de sesiones al restablecer (C03).
- Cola de comunicaciones con datos cifrados, envío inmediato y reintentos a 1, 3 y 5 minutos (RNF-09).
- Catálogo de permisos del panel (un permiso por caso de uso) y su asignación a los cuatro roles internos según la matriz 3.6.2 (`CatalogoPermisos`, `PermisosSeeder`), con pruebas que la comparan con el documento.

## [0.1.0] - 2026-10-24

Hito Fin de Elaboración: arquitectura ejecutable verificada (2.2.2 del documento).

### Agregado
- Arquitectura ejecutable (actividad 6): contenedores web, app, scheduler y db con Docker Compose.
- Esquema de la base de datos: 70 tablas en nueve migraciones, una por subsistema, y datos semilla.
- Motor financiero con 52 pruebas y casos de referencia calculados de forma independiente.
- Componentes base: servicio transaccional, auditoría, alertas, parámetros versionados, comando base de procesos programados, filtros de seguridad y adaptadores con simuladores.
- Comando `sigccr:verificar-entorno` para el riesgo R8.
- Registro de decisiones D-01 a D-08.
- Planilla `tests/referencia/casos_referencia.xlsx`: verificación independiente con fórmulas de los 27 casos de referencia (68 valores).
- `docker/inicializar.sh`: inicializa y verifica la arquitectura ejecutable en un solo paso.

### Corregido
- El contenedor prepara `writable/` en cada arranque (el volumen quedaba vacío tras `docker compose down -v`).
- `bootstrap.sh` devuelve los archivos generados al usuario de WSL.
- `crear_base.sql` actualiza la contraseña de los inicios de sesión si ya existen.
- La contraseña del Administrador inicial no se muestra al escribirla y se pide dos veces.
- Migraciones y seeders con el propietario del esquema (`SIGCCR_DB_GRUPO`): la tabla `migrations` se creaba con `sigccr_app`, sin permiso CREATE TABLE.
- PHPUnit usa siempre `phpunit.xml.dist` (el `phpunit.dist.xml` del appstarter tenía prioridad y no ejecutaba ninguna prueba).
- Imagen de PHP con pcov (cobertura), pcntl y `safe.directory` para git.
- La cobertura de RNF-15 se mide sobre el motor financiero; los servicios se miden con las pruebas de integración.
- `inicializar.sh` detecta las excepciones de spark aunque termine con código 0 y mide la cobertura.

[Sin publicar]: https://github.com/ivanthoux/sigccr/compare/v0.1.0...develop
[0.1.0]: https://github.com/ivanthoux/sigccr/releases/tag/v0.1.0
