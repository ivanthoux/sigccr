# Registro de cambios

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/); versionado semántico.

## [Sin publicar]

### Agregado (E06, D-37)
- CU-76 Aplicar Saldo a Favor a Obligaciones (Cajero): saldos con su origen, cuotas consecutivas desde la más antigua, constancia de conformidad del cliente, prelación de RN-29 e imputación parcial excepcional si el saldo no alcanza.
- CU-77 Autorizar Devolución de Saldo a Favor (Gerente): importe, motivo, constancia de la solicitud y destino (medio de pago original para pagos electrónicos o transferencia a un CBU del cliente con titularidad verificada); reserva el importe por 72 horas y alerta a los Cajeros. Anulación de autorizaciones no ejecutadas.
- CU-78 Ejecutar Devolución (Cajero): por la pasarela con identificador único o registrando el comprobante de la transferencia; resultado confirmado, fallido (libera la reserva) o incierto (en conciliación), y constancia de devolución.
- `pagos:conciliar` concilia las devoluciones inciertas y CU-89 vence las autorizaciones sin ejecución. Simulador de devoluciones en `simulador/devoluciones`.
- Migración 22.

### Corregido (E06)
- La disponibilidad de fondos descontaba las devoluciones de saldos a favor, que no integran los fondos (RN-57).

### Agregado (E05, D-36)
- CU-39 Simular Plan de Refinanciación (Analista): créditos En mora con todas sus cuotas no pagadas, capital refinanciable y concepto no capitalizable al día, tasa y cuotas dentro de los límites de CU-60 y plan francés preliminar, vigente 7 días.
- CU-40 Registrar Propuesta: a partir de una simulación vigente cuya deuda no cambió; fija la deuda a la fecha de registro, vence a los 7 días y alerta al Gerente. Impide dos propuestas vigentes sobre el mismo crédito y registra la corrección de una observada como nueva versión.
- CU-41 Resolver Propuesta (Gerente): aprobar, rechazar u observar con fundamento, previa revalidación de la vigencia y la deuda.
- CU-42 y CU-43 Localizar y Consultar Propuesta: búsqueda por número, cliente, documento, estado y fechas; detalle con cuotas, plan, resoluciones, versiones, alertas y aceptación.
- CU-74 Registrar Aceptación y Aplicar: constancia del cliente, punitorios posteriores al corte incorporados al concepto no capitalizable, nuevo crédito con origen Refinanciación y primer vencimiento a 30 días, cuotas originales Sustituidas y créditos Refinanciados, sin desembolso.
- CU-96 Cancelar Propuesta: desistimiento del cliente (Analista) o causa administrativa (Gerente).
- CU-44 Condonar Intereses Punitorios (Gerente): hasta el máximo configurado, desde la cuota más antigua y con su IVA proporcional, con fundamento.
- Cobros, anulaciones y condonaciones sobre cuotas comprendidas dejan Observada la propuesta vigente (RN-36); CU-89 vence las propuestas.
- Migración 21.

### Agregado (E03, D-35)
- CU-34 Generar Orden de Pago Electrónico (Cajero): cuotas completas y consecutivas por su total con punitorios al día, enlace de pago de la pasarela y vencimiento a las 00:00; impide incluir una cuota en dos órdenes vigentes.
- CU-38 Consultar Estado de Pago Electrónico: órdenes por número, cliente o estado, con sus cuotas, transacciones, incidencias, cobro y saldo a favor, y consulta a la pasarela a pedido.
- CU-90 Procesar Notificación de Pago Electrónico (`php spark pagos:notificaciones`, cada minuto) y webhook `POST api/webhooks/pasarela` con validación de firma: confirma el cobro una sola vez con la fecha efectiva, revierte los punitorios posteriores al pago y registra las incidencias de importe, plazo, cuotas ya pagadas o estado no definitivo; el excedente queda como saldo a favor.
- CU-91 Conciliar Órdenes de Pago Pendientes (`php spark pagos:conciliar`, cada 30 minutos): consulta las órdenes sin confirmar, vence las que no se pagaron y alerta al Gerente las que llevan más de 48 horas en conciliación.
- Simulador de la pasarela fuera de producción (`simulador/pagar/{referencia}`).
- CU-33 advierte las cuotas incluidas en una orden de pago electrónico pendiente (A6).
- Migración 20: `ordenes_pago_electronico.url_pago`.

### Agregado (E10, D-34)
- CU-102 Registrar Revocación del Préstamo (Cajero): plazo de 10 días hábiles desde la recepción del contrato o el desembolso, lo último, con el calendario operativo; importe a restituir (neto desembolsado, interés compensatorio de los días transcurridos y su IVA, menos lo pagado; sin gastos ni comisiones); confirmación de la transferencia recibida, que integra la conciliación de la caja; el crédito pasa a Revocado y sus cuotas no pagadas a SinEfecto. Si lo pagado supera el importe, el exceso queda como saldo a favor.
- Migración 19: estados `Revocado` del crédito y `SinEfecto` de la cuota, cobro de tipo `Revocacion` y tabla `revocaciones`.

### Agregado (E04, D-33)
- CU-60 Configurar Parámetros de Morosidad: tasa punitoria diaria y su tope sobre la TNA (hasta 50 %, art. 769 del Código Civil y Comercial), tolerancia, umbral de morosidad avanzada, máximo condonable y rango de refinanciación, en versiones no retroactivas.
- CU-88 Calcular Intereses Punitorios (`php spark cartera:punitorios`, todos los días a las 00:05): marca cuotas vencidas y créditos en mora, registra un tramo por cuota y día con la tasa vigente ese día sobre el capital vencido pendiente, y los vuelve exigibles al superar la tolerancia del crédito; recupera días omitidos sin duplicar tramos.
- El cobro en ventanilla calcula los punitorios hasta el día del pago antes de mostrar y cobrar la deuda (RN-53).

### Agregado (E09, D-31)
- Contrato de préstamo (RN-78): se genera con cada aprobación, automática o del Gerente, e informa partes, importe y neto, TNA, TEA, el CFTEA destacado al doble del tamaño, total de intereses, plan francés con sus cuotas, gastos con IVA, tasa punitoria y tolerancia, cancelación anticipada sin comisión, pagos sin efectivo, jurisdicción del domicilio del cliente y, antes de las firmas, el derecho de revocación en caracteres destacados.
- CU-103 Registrar Contrato Firmado (Analista): impresión del contrato y copia escaneada de la constancia firmada al legajo, con la fecha de recepción; puede reemplazarse antes de liquidar y la anterior se conserva.
- CU-27 no liquida sin la constancia firmada de la aprobación vigente (RN-25, A4).
- Migración 18: tabla `contratos_prestamo` y tipo de documento interno `CONTRATO_FIRMADO`. Datos de la entidad en `sigccr.entidadRazonSocial`, `sigccr.entidadCuit` y `sigccr.entidadDomicilio`.

### Agregado (E11, D-31)
- Identificación del cliente para la UIF (RN-80, Resolución UIF 200/2024): nacionalidad y actividad laboral o profesional principal obligatorias para la persona humana, y declaración jurada de persona expuesta políticamente para todo cliente, con su fecha. La declaración afirmativa genera una alerta al Gerente para la debida diligencia reforzada.
- CU-22 exige la identificación completa antes de registrar una solicitud; los clientes registrados antes la completan con CU-12.
- Migración 17: `personas_humanas.nacionalidad`, `clientes.persona_expuesta` y `clientes.fecha_declaracion_pep`.

### Corregido (E11)
- CU-12 no guardaba el cambio de la categoría del monotributo.

### Agregado (E02)
- CU-37 Anular Cobro: el Gerente anula con motivo un cobro confirmado; se conserva como antecedente, las cuotas recuperan lo imputado por concepto y vuelven a Pendiente o Vencida, el crédito recupera su estado y el importe recibido queda como saldo a favor del cliente (RN-33, RN-57). Los cobros posteriores sobre las mismas cuotas se presentan y quedan identificados para su revisión (A2).

### Corregido
- Consulta del comprobante de pago (CU-35): error de sintaxis en la consulta del Cajero responsable. La integración continua verifica ahora la sintaxis de todo el código PHP.

### Agregado (E01, D-32)
- CU-33 Registrar Cobro en Ventanilla: deuda del cliente por crédito y cuota con sus conceptos y días de atraso, advertencia de morosidad avanzada (RN-65), selección consecutiva desde la cuota más antigua, pago total o parcial (D-12) con uno o más medios bancarios, imputación por prelación (RN-29) y actualización del estado de las cuotas y del crédito.
- CU-35 Emitir Comprobante de Pago: búsqueda por comprobante, cliente o fecha; impresión y reemisión auditadas sin generar un nuevo cobro (RN-31).
- CU-32 Consultar Movimientos de Caja: jornadas por caja, Cajero, fecha y estado, con sus cobros por medio y la conciliación del cierre; el Cajero ve solo las propias.

### Cambiado (D-30)
- Caja sin efectivo: la jornada se abre sin fondo inicial y se cierra conciliando, por medio de pago, los cobros registrados con lo acreditado según el extracto o el resumen del procesador; cada diferencia exige una explicación (CU-31, CU-36, RN-28, RN-32).
- Cobro en ventanilla solo con transferencia o depósito y tarjeta de débito o de crédito, con número de operación y sin cambio (`MediosCobro`, RN-29, RN-77).
- Migración 16: tabla `conciliaciones_jornada` y restricciones que impiden registrar efectivo, cambio, fondo inicial o desembolsos por caja; los registros anteriores se conservan.

## [0.3.0] - 2026-11-06

Control 2 (previsto para el 13/11/2026): iteración 2 completa (SS-04 y SS-05: simulación, solicitud, evaluación, alternativas, resolución, liquidación, cajas, fondos, parámetros y calendario) con las observaciones de la presentación del 02/11/2026. Prueba funcional aprobada el 06/11/2026 (97 de 97).

### Cambiado (observaciones de la presentación del 02/11/2026, D-23 a D-29)
- Líneas: el máximo debe ser mayor que el mínimo en importes, cuotas y edades (RN-14); gasto administrativo fijo o porcentual del capital en cada cuota (`GastoAdministrativo`, RN-68, D-28).
- Simulación y comparación: datos del cliente (edad, situación laboral, empleador, ingresos, obligaciones y capacidad de pago); plan de cuotas paginado de a 12.
- Resolución del Gerente: documentación del legajo con su verificación, abierta en el navegador.
- Confirmación al registrar una nueva versión de condiciones o de parámetros con solicitudes en trámite (D-24).
- Desembolso solo por transferencia a un CBU del cliente; sin efectivo ni CVU (Ley 25.345, D-27).

### Agregado (D-25 y D-26)
- CU-100 Configurar Catálogo de Documentación: tipos de documento nuevos y documentación base por categoría de ingresos.
- CU-101 Actualizar Categorías del Monotributo: escala de ARCA versionada y categoría obligatoria del cliente monotributista, consistente con sus ingresos.
- Migración 15 (`documentacion_sugerida`, `categorias_monotributo`, gasto porcentual y rangos estrictos); el modelo pasa a 77 tablas.

### Agregado (iteración 2 · D01: simulación, solicitud, verificación y correcciones)
- CU-21 Simular Crédito: líneas admitidas para el cliente según estado, vigencia, tipo, categoría, edad o antigüedad (`Elegibilidad`, RN-13, RN-75), validación del importe y las cuotas (RN-14, RN-76) y plan preliminar con TEA, CFTEA, gastos e IVA (`Simulador`, RN-50 a RN-52, RN-63, RN-68), vigente por 7 días (RN-48).
- CU-22 Registrar Solicitud desde una simulación vigente: cliente activo con CUIT/CUIL, línea no suspendida (RN-16), una sola solicitud en trámite por cliente y línea (RN-18) y la documentación que exige la versión de condiciones (RN-11, D-14).
- CU-23 Verificar Legajo: resultado por documento con su motivo y resultado de la revisión (`RevisionLegajo`, RN-19); incorporación de documentación durante la verificación (A4).
- CU-84 Registrar Corrección: reemplazo de los documentos observados (RN-44), aclaraciones y etapa siguiente según 4.2.3, incluida la devolución gerencial (`EstadosSolicitud`); corrección parcial (A1).
- RN-61: un documento clave nuevo devuelve a verificación las solicitudes evaluadas, invalida su evaluación y alerta al Analista.
- Localización y consulta de solicitudes (base de CU-29 y CU-30) con historial de estados y revisiones documentales.
- `LegajoService`: legajo compartido por clientes y solicitudes.

### Agregado
- Especificación del modelo de datos en el repositorio (`docs/modelo-datos/`) con sus generadores y `verificar.py`, que la integración continua ejecuta para comprobar que las migraciones producen el mismo esquema.

### Corregido
- Migración 12: `alertas_vistas.usuario_id` sin clave foránea hacia SS-01 (las tablas de SS-10 no dependen de otros subsistemas) e índices de las claves foráneas de la migración 10 (`movimientos_caja.cobro_id`, `simulaciones_comparativas.usuario_id` y `version_parametros_id`).

## [0.2.0] - 2026-10-29

Control 1 (previsto para el 03/11/2026): iteración 1 completa (SS-01, SS-02, SS-03 y SS-10) y motor financiero aprobado por sus casos de prueba (2.2.2 del documento).

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

[Sin publicar]: https://github.com/ivanthoux/sigccr/compare/v0.3.0...develop
[0.3.0]: https://github.com/ivanthoux/sigccr/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/ivanthoux/sigccr/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/ivanthoux/sigccr/releases/tag/v0.1.0
