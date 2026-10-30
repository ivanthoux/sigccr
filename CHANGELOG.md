# Registro de cambios

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/); versionado semántico.

## [Sin publicar]

## [0.2.0] - 2026-11-03

Control 1: iteración 1 completa (SS-01, SS-02, SS-03 y SS-10) y motor financiero aprobado por sus casos de prueba (2.2.2 del documento).

### Agregado
- Portada pública en la raíz del sitio: ingreso al portal, activación del acceso y recuperación para los clientes, y un enlace al panel para el personal.
- Clientes (C05): CU-54 Localizar, CU-11 Registrar persona humana o jurídica con su legajo y consentimientos, CU-12 Modificar (el cambio de correo exige evidencia de identidad y nueva verificación), CU-13 Desactivar, CU-14 Reactivar y CU-15 Consultar perfil e historial crediticio, con las reglas RN-09 a RN-12, RN-58 y RN-64 (`ValidadorCliente`, `RequisitosDocumentales`, `ArchivoLegajo`, `ReglasClientes`, `ClienteService`).
- Legajo digital: PDF, JPG o PNG de hasta 5 MB guardados fuera de `public/` con nombre aleatorio; descarga solo con permiso y aviso de la documentación faltante según la categoría de ingresos.
- Verificación del correo del cliente desde el portal (`portal/verificar-correo`); el alta crea su cuenta pendiente de activación.
- Validación de importes, fechas, edad mínima, teléfono y correspondencia entre CUIL y DNI, con pruebas.
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
- Líneas de crédito (C07): CU-16 Registrar, CU-17 Modificar (nueva versión no retroactiva), CU-18 Suspender, CU-19 Reactivar, CU-20 Localizar y CU-73 Consultar, con importe mínimo y máximo prestable (RN-76, D-09) y documentación exigida por categoría.
- CU-58 Configurar Parámetros de Evaluación Crediticia: pesos, valores, cortes y situaciones excluyentes del motor BCRA, con validaciones y carga de los valores sugeridos de RN-69 (D-13).
- `ComparadorLineas`: simulación comparativa de todas las líneas para un cliente, por mayor monto o menor CFTEA (CU-99, D-10).
- `MediosCobro`: cobro en ventanilla con varios medios de pago (D-11).
- Migración 10: `cobros_medios`, `simulaciones_comparativas`, `cobros.cambio`, `movimientos_caja.cobro_medio_id`.
- Decisiones D-09 a D-14 por las devoluciones de la presentación de la v8.
- Líneas «solo con DNI»: el alta del cliente exige solo su identificación y cada línea define la documentación de la solicitud (D-14).
- CU-04 Activar o Rehabilitar Acceso del Cliente en el portal (`portal/activar`), con RN-10 y respuesta sin revelar el estado de la cuenta (`ReglasActivacion`, `ActivacionService`).
- CU-83 Consultar Alertas: listado con filtros, detalle registrado como visto, «Ir a la operación» según el rol e indicador de pendientes en el menú (`OperacionAlerta`, migración 11 `alertas_vistas`).
- CU-95 Procesar Comunicaciones Pendientes: comando `comunicaciones:enviar` cada minuto, validez antes de enviar, reintentos al 1, 3 y 5 minutos del primer intento y resultado incierto En revisión (`PoliticaEnvio`).
- Adaptador SMTP real (`sigccr.adaptadorComunicaciones = real`) con clasificación de respuestas según RNF-09.
- Suite `Rutas`: autorización de cada ruta del panel con cada rol, sin base de datos.
- CU-01 a CU-03 del Cliente en el portal (`portal/login`, `portal/recuperar`, `portal/salir`): mismo servicio que el panel con el tipo de cuenta Cliente, mensaje genérico, bloqueo por intentos (RN-62) y rechazo del cliente desactivado en el padrón aunque su contraseña sea correcta (RN-10).
- Plantilla del portal con barra superior y pantalla principal con las funciones de autogestión, que pasan a ser enlaces a medida que se construyen (`Config\Menu::$portal`).
- Pruebas `Rutas`: cada ruta del portal con sesión exige `auth:portal`, el panel no acepta la sesión del portal y las pantallas de acceso envían cada formulario a su ámbito.
- Diseño de la iteración 1 (C09): clases de diseño y nueve diagramas de secuencia por patrón en Mermaid y draw.io (`docs/diseno/`), incorporados como sección 4.5 del documento.
- Guía de la prueba funcional de la iteración 1 (C11) con 60 casos y su registro de resultados: aprobada el 29/10/2026 (`docs/pruebas/`).
- Decisiones D-15 (persistencia desde los servicios con el Query Builder, sin modelos por tabla) y D-16 (activación del portal al verificar el correo).

### Cambiado
- `Imputador`: el cobro ordinario admite el pago parcial de cuotas (D-12); `imputaciones.parcial_excepcional` pasa a `parcial`.
- `cobros.medio` y `cobros.numero_operacion` pasan a `cobros_medios` (D-11).
- El rechazo del inicio de sesión advierte cuando quedan dos intentos o uno antes del bloqueo temporal, e informa el bloqueo al aplicarse y mientras dura (CU-01 A1 y A2), en el panel y en el portal. Los correos sin cuenta habilitada siguen la misma secuencia con un contador en la caché, para no revelar si la cuenta existe (RN-62).
- Al terminar la activación (CU-04 paso 10) el cliente pasa al inicio de sesión del portal con su correo ya cargado; la pantalla de activación ofrece también «Ya tengo acceso: iniciar sesión».

### Corregido
- El número de documento ya no descarta en silencio puntos, guiones o espacios sueltos («28999111.» se rechazaba como válido); solo se acepta el separador de miles bien formado (28.999.111).
- CUIL/CUIT: el prefijo se valida según el tipo de persona (20, 23, 24 o 27 para personas humanas; 30, 33 o 34 para jurídicas) y, si el dígito verificador no corresponde al prefijo, el mensaje indica cuál es.
- El bloqueo temporal vencido vuelve a Habilitada antes de cualquier lectura del estado de la cuenta (4.2.1), no solo al iniciar sesión: la recuperación de acceso, la activación del portal y las pantallas de usuarios y clientes ya no ven un bloqueo terminado (RN-62).
- El cliente que confirma su correo desde el enlace del alta pasa directamente a definir su contraseña del portal (D-16); el correo de verificación de los clientes tiene su propia plantilla.
- La pantalla de clientes muestra los registrados más recientemente antes de buscar, como las de usuarios y líneas.
- Un resultado incierto del proveedor de correo se reintentaba; ahora queda En revisión y no se reenvía (RNF-09, CU-95 A4).
- Los reintentos se programaban 1, 3 y 5 minutos después de cada intento; ahora se cuentan desde el primero.
- Integración continua: se versionan vacías las subcarpetas de `writable/`; un clon nuevo fallaba con «Cache unable to write».

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

[Sin publicar]: https://github.com/ivanthoux/sigccr/compare/v0.2.0...develop
[0.2.0]: https://github.com/ivanthoux/sigccr/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/ivanthoux/sigccr/releases/tag/v0.1.0
