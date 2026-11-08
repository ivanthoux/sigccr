# Registro de decisiones

Decisiones de análisis y diseño adoptadas durante el proyecto. Cada una indica su contexto, la decisión, sus consecuencias y su estado de validación con el director. D-01 a D-08 fueron validadas el 24/10/2026 y D-09 a D-14 el 29/10/2026. D-15 a D-29 fueron aceptadas el 06/11/2026 por uno de los profesores y se aplican; quedan pendientes de ratificación por el director y, si las objeta, se reemplazan por decisiones nuevas; su contenido está incorporado en las secciones correspondientes del documento del proyecto, y este archivo es el registro oficial de las decisiones (desde la v9 el documento no lo reproduce). Si una decisión validada requiere un cambio, se registra como una decisión nueva que reemplaza a la anterior, con los motivos del cambio.

| ID | Fecha | Estado |
|---|---|---|
| D-01 | 23/10/2026 | Validada (24/10/2026) |
| D-02 | 23/10/2026 | Validada (24/10/2026) |
| D-03 | 23/10/2026 | Validada (24/10/2026) |
| D-04 | 23/10/2026 | Validada (24/10/2026) |
| D-05 | 23/10/2026 | Validada (24/10/2026) |
| D-06 | 23/10/2026 | Validada (24/10/2026) |
| D-07 | 23/10/2026 | Validada (24/10/2026) |
| D-08 | 23/10/2026 | Validada (24/10/2026) |
| D-09 | 27/10/2026 | Validada (29/10/2026) |
| D-10 | 27/10/2026 | Validada (29/10/2026) |
| D-11 | 27/10/2026 | Validada (29/10/2026) |
| D-12 | 27/10/2026 | Validada (29/10/2026) |
| D-13 | 27/10/2026 | Validada (29/10/2026) |
| D-14 | 28/10/2026 | Validada (29/10/2026) |
| D-15 | 29/10/2026 | Aceptada (06/11/2026), pendiente de ratificación |
| D-16 | 29/10/2026 | Aceptada (06/11/2026), pendiente de ratificación |
| D-17 | 30/10/2026 | Aceptada (06/11/2026), pendiente de ratificación |
| D-18 | 30/10/2026 | Aceptada (06/11/2026), pendiente de ratificación |
| D-19 | 30/10/2026 | Aceptada (06/11/2026), pendiente de ratificación |
| D-20 | 30/10/2026 | Aceptada (06/11/2026), pendiente de ratificación |
| D-21 | 30/10/2026 | Aceptada (06/11/2026), pendiente de ratificación |
| D-22 | 30/10/2026 | Aceptada (06/11/2026), pendiente de ratificación |
| D-23 | 03/11/2026 | Aceptada (06/11/2026), pendiente de ratificación |
| D-24 | 03/11/2026 | Aceptada (06/11/2026), pendiente de ratificación |
| D-25 | 03/11/2026 | Aceptada (06/11/2026), pendiente de ratificación |
| D-26 | 03/11/2026 | Aceptada (06/11/2026), pendiente de ratificación |
| D-27 | 03/11/2026 | Aceptada (06/11/2026), pendiente de ratificación |
| D-28 | 03/11/2026 | Aceptada (06/11/2026), pendiente de ratificación |
| D-29 | 03/11/2026 | Aceptada (06/11/2026), pendiente de ratificación |
| D-30 | 06/11/2026 | Propuesta |
| D-31 | 06/11/2026 | Propuesta |
| D-32 | 07/11/2026 | Propuesta |
| D-33 | 07/11/2026 | Propuesta |
| D-34 | 07/11/2026 | Propuesta |

## D-01 · Capital máximo financiable con el IVA del interés (CU-87)

**Contexto.** CU-87 paso 3 calcula el capital máximo descontando de la capacidad de pago solo el gasto administrativo y su IVA. En un plan francés la primera cuota incluye además el IVA del interés, por lo que la alternativa del monto máximo superaría siempre la capacidad y se descartaría en el paso 6.

**Decisión.** El capital máximo para n cuotas es C ≤ (K − g · (1 + IVA)) / (1 / a(n, i) + IVA · i), con a(n, i) = [1 − (1 + i)⁻ⁿ] / i. El resultado se redondea hacia abajo al paso configurado y se verifica con el plan real.

**Consecuencias.** Implementado en `CapacidadPago::capitalMaximo` (caso CP-F17). Texto de CU-87 paso 3 corregido.

## D-02 · Valores de ejemplo para los parámetros «definidos por la entidad» (3.5.1)

**Decisión.** Tasa punitoria diaria 0,12 %; refinanciación con TNA entre 30 % y 80 % y hasta 36 cuotas; campañas: umbral de disponibilidad $ 5.000.000, proyección a 30 días, factor de cobrabilidad 0,90, reserva mínima $ 1.000.000, capital máximo $ 3.000.000 y 15 días de duración.

**Consecuencias.** Cargados por `ParametrosInicialesSeeder` e incorporados como valores iniciales en 3.5.1. Se modifican con CU-51 y CU-60 sin cambiar código.

## D-03 · Conexión con SQL Server sin cifrar dentro de la red interna de Docker

**Contexto.** El controlador ODBC 18 exige un certificado de confianza para cifrar la conexión.

**Decisión.** En desarrollo, `encrypt = false` en la red interna (el puerto 1433 no se publica). En producción, certificado TLS en SQL Server, su autoridad agregada a la imagen `app` y `encrypt = true`.

**Consecuencias.** Vista de despliegue (4.4.5) y su diagrama actualizados.

## D-04 · Prelación del concepto no capitalizable (RN-29, RN-70)

**Decisión.** En las cuotas de una refinanciación, el concepto no capitalizable se imputa después del IVA y antes del capital.

**Consecuencias.** `Imputador::PRELACION`; valor `NoCapitalizable` agregado a `ConceptoDeuda` (4.1.1); prelación de RN-29 actualizada.

## D-05 · Valores de las enumeraciones complementarias (4.1.1)

**Decisión.** Se adoptan los valores de las 27 enumeraciones agregadas en la v6 (por ejemplo `DecisionResolucion`: Aprobada · Rechazada · DevueltaRevision).

**Consecuencias.** Restricciones CHECK del esquema (4.3).

## D-06 · Dependencias SS-06 → SS-03 y SS-08 → SS-02 (3.7.1)

**Decisión.** Se agregan a la descripción de subsistemas: el crédito conserva la versión de condiciones de su línea y las ofertas se dirigen a clientes.

## D-07 · Claves foráneas reubicadas y referencias genéricas (4.3.1)

**Decisión.** Las claves foráneas siguen el sentido de las dependencias entre subsistemas. Cuando una asociación del dominio va en sentido contrario, la clave se ubica en el subsistema dependiente (`liquidaciones.credito_id`, `ofertas.solicitud_id`, `clientes.cuenta_cliente_id`) o se reemplaza por una referencia genérica validada por el servicio (`afectaciones_fondos`, `comunicaciones`, identificadores de usuario en SS-10).

## D-08 · Redondeo directo de los importes exigibles (RN-50)

**Decisión.** Los importes exigibles (interés de cada cuota, IVA, gastos de otorgamiento) se redondean a dos decimales desde el valor exacto, sin pasar por cuatro decimales, para evitar el doble redondeo. Los cuatro decimales se usan en los resultados que se acumulan antes de exigirse (tramos de punitorios).

**Consecuencias.** `AmortizacionFrancesa`, `Punitorios`; criterio agregado a RN-50.

## D-09 · Importe mínimo y máximo prestable de cada línea (RN-76)

**Contexto.** En la presentación de la v8 se pidió que cada crédito tenga un mínimo y un máximo prestable configurable (por ejemplo, motos entre $ 300.000 y $ 1.500.000). La v8 ya los contemplaba (CU-16 paso 2, CU-21 A2, CU-87 paso 4, RN-14 y `versiones_condiciones.monto_minimo/monto_maximo`), pero repartidos y sin una regla propia, por lo que no se percibían.

**Decisión.** Se explicitan en una regla nueva, RN-76: cada versión de condiciones define un importe mínimo y un máximo prestable, obligatorios, con mínimo mayor que cero y no superior al máximo; acotan simulaciones (CU-21, CU-99), solicitudes (CU-22), alternativas automáticas (CU-87), ofertas (CU-93) y la aprobación convencional (CU-26). Un cambio de límites genera una versión nueva (RN-15). Se agregan al glosario y al modelo de dominio como atributos visibles de `VersionCondiciones`.

**Consecuencias.** Sin cambios de esquema. `ValidadorLinea` (C06) y `ComparadorLineas` aplican la regla; el formulario de CU-16/CU-17 muestra el ejemplo de la línea de motos.

## D-10 · Simulación comparativa de todas las líneas (CU-99)

**Contexto.** Se pidió un caso de uso para el cliente que se presenta sin saber qué tipo de crédito busca: simular todas las líneas a la vez para ver cuál le presta más o cuál le conviene.

**Decisión.** Nuevo CU-99 Simular Todas las Líneas de Crédito (Analista de Crédito, SS-04). Con una sola capacidad de pago (CU-87 pasos 1 y 2) calcula, para cada línea activa y vigente: si el cliente es elegible (RN-75); el monto máximo prestable (D-01 limitado por RN-76); y una alternativa recomendada con el importe pedido (o el máximo) en la menor cantidad de cuotas que entra en la capacidad, que es la de menor costo total. Marca la línea de **mayor monto** y la de **menor CFTEA** (desempate: menor total a pagar) y ordena por el criterio que elija el Analista. Cada alternativa recomendada se guarda como una simulación vinculada a la comparativa, que puede formalizarse con CU-22. Es preliminar (RN-17): no consulta la Central de Deudores.

**Consecuencias.** Tabla `simulaciones_comparativas` y columnas `simulaciones.simulacion_comparativa_id` y `posicion` (migración 10). Lógica en `ComparadorLineas` con 8 pruebas. Permiso `solicitudes.simular-comparativa` para el Analista (matriz 3.6.2). La pantalla se construye en la iteración 2, junto con CU-21.

## D-11 · Cobro con varios medios de pago (RN-29)

**Contexto.** RN-29 admitía un único medio por cobro. Se pidió poder pagar una cuota con más de un medio a la vez (por ejemplo, parte en efectivo y parte por transferencia).

**Decisión.** Un cobro en ventanilla se compone de uno o más medios. Los medios distintos del efectivo se aplican por su importe exacto, con número de operación, no pueden superar el importe a cobrar y no generan cambio; el efectivo, en una sola línea, cubre el resto y su excedente es cambio. Se admiten varias líneas del mismo medio electrónico con números de operación distintos. Cada medio genera su movimiento de caja; el arqueo (RN-32) sigue sumando solo el efectivo. La devolución por el medio original de un cobro con varios medios (RN-73) se hace por transferencia a una cuenta verificada del cliente.

**Consecuencias.** Tabla `cobros_medios`; `cobros` pierde `medio` y `numero_operacion` y gana `cambio`; `movimientos_caja.cobro_medio_id` (migración 10). Lógica en `MediosCobro` con 7 pruebas. Cambian RN-29, RN-73, CU-33, CU-35, CU-36, CU-37, CU-56 y el modelo de dominio (`MedioCobro`).

## D-12 · Pago parcial ordinario de cuotas (RN-29)

**Contexto.** RN-29 excluía el pago de una fracción de cuota; solo había imputaciones parciales excepcionales (RN-56, RN-57). El director preguntó si se contempla el pago parcial. Se decidió habilitarlo siempre en ventanilla.

**Decisión.** El cobro ordinario admite cualquier importe mayor que cero que no supere el total exigible de las cuotas seleccionadas (consecutivas desde la más antigua de cada crédito). Se imputa por antigüedad y por la prelación de RN-29; la cuota alcanzada parcialmente sigue Pendiente o Vencida por su saldo, conserva su vencimiento y su mora. Los punitorios corren sobre el capital vencido que quede pendiente (RN-34), y la tolerancia se evalúa sobre la fecha en que la cuota se cancela por completo (RN-53). El pago parcial no es una cancelación anticipada parcial: no reduce intereses ni recalcula el plan (1.5.2). En el portal (CU-49) se mantienen las órdenes por cuotas completas en esta versión.

**Consecuencias.** `Imputador::imputar` admite importes menores al total y rechaza los mayores; nueva función `saldoDespues`; 3 pruebas nuevas. La columna `imputaciones.parcial_excepcional` pasa a llamarse `parcial` (migración 10). Cambian RN-29, RN-53, RN-56, RN-57, CU-33 (A1) y el glosario.

## D-13 · Parámetros del motor de evaluación editables por la prestadora (CU-58)

**Contexto.** Se pidió que los valores del motor que trabaja con la Central de Deudores del BCRA sean parametrizables: los de RN-69 sirven como base, pero una prestadora con otras políticas debe poder cambiarlos. La v8 ya los declaraba configurables (RN-69, CU-58) y el código los lee de `versiones_parametros`, pero CU-58 no detallaba qué se edita ni cómo se valida.

**Decisión.** CU-58 enumera los parámetros editables: pesos de los cuatro indicadores; valor por situación BCRA (1 a 6 y sin deudas); situaciones vigentes excluyentes; tramos de atraso interno, de endeudamiento y de antigüedad con sus valores; cortes de las categorías A a E y su resultado; y vigencia de la evaluación. Validaciones: pesos entre 0 % y 100 % que suman 100 %; valores enteros de 0 a 100; tramos ordenados; cortes estrictamente descendentes con E desde 0; A no puede ser Desfavorable ni E Favorable; vigencia de 1 a 365 días. Los valores de RN-69 quedan como **valores sugeridos**, que el Gerente puede volver a cargar en el formulario y registrar como una versión nueva con fecha y motivo.

**Consecuencias.** `ValidadorParametrosEvaluacion` con 6 pruebas; pantalla CU-58 en el panel (`admin/parametros/evaluacion`) y `ParametroService::registrarVersion`. Sin cambios de esquema.

## D-14 · Documentación exigida por la línea: créditos solo con DNI (RN-11)

**Contexto.** En la presentación se discutió que muchas prestadoras otorgan préstamos de bajo monto pidiendo solo el documento de identidad. La v8 permitía configurar la documentación por línea, pero el alta del cliente (CU-11 A4) exigía la documentación base de su categoría de ingresos (por ejemplo, los tres últimos recibos de sueldo), así que un cliente que se presentaba solo con su DNI no podía registrarse.

**Decisión.** Se separan dos momentos. En el alta del cliente solo es obligatoria su identificación: documento de identidad para la persona humana; constancia de CUIT, documento del representante e instrumento de representación para la persona jurídica. La documentación de ingresos la define cada versión de condiciones de la línea y se exige al registrar la solicitud (CU-22) y se verifica en CU-23. La documentación base de RN-11 pasa a ser la que el sistema propone al configurar una línea; la línea puede quitarla y exigir solo el documento de identidad, que es obligatorio en todas. El riesgo de una línea «solo con DNI» se controla con la evaluación del BCRA (RN-69), la capacidad de pago sobre ingresos declarados y el importe máximo de la línea (RN-76).

**Consecuencias.** Sin cambios de esquema (`requisitos_documentales` ya es por versión y categoría). `RequisitosDocumentales`: `identificacion()`, `sugeridos()`, `faltantes()` sobre una lista y `exigidosPorLinea()` para CU-22 y CU-23; `ClienteService::registrar` exige solo la identificación; `ValidadorLinea` exige el documento de identidad en cada categoría. Cambian RN-11, CU-11 (paso 3 y A4, A8), CU-16 (paso 2 y A3), CU-22 (paso 8 y A4) y CU-23 (paso 3).

## D-15 · Persistencia desde los servicios con el Query Builder, sin modelos por tabla (4.4.2, 4.4.3)

**Contexto.** La vista lógica de la v9 preveía una capa de persistencia con un modelo de CodeIgniter por tabla y entidades con conversión de tipos. Al construir la iteración 1, cada operación de servicio resultó necesitar, dentro de una misma transacción, lecturas con bloqueo de fila (`WITH (UPDLOCK, ROWLOCK)`), actualizaciones condicionadas por `version_fila`, consultas que combinan varias tablas (por ejemplo, la cuenta de acceso con el usuario, el rol y el cliente) y conversiones propias de SQL Server (`VARBINARY` de la cola de comunicaciones). Los modelos de CodeIgniter no expresan esos bloqueos, duplican el control de concurrencia de `BaseService` y no aportan reglas de negocio, que ya están en los servicios y en `app/Libraries`.

**Decisión.** Los servicios acceden a la base con el Query Builder y con consultas parametrizadas (`?`), sin modelos ni entidades por tabla. `BaseService` concentra la transacción (`transaccion()`), el bloqueo (`bloquear()`), la actualización con versión (`actualizarConVersion()`) y las fechas (`ahoraUtc()`, `hoyNegocio()`). Las reglas puras de cada área se ubican en `app/Libraries/<Área>` (Seguridad, Clientes, Lineas, Validacion, Alertas, Comunicaciones, Caja, además de Finanzas). Los importes siguen viajando como cadenas decimales y nunca como `float`.

**Consecuencias.** Sin cambios de esquema. `app/Models` queda vacío y no existe `app/Entities`. Se mantienen RNF-05 (sin reglas en controladores ni vistas) y RNF-12 (sin concatenar datos del usuario en el SQL). Cambian 4.4.2 (fila Persistencia e Imagen 47) y 4.4.3 (filas de controladores, filtros, bibliotecas, excepciones, vistas, adjuntos y pruebas) en la v10.

## D-16 · Activación del portal al verificar el correo del cliente (CU-04, CU-11)

**Contexto.** En la prueba funcional de la iteración 1, el cliente recién registrado abría el enlace de verificación del correo y la pantalla solo le confirmaba la verificación: para definir su contraseña debía ir por su cuenta a «Activar o rehabilitar acceso», volver a ingresar su documento y su correo y esperar un segundo correo. El recorrido se percibía como un bloqueo («me pide verificar el correo y no me deja crear la contraseña»). Además, el correo usaba la plantilla de los usuarios internos.

**Decisión.** Al confirmar el correo desde el enlace (CU-11 paso 8), si la cuenta del cliente está Pendiente de activación, o Desactivada con el cliente activo en el padrón, el sistema emite en ese momento la autorización de activación o de rehabilitación y lleva al cliente directamente a definir su contraseña (CU-04 paso 7). La autorización no se envía por correo, porque el cliente acaba de demostrar que lo controla con otra autorización de un solo uso (RN-03); se aplican las mismas reglas del paso 4 (RN-10, RN-62) y queda auditada. Si la cuenta ya está habilitada (cambio de correo, CU-12), la verificación termina en el inicio de sesión. La activación desde «Activar o rehabilitar acceso» con documento y correo se mantiene para quien no tenga el enlace. El correo de verificación de los clientes tiene su propia plantilla, que anticipa este paso.

**Consecuencias.** Sin cambios de esquema. `AutorizacionService::emitirSinComunicacion()`, `ActivacionService::continuarTrasVerificacion()` y la plantilla `verificacion_correo_cliente`. Cambian CU-04 (A6, activación desde la verificación del correo), CU-11 (paso 8) y la tabla de realizaciones de 4.5.1 en la v11.

## D-17 · Evaluación automática en dos transacciones y registro de la contingencia (CU-86, CU-25)

**Contexto.** Al construir la evaluación crediticia (D02) aparecieron tres situaciones que la v11 no detalla. La consulta a la Central de Deudores puede demorar hasta 30 segundos (10 s por petición y dos reintentos, RNF-08): si la evaluación completa se hiciera en una sola transacción, la solicitud quedaría bloqueada durante la espera. CU-86 A7 indica que ante un error interno la solicitud queda Pendiente de evaluación, pero el diagrama de estados de 4.2.3 no tiene la transición de En evaluación a Pendiente de evaluación, ni el de 4.2.4 prevé un intento que quede En proceso porque el proceso se interrumpió. Por último, el esquema exige que la contingencia tenga resultado nulo o «Requiere análisis gerencial», pero CU-25 no dice cuál de los dos registra, ni dónde se guardan los antecedentes verificables que describe el Analista.

**Decisión.**
1. CU-86 se ejecuta en dos transacciones por solicitud: la primera registra el intento En proceso con la versión de parámetros vigente y pasa la solicitud a En evaluación; la consulta externa se hace fuera de toda transacción; la segunda calcula los indicadores y aplica el resultado solo si la solicitud sigue En evaluación (si no, el resultado queda Finalizado con vigencia Invalidada como antecedente, A4 y A5). Para no producir bloqueos mutuos, se bloquea siempre primero la solicitud y después la evaluación.
2. Un error interno posterior al inicio, o un intento En proceso con más de 15 minutos (ejecución interrumpida), deja el intento No completado con su motivo y devuelve la solicitud a Pendiente de evaluación, sin alerta, para la próxima ejecución. Se agrega la transición En evaluación → Pendiente de evaluación (A7). La falta de respuesta del servicio externo sigue llevando a Pendiente de evaluación de contingencia, sin reintento automático (A3, RN-21).
3. El inicio inmediato al verificar un legajo (CU-86 paso 1) toma el mismo bloqueo de aplicación que el proceso programado; si el proceso está en curso, no hace nada y la solicitud se evalúa en la ejecución siguiente (cada 5 minutos).
4. La evaluación de contingencia se registra Finalizada, Vigente por la vigencia configurada y con resultado «Requiere análisis gerencial»: la recomendación del Analista (Favorable o Desfavorable) y su justificación se guardan aparte y no aprueban la solicitud (RN-21). Los tipos de antecedentes verificables utilizados y su descripción se guardan con los antecedentes internos del cliente en `indicadores`; las deudas que el Analista transcribe de una consulta manual a la Central de Deudores se guardan en `antecedentes_externos` con fuente «Manual».
5. Indicadores internos de RN-69: el comportamiento de pago es el máximo atraso de las cuotas vencidas en los últimos 12 meses (las pagadas, hasta la fecha efectiva del pago; las impagas, hasta hoy) y no hay antecedentes si no hay cuotas vencidas en ese período; las obligaciones son la próxima cuota impaga de cada crédito Activo o En mora más la primera cuota de las demás solicitudes en trámite del cliente (RN-18). De la Central de Deudores se toman los períodos desde doce meses antes del mes en curso; la situación vigente es la peor del último período informado.

**Consecuencias.** Sin cambios de esquema. `EvaluacionService`, comando `evaluacion:ejecutar` (cada 5 minutos en el planificador), `AntecedentesBcra`, `DimensionesEvaluacion`, `Contingencia` y el adaptador `BcraInformacionCrediticia` (API pública de la Central de Deudores, `sigccr.adaptadorInformacionCrediticia = real`). Cambian el diagrama de estados de la solicitud de 4.2.3 (nueva transición), el de la evaluación de 4.2.4 (EnProceso → NoCompletada por error interno o interrupción), CU-86 (A7) y CU-25 (paso 7, resultado registrado).

## D-18 · Domicilio del cliente con provincia y localidad (CU-11, CU-12)

**Contexto.** En la prueba de la iteración 2 se observó que el domicilio del cliente era un único texto libre. Una sucursal atiende clientes de varias localidades cercanas, a veces de otra provincia: por ejemplo, desde Apóstoles (Misiones) se atiende a Azara y San José, en Misiones, y a Colonia Liebig, en Corrientes. Con un texto libre no se puede saber con certeza de qué localidad es cada cliente, ni agruparlos para reportes o campañas.

**Decisión.** El domicilio se compone de provincia, localidad, código postal (opcional) y calle y número. La provincia y la localidad se eligen de un catálogo y son obligatorias en el alta (CU-11) y en cada modificación (CU-12). Los clientes registrados antes del cambio conservan su domicilio y completan la localidad en su próxima modificación. El catálogo usa los códigos oficiales: las provincias, con su código INDEC, y las localidades, del catálogo de localidades censales del Servicio de Normalización de Datos Geográficos (georef, datos.gob.ar). La instalación carga las 24 jurisdicciones y una lista base de localidades (las capitales, la zona de Apóstoles y el Gran Resistencia). El comando `php spark geografia:importar` incorpora el catálogo completo, descargado o desde un archivo; puede repetirse y no elimina localidades que ya estén en uso. El código postal admite los cuatro dígitos o el CPA de ocho caracteres.

**Consecuencias.** Migración 13: tablas `provincias` y `localidades` en SS-10 y columnas `clientes.localidad_id` y `clientes.codigo_postal`; `domicilio` conserva la calle y el número. Queda en 75 tablas. `CatalogoGeografico` (con 6 pruebas), `GeografiaService`, `GeografiaSeeder` y el comando `geografia:importar`. El formulario del cliente carga las localidades al elegir la provincia. Cambian CU-11 (paso 3), CU-12, el modelo de dominio (`Cliente` con `Localidad`), 4.3 (tablas de SS-10 y SS-02, totales) y el glosario.

## D-19 · Alternativas de financiación, aprobación automática y reserva de fondos (CU-87, CU-64, CU-79)

**Contexto.** Al construir las alternativas (D03) quedaron cuatro definiciones sin precisar en la v11. CU-87 combina el monto máximo y sus escalones con *todas* las cantidades de cuotas admitidas: una línea de 3 a 24 cuotas daría 66 combinaciones, demasiadas para presentarlas al cliente. Tampoco se dice si la combinación que el cliente pidió en la solicitud debe figurar entre las alternativas. CU-79 exige verificar «las condiciones de aprobación automática según los parámetros vigentes», pero CU-58 y CU-59 no definen ningún parámetro de ese tipo. Por último, RN-41 pide reservar fondos sin indicar el importe.

**Decisión.**
1. **Plazos sugeridos.** Las alternativas se generan sobre los plazos sugeridos de la línea: el mínimo, el máximo y los plazos habituales intermedios (3, 6, 9, 12, 18, 24, 30, 36, 48 y 60 cuotas). Se agrega el plazo de la solicitud si no está entre ellos. Con los escalones iniciales (100 %, 75 % y 50 % del monto máximo), una línea de 3 a 24 cuotas da hasta 18 combinaciones.
2. **Combinación pedida.** La combinación pedida en la solicitud se presenta primero cuando no supera el monto máximo financiable. Las combinaciones cuya primera cuota con IVA supera la capacidad se conservan descartadas, con su motivo y su costo (RF-15). Una nueva generación descarta las alternativas vigentes de la anterior.
3. **Aprobación automática (RN-23).** Se aprueba automáticamente cuando:
   - la evaluación sigue vigente;
   - la alternativa está vigente;
   - su primera cuota entra en la capacidad de pago recalculada con las obligaciones actuales del cliente (RN-18);
   - la línea está activa (RN-16);
   - el disponible libre alcanza para el desembolso (RN-71).

   No hay otros parámetros de aprobación automática. Si la línea no está activa o faltan fondos, la confirmación queda registrada y la solicitud pasa a Pendiente de resolución con la causa y una alerta al Gerente (A1, A4). Si la evaluación perdió vigencia, la solicitud vuelve a Pendiente de evaluación (A5).
4. **Reserva de fondos (RN-41).** La reserva es por el importe neto a desembolsar (capital menos gasto de otorgamiento y su IVA), que es lo que sale de los fondos al liquidar. El disponible libre de RN-71 se calcula como:
   - movimientos de fondos realizados (ingresos menos egresos);
   - más los importes imputados por cobros confirmados y aplicaciones de saldo;
   - menos desembolsos, devoluciones completadas, reservas y compromisos vigentes;
   - menos la reserva mínima de los parámetros de campañas.

   La reserva se constituye con un bloqueo de aplicación, para que dos aprobaciones simultáneas no usen los mismos fondos.
5. **Vencimiento de la aprobación.** Son 72 horas hábiles con el calendario operativo (RN-49), que se conservan en la resolución automática.

**Consecuencias.** Sin cambios de esquema. Componentes nuevos:
- `Estructurador`, con 6 pruebas.
- `EstructuracionService` (generar, regenerar, consultar y confirmar).
- `FondosService` (disponibilidad y reserva).
- `ComparativaService` (CU-99, con `ComparadorLineas`).

CU-86 genera las alternativas al resultar favorable la evaluación y, si no hay viables, deriva a resolución (A6). Hasta construir CU-85 (D05), los fondos de la entidad se registran como movimientos de fondos. Cambian CU-87 (paso 5), CU-79 (pasos 7 a 9) y RN-41 (importe de la reserva).

## D-20 · Plan de la aprobación gerencial, conformidad al liquidar e identidad del destinatario (CU-26, CU-27, CU-28)

**Contexto.** Al construir la resolución, la liquidación y la cancelación (D04) quedaron cinco puntos sin precisar en la v11:
- **Plan definido por el Gerente.** CU-26 A8 permite que el Gerente defina el importe y las cuotas cuando no hay una alternativa confirmada, pero `resoluciones` solo referencia alternativas.
- **Conformidad.** CU-27 exige registrar la conformidad del cliente con ese plan, sin decir dónde se guarda.
- **Identidad.** Pide verificar la identidad del destinatario sin indicar cómo.
- **Datos de la transferencia.** Pide registrar el CUIT del titular, la evidencia de titularidad y el comprobante, pero `liquidaciones` solo tiene la cuenta y un comprobante de texto.
- **Tipo de rechazo.** Tampoco se dice qué tipo de rechazo corresponde a la decisión del Gerente.

**Decisión.**
1. **Plan aprobado.** Toda aprobación referencia una alternativa:
   - la confirmada por el cliente;
   - una alternativa vigente que elige el Gerente;
   - o la combinación que define el Gerente.

   En el último caso se valida contra los límites de la línea (RN-76) y la capacidad de pago actual. Después se genera una estructuración nueva con la capacidad de ese momento y la combinación se agrega como alternativa vigente.
2. **Conformidad al liquidar (RN-22).** Si la alternativa aprobada no está confirmada, el Cajero marca la conformidad del cliente al liquidar. La alternativa queda Confirmada con la fecha de la liquidación y el medio «Presencial, al liquidar».
3. **Identidad del destinatario.** El Cajero reingresa el número del documento que presenta la persona. Debe coincidir con el del cliente o, si es una persona jurídica, con el de su representante registrado (RN-25).
4. **Transferencia.** La cuenta debe ser un CBU o CVU con dígitos verificadores válidos y el CUIT/CUIL del titular debe ser el del cliente. El importe transferido debe ser el neto a desembolsar. Se guardan:
   - el número de operación y la fecha, en `comprobante`;
   - la evidencia de titularidad, en la auditoría.
5. **Rechazo gerencial.** Se registra como rechazo crediticio. La aprobación, el rechazo y la devolución liberan las afectaciones previas. La aprobación constituye la reserva por el neto (D-19). Si los fondos no alcanzan, la aprobación no se registra y el Gerente debe rechazar o devolver (A4).
6. **Liquidación (RN-26, RN-52).** En una sola transacción se registran:
   - el crédito, con su plan definitivo de cuotas (primer vencimiento a los 30 días);
   - la liquidación;
   - el egreso de caja, si es en efectivo, con la caja abierta del Cajero (RN-28);
   - el consumo de la reserva.

   La solicitud pasa a Liquidada. El régimen de morosidad (tolerancia) se toma de los parámetros vigentes al liquidar (RN-35).

**Consecuencias.** Sin cambios de esquema. Componentes nuevos:
- `ResolucionGerencial` y `Desembolso`, con 6 pruebas.
- `ResolucionService` (CU-26 y CU-28) y `LiquidacionService` (CU-27).
- `EstructuracionService::alternativaParaAprobar`.
- `FondosService::liberarDeSolicitud` y `consumir`.

El desembolso en efectivo requiere la apertura de caja (CU-31), que se construye en D05; hasta entonces se puede liquidar por transferencia. Cambian CU-26 (A8), CU-27 (pasos 4 y 6, A7) y RN-22.

## D-21 · Vigencia de las asignaciones de caja y corrección de movimientos de fondos (CU-72, CU-80, CU-85)

**Contexto.** Al construir cajas y fondos (D05) se encontraron cuatro diferencias entre los casos de uso y el esquema:
- **Vigencia de la asignación.** El esquema consideraba vigente una asignación «mientras su fin es nulo», pero CU-72 permite cargar una fecha de fin. Además, una asignación finalizada el mismo día en que comenzó seguiría vigente ese día.
- **Observaciones de la caja.** CU-80 pide observaciones, y la tabla `cajas` no tiene esa columna.
- **Correcciones de fondos.** CU-85 corrige un movimiento con un «movimiento inverso vinculado» y exige motivo para corregir o anular, pero `movimientos_fondos` no tiene vínculo ni motivo.
- **Jornada de días anteriores.** RN-28 exige cerrar la jornada de un día anterior antes de operar, sin decir qué operaciones lo verifican.

**Decisión.**
1. **Vigencia de la asignación.** Una asignación está vigente desde su fecha de inicio hasta su fecha de fin inclusive, o sin fin. Finalizarla (CU-72 A5, o al inhabilitar la caja en CU-81) fija el fin en el día anterior. Si todavía no había comenzado, queda con un período vacío (fin = inicio − 1), que el CHECK ahora admite. Una caja y un Cajero no pueden tener otra asignación vigente o futura.
2. **Observaciones de la caja.** `cajas.observaciones` guarda las observaciones del alta.
3. **Correcciones de fondos.** `movimientos_fondos.movimiento_corregido_id` (única) y `motivo` registran la corrección:
   - el movimiento inverso tiene el mismo tipo e importe y el carácter opuesto, y se fecha hoy;
   - un movimiento se corrige una sola vez y una corrección no se corrige;
   - un movimiento programado no se corrige: se confirma cuando llega su fecha o se anula con motivo.

   Un egreso realizado, una corrección que resulta en egreso y la confirmación de un egreso programado no pueden superar el disponible libre (A1). Se usa el mismo bloqueo de aplicación que las reservas.
4. **Jornada de días anteriores.** Un Cajero solo puede registrar operaciones (desembolsos en efectivo y, en la iteración 3, cobros) en una jornada abierta hoy, con la misma asignación con que la abrió y todavía vigente. La jornada de un día anterior se puede cerrar, pero no operar. El fondo inicial, si es mayor que cero, queda como movimiento de la jornada.

**Consecuencias.** Migración 14 (`cajas.observaciones`, `movimientos_fondos.movimiento_corregido_id` y `motivo`, y el CHECK de `asignaciones_caja`). Componentes nuevos:
- `ReglasCaja`, con 5 pruebas.
- `CajaService` (CU-31, CU-36, CU-72, CU-80, CU-81).
- `FondosService` (CU-85).
- Pantallas Mi caja, Cajas y Fondos.

`LiquidacionService` usa `CajaService::jornadaOperable`. Cambian CU-72 (A5), CU-80 (paso 2), CU-85 (A2, A3), RN-28 y el modelo de datos (4.3).

## D-22 · Parámetros de estructuración, vencimientos automáticos y calendario operativo (CU-59, CU-89, CU-82)

**Contexto.** Al construir D06 quedaron sin precisar en la v11:
- **CU-59.** Enumera categorías de parámetros («tratamiento de obligaciones», «criterios de exclusión de ingresos», «combinaciones permitidas») sin definir sus valores. El motor solo usa el porcentaje de afectación, la alícuota de IVA, el redondeo, los escalones y la vigencia de las alternativas.
- **CU-89.** No define qué solicitudes «dependen» de una evaluación vencida, ni el orden entre el vencimiento de la evaluación y el de la aprobación.
- **CU-82.** No dice si se puede dar de baja el día de hoy.

**Decisión.**
1. **Parámetros de estructuración (CU-59).** Son seis:
   - porcentaje máximo de afectación (1 % a 100 %);
   - alícuota de IVA (0 % a 100 %);
   - vigencia de las alternativas (1 a 30 días corridos);
   - múltiplo de redondeo del monto máximo;
   - escalones de importe (hasta 6, el primero 100 % y decrecientes);
   - tope global por cliente (opcional).

   El tope limita el monto máximo financiable de todas las líneas en las alternativas (CU-87), en la comparación de líneas (CU-99) y en el plan que define el Gerente (CU-26). Los demás criterios de CU-59 quedan fijos en esta versión: se consideran los ingresos declarados y las cuotas vigentes y comprometidas de RN-18. Cada cambio es una versión con fecha de vigencia no retroactiva y las operaciones ya generadas conservan la suya (RN-15).
2. **Vencimientos (CU-89).** Cada 15 minutos, cada elemento en su propia transacción y releído con bloqueo (A1):
   - Las simulaciones y alternativas vencidas pasan a Vencida.
   - Las autorizaciones de acceso vencidas pasan a Vencida.
   - Primero se procesan las aprobaciones vencidas: la solicitud pasa a Vencida y se libera la reserva. Si el vencimiento de la aprobación coincide con el de la evaluación, prevalece la aprobación (RN-49).
   - Después, las evaluaciones vencidas. Si la solicitud está en Pendiente de selección de plan, Pendiente de resolución o Aprobada pendiente de liquidación, vuelve a Pendiente de evaluación: la aprobación queda sin efecto, se libera la reserva y vencen sus alternativas. La evaluación automática la vuelve a evaluar en su próxima ejecución.
   - Por último, las solicitudes en Pendiente de documentación o Devuelta para revisión sin movimientos durante 30 días corridos pasan a Vencida (RN-72).

   Cada cambio genera una alerta de vencimiento al Analista que registró la solicitud. Las propuestas de refinanciación, las órdenes de pago y las devoluciones se incorporan en la iteración 3.
3. **Calendario operativo (CU-82).** Se pueden agregar fechas desde hoy en adelante, de a una o con la carga anual. La carga anual tiene una línea por fecha («dd/mm/aaaa; descripción; tipo»); cada fecha se valida por separado y se informan las rechazadas. Se pueden dar de baja días de hoy en adelante. Los vencimientos ya calculados no cambian.
4. **Localización y consulta (CU-29, CU-30).**
   - **Criterios de búsqueda:** documento o CUIT/CUIL, línea y rango de fechas de registro, con la validación del rango (A2).
   - **Cajero (A3):** sin permiso de consulta, el número de la solicitud lo lleva a la liquidación cuando corresponde.
   - **Consulta:** suma las alternativas, las afectaciones de fondos y las alertas de la solicitud, y marca como «No registrada» lo que todavía no existe (A4).

**Consecuencias.** Sin cambios de esquema. Componentes nuevos:
- `ValidadorParametrosEstructuracion` y `CalendarioOperativo`, con 4 pruebas, más 1 prueba del tope en `EstructuracionTest`.
- `CalendarioService` y `VencimientoService`.
- Comando `vencimientos:controlar`, habilitado en el planificador.
- Pantallas de parámetros de estructuración y del calendario operativo.

Cambian CU-59 (paso 2), CU-89 (pasos 4 y 5), CU-82 y CU-29 (paso 2).

## D-23 · Ajustes de validación y de presentación observados en la presentación del 02/11/2026 (CU-16, CU-17, CU-21, CU-26, CU-99)

**Contexto.** En la presentación del avance se observó que:
- una línea admite el mismo importe mínimo y máximo, la misma cantidad mínima y máxima de cuotas y la misma edad mínima y máxima;
- el simulador no muestra los datos del cliente que sustentan la operación;
- un plan de muchas cuotas obliga a desplazarse mucho para recorrerlo;
- el Gerente no puede abrir la documentación del legajo desde la pantalla de resolución.

**Decisión.**
1. **Rangos de la línea (RN-14).** El máximo debe ser estrictamente mayor que el mínimo en el importe prestable, en la cantidad de cuotas y en las edades admitidas. La restricción se aplica en la validación y en las restricciones CHECK de `versiones_condiciones`; las versiones ya registradas no se modifican.
2. **Datos del cliente en la simulación y la comparación (CU-21, CU-99).** Se muestran el documento o CUIT, la edad (o la antigüedad de la empresa), la situación laboral (categoría de ingresos), el empleador o la actividad, los ingresos declarados, las cuotas vigentes y comprometidas y la capacidad de pago con los parámetros vigentes. Es información interna: no consulta la Central de Deudores (RN-17).
3. **Plan de cuotas paginado.** Con más de 12 cuotas, el plan se presenta de a 12 por página, con la opción de ver 24, 60 o todas; al imprimir se muestran todas.
4. **Documentación en la resolución (CU-26).** La pantalla de resolución lista los documentos vigentes del legajo con su resultado en la última verificación y los abre en otra pestaña, sin descargarlos.

**Consecuencias.** Cambian `ValidadorLinea`, `SimulacionService::perfil`, la vista parcial del plan, la de resolución y la descarga de documentos (`?ver=1` los muestra en el navegador). Cambian RN-14 y CU-16, CU-17, CU-21, CU-26 y CU-99.

## D-24 · Confirmación de cambios con solicitudes en trámite (CU-17, CU-58, CU-59)

**Contexto.** Una nueva versión de condiciones de una línea o de los parámetros de evaluación o estructuración no altera las operaciones ya generadas (RN-15), pero quien la registra no sabe cuántas solicitudes en trámite quedan con la versión anterior.

**Decisión.** Si hay solicitudes en trámite (estados no finales) con la versión vigente de la línea, o cualquier solicitud en trámite al cambiar los parámetros de evaluación o de estructuración, el sistema informa cuántas son, explica que conservan su versión y exige marcar una confirmación antes de registrar el cambio. Sin solicitudes en trámite, el cambio se registra directamente.

**Consecuencias.** `LineaService::modificar` recibe la confirmación; el controlador de parámetros cuenta las solicitudes en trámite (`ParametroService::solicitudesEnTramite`). Se agrega un paso alternativo a CU-17, CU-58 y CU-59.

## D-25 · Catálogo de documentación configurable (CU-100; CU-11, CU-16, CU-17)

**Contexto.** La exigencia de documentación por línea y categoría ya se configura en cada versión de condiciones (D-14), pero el catálogo de tipos de documento y la documentación base que se propone para cada categoría estaban fijos en el código. En la presentación se pidió poder agregar documentos que no figuran, por ejemplo uno más para relación de dependencia.

**Decisión.**
1. **Tipos de documento.** El Gerente registra tipos nuevos (nombre, a qué tipo de cliente corresponde y si es documento clave, RN-61); el código se genera a partir del nombre. Los tipos no se eliminan: se desactivan, porque los legajos y las versiones de condiciones los referencian. La identificación del alta (D-14) no se puede desactivar, y a quién corresponde un tipo no cambia si ya hay documentos de ese tipo en legajos.
2. **Documentación base por categoría.** Se guarda en la tabla `documentacion_sugerida` y la propone el formulario de líneas; debe incluir la identificación del cliente de cada categoría. Cambiarla no altera las versiones de condiciones registradas.
3. **Nuevo caso de uso.** CU-100 Configurar Catálogo de Documentación, del Gerente.

**Consecuencias.** Tabla `documentacion_sugerida` (migración 15), `DocumentacionService`, pantalla «Documentación exigible» y `RequisitosDocumentales::validarSugeridos`. La lista inicial pasa a `RequisitosDocumentales::SUGERIDOS_INICIALES` y la carga la instalación. Cambian RN-11, la matriz de participación (3.6.2) y el modelo de datos (4.3).

## D-26 · Categorías del monotributo (CU-101; CU-11, CU-12)

**Contexto.** Para un cliente monotributista el sistema no registra su categoría del Régimen Simplificado, que acota sus ingresos.

**Decisión.**
1. **Escala versionada.** Las categorías A a K se guardan con su tope de ingresos brutos anuales y la fecha desde la que rige (`categorias_monotributo`), porque ARCA las actualiza cada semestre. La instalación carga la escala vigente desde el 01/08/2026. El Gerente registra cada nueva escala con fecha de hoy en adelante (CU-101 Actualizar Categorías del Monotributo); cada tope debe ser mayor que el de la categoría anterior.
2. **Cliente monotributista.** Al registrarlo o modificarlo se informa su categoría, obligatoria desde esta decisión. Doce veces los ingresos mensuales declarados no pueden superar el tope de la categoría vigente: los ingresos que se declaran son netos y el tope es de ingresos brutos, así que superarlo indica una inconsistencia.

**Consecuencias.** Tabla `categorias_monotributo` y columna `clientes.categoria_monotributo` (migración 15), `CategoriasMonotributo`, `MonotributoService` y la pantalla «Categorías del monotributo». Cambian CU-11 y CU-12 (validación), la matriz de participación y el modelo de datos.

## D-27 · Desembolso solo por transferencia a una cuenta bancaria del cliente (CU-27; cobros de la iteración 3)

**Contexto.** El artículo 1 de la Ley 25.345 (prevención de la evasión fiscal) dispone que los pagos de sumas superiores a $ 1.000 que no se realicen mediante depósitos en entidades financieras, giros o transferencias bancarias, cheques, tarjetas u otros medios que indique el Poder Ejecutivo no surten efectos entre las partes ni frente a terceros. El monto no se actualizó desde 2001, por lo que alcanza a prácticamente cualquier desembolso o cuota. Además, el director pidió que las transferencias se hagan solo a cuentas bancarias, no a CVU de billeteras virtuales.

**Decisión.**
1. **Desembolso (CU-27).** Se realiza solo por transferencia a una cuenta bancaria del cliente, identificada con un CBU válido de 22 dígitos cuyo titular es el cliente. Se rechazan las claves que comienzan con 000 (CVU de proveedores de servicios de pago). Se elimina el desembolso en efectivo por caja.
2. **Cobros (iteración 3).** Las cuotas se cobran por transferencia o depósito bancario con comprobante, sin efectivo. La caja deja de manejar efectivo y se rediseña en la iteración 3 como registro y conciliación de los movimientos bancarios; hasta entonces sus pantallas no participan del desembolso.

**Consecuencias.** Cambian `Desembolso` (un solo medio, `esCvu`), `LiquidacionService` (sin egreso de caja) y la pantalla de liquidación. Las liquidaciones en efectivo ya registradas se conservan. Cambian CU-27 (pasos 5 y A4), RN-28 y el alcance de CU-31, CU-32, CU-33 y CU-36, que se revisan en la iteración 3.

## D-28 · Gasto administrativo fijo o porcentual (CU-16, CU-17; CU-21, CU-87, CU-99, CU-27)

**Contexto.** RN-68 define el gasto administrativo como un importe fijo por cuota. En la presentación se pidió que la línea pueda definirlo también como porcentaje.

**Decisión.**
1. **Dos modalidades.** Cada versión de condiciones define el gasto administrativo como importe fijo por cuota o como porcentaje del capital otorgado que se cobra en cada cuota, hasta el 10 % (por ejemplo, 0,5 % de $ 800.000 son $ 4.000 más IVA en cada cuota). En los dos casos el gasto es igual en todas las cuotas y la cuota del sistema francés se mantiene constante. Lleva IVA (RN-68) e integra el CFTEA (RN-63).
2. **Capital máximo (CU-87, CU-99).** Con gasto porcentual, el capital máximo para n cuotas es C ≤ (K − g_fijo · (1 + IVA)) / (1 / a(n, i) + IVA · i + p · (1 + IVA)), donde p es el porcentaje; el resultado se verifica con el plan real, como en D-01.
3. **Versiones anteriores.** Las versiones registradas antes de esta decisión son de importe fijo.

**Consecuencias.** Columnas `versiones_condiciones.tipo_gasto_administrativo` y `gasto_administrativo_pct` con su restricción de consistencia (migración 15). Componente `GastoAdministrativo`, usado por `Simulador`, `CapacidadPago`, `ComparadorLineas`, `Estructurador` y `LiquidacionService`, con 4 pruebas nuevas. Cambian RN-68, CU-16 y CU-17 (paso de condiciones), el modelo de datos y los casos de prueba del motor financiero.

## D-29 · Requerimientos nuevos diferidos a la iteración 3

**Contexto.** En la presentación del 02/11/2026 se pidieron, además de los ajustes de D-23 a D-28:
- que el cliente pueda registrarse por su cuenta;
- que los datos personales se completen a partir del CUIL;
- que el sistema emita un documento de consentimiento del préstamo para firmar, que se escanea y se incorpora al legajo;
- un estudio de los requisitos legales del BCRA y de la legislación argentina.

**Decisión.**
1. **Pre-registro en el portal.** El cliente carga sus datos y su documentación; queda «Pendiente de validación» hasta que el Analista verifica su identidad y lo da de alta (CU-11). No puede solicitar crédito hasta entonces.
2. **Datos a partir del CUIL.** Se define un adaptador de padrón (como el de la Central de Deudores, RNF-05), con un simulador para desarrollo y pruebas y la conexión al padrón de ARCA (servicio `ws_sr_padron_a13`, que requiere certificado y CUIT de la entidad) como implementación real. Los datos obtenidos se proponen en el formulario y el Analista los confirma.
3. **Constancia de consentimiento.** Al aprobarse la solicitud se genera el contrato o la solicitud de préstamo para firmar, con los datos que exige la normativa (estudio legal). El Cajero incorpora la copia firmada y escaneada al legajo, y sin ella no se puede liquidar.
4. **Estudio legal.** Se elabora como documento del proyecto y alimenta las reglas de negocio y el contenido del contrato.

**Consecuencias.** Se agregan casos de uso al SS-02 (pre-registro), al SS-04 (constancia firmada) y un adaptador al SS-11. Se replanifica la iteración 3.

## D-30 · Caja y cobros sin efectivo (CU-31 a CU-37, CU-56, CU-97; RN-28, RN-29, RN-32)

**Contexto.** D-27 eliminó el efectivo del desembolso y difirió a la iteración 3 el rediseño de la caja. El art. 1 de la Ley 25.345 impide recibir pagos de más de $ 1.000 en efectivo, y la presentación pidió que todo pago se haga por medios bancarios.

**Decisión.**
1. **Sin efectivo.** Los cobros se reciben por transferencia o depósito en una cuenta de la entidad, tarjeta de débito o de crédito, o la pasarela, siempre con número de operación (RN-77). No hay cambio.
2. **Apertura sin fondo inicial (CU-31).** La jornada se abre con la asignación vigente, sin importe (RN-28).
3. **Cierre como conciliación (CU-36).** Para cada medio, el Cajero informa el total acreditado según el extracto o el resumen del procesador; el sistema calcula la diferencia con lo registrado y exige su explicación (RN-32). CU-32 y CU-56 informan la conciliación en lugar del arqueo.
4. **Registros anteriores.** Las columnas del efectivo, el cambio y el fondo inicial se conservan para los registros anteriores; la migración de la iteración 3 impide nuevos valores.

**Consecuencias.** Cambian `CajaService` (`abrir()` sin fondo, `cerrar()` con la conciliación), `MediosCobro` (sin efectivo ni cambio; la suma de los medios debe igualar el importe), `ReglasCaja` (`totalesPorMedio()` y `validarConciliacion()` en lugar del arqueo) y las pantallas de caja. La migración 16 agrega `conciliaciones_jornada` y restricciones WITH NOCHECK sobre `jornadas_caja`, `cobros`, `cobros_medios`, `movimientos_caja` y `liquidaciones`. Cambian CU-31 a CU-33, CU-35, CU-36, CU-56, CU-67, CU-77, CU-97, RN-28, RN-29 y RN-32 (documento v12).

## D-31 · Contrato, revocación, identificación del cliente y régimen informativo (CU-102 a CU-105; RN-77 a RN-84)

**Contexto.** El estudio normativo (sección 1.8 del documento v12) identificó obligaciones que la v11 no cubría: el contrato de la Ley 24.240 (art. 36), la revocación del préstamo, la identificación del cliente de la Resolución UIF 200/2024 y el régimen informativo de la Central de Deudores.

**Decisión.**
1. **Contrato (CU-103, RN-78).** Se genera con cada aprobación vigente: si la aprobación queda sin efecto y la solicitud se vuelve a aprobar, el nuevo contrato instrumenta las nuevas condiciones y exige una nueva constancia. El Analista lo imprime en dos ejemplares, entrega uno al cliente e incorpora al legajo la copia escaneada del firmado, con la fecha de recepción, que inicia el plazo de revocación; el Cajero no liquida sin ella (RN-25). La constancia es un tipo de documento interno, inactivo en el catálogo para que ninguna línea lo exija. Los datos de la entidad se configuran en `.env`. Precisa el punto 3 de D-29.
2. **Revocación (CU-102, RN-79).** Dentro de los 10 días hábiles desde la recepción del contrato o el desembolso, lo último. El cliente restituye el capital y el interés devengado, sin gastos; confirmada la restitución, el crédito pasa a Revocado (nuevo estado de 4.2.7).
3. **Identificación (RN-80).** CU-11 agrega nacionalidad, actividad principal obligatoria y la declaración sobre persona expuesta políticamente, que alerta al Gerente. La declaración se guarda en `clientes` con su fecha (para la persona jurídica comprende a sus representantes y beneficiarios). Los clientes anteriores quedan con los datos vacíos: la validación los exige en la próxima modificación y CU-22 no registra solicitudes hasta completarlos. La consulta del padrón de ARCA es opcional (A9, ACT-10).
4. **Pre-registro (CU-104).** Precisa el punto 1 de D-29.
5. **Régimen informativo (CU-105, RN-84).** Clasificación mensual de los deudores en situaciones 1 a 5 y archivo para la Central de Deudores; la presentación ante el BCRA la hace la entidad.
6. **Tope global (RN-83) y confirmación de cambios (RN-82)** quedan como reglas propias, ya implementadas en D-22 y D-24.

**Consecuencias.** Nuevos RF-19, RNF-17, GLO-75 a GLO-84, clases ContratoPrestamo, Revocacion y SituacionDeudor, y tablas en las iteraciones 3 y 4. Fuentes de los diagramas de estados en `docs/diseno/estados` y de secuencia de la iteración 2 en `docs/diseno/iteracion-2`.

## D-32 · Cobro de cuotas en ventanilla y su anulación (CU-33, CU-35, CU-32, CU-37)

**Contexto.** CU-33 indica el orden de imputación dentro de una cuota (RN-29), pero no entre cuotas de distintos créditos, ni cómo se exigen los punitorios que CU-88 devenga con cuatro decimales, ni cuándo cambia el estado del crédito.

**Decisión.**
1. **Orden entre créditos.** Por cada crédito la selección comienza por la cuota impaga más antigua y es consecutiva (A8). Entre créditos, el importe se imputa por fecha de vencimiento y, a igual fecha, por crédito y número de cuota.
2. **Punitorios.** Se exigen los que CU-88 dejó en la cuota, redondeados a dos decimales; su IVA integra el concepto IVA. Dentro del IVA se cancela primero el de los conceptos ordinarios. Un concepto cancelado queda en cero aunque tuviera fracciones de centavo.
3. **Estados.** La cuota cancelada pasa a Pagada; la alcanzada parcialmente conserva su saldo y su estado (D-12). El crédito pasa a Cancelado sin cuotas impagas, y de En mora a Activo cuando no le quedan cuotas vencidas impagas.
4. **Comprobante.** Se emite en la misma transacción. Cada consulta y cada reemisión se audita, y la reemisión incrementa su contador (RN-31).
5. **Anulación (CU-37).** Cada concepto de la cuota recupera lo imputado; el IVA vuelve primero al ordinario, hasta su importe nominal. Los punitorios se restituyen redondeados a dos decimales y CU-88 los recalcula desde el vencimiento original. Son operaciones dependientes los cobros posteriores confirmados sobre las mismas cuotas: se presentan y el Gerente confirma que quedan identificados para su revisión. El cobro y sus movimientos de caja se conservan, porque el dinero se recibió; el importe queda como saldo a favor (RN-33, RN-57).
6. **Pendiente.** La advertencia de cuotas incluidas en una orden de pago electrónico (A6) y la reversión por la pasarela (CU-37 A3) se incorporan con los pagos electrónicos (E03).

**Consecuencias.** Sin cambios de esquema. Componentes nuevos: `CobroCuotas` (con 9 pruebas) y `CobroService`; controlador `Admin\Cobros` y consulta de jornadas en `Admin\Cajas`.

## D-33 · Cálculo de los intereses punitorios (CU-60, CU-88, CU-33)

**Contexto.** RN-34, RN-35 y RN-53 definen la base, la tasa, el tope y la tolerancia, pero no cómo se combina el proceso nocturno con un pago realizado durante el día, ni qué tasa rige el día en que se cambia el parámetro.

**Decisión.**
1. **Día procesado.** CU-88 corre a las 00:05 y procesa el día anterior: marca Vencidas las cuotas con vencimiento hasta ese día y En mora sus créditos, y registra un tramo por cada día de atraso sin calcular, desde el día siguiente al vencimiento. Si el proceso no corrió algunos días, cada cuota completa sus días faltantes en la siguiente ejecución (A1); la clave única (cuota, fecha) evita duplicar (A2).
2. **Tasa de cada día.** Rige la versión de parámetros vigente al cierre del día (23:59 hora local), limitada por el tope sobre la TNA del crédito. Un cambio registrado hoy se aplica desde el día de hoy y no modifica los tramos ya calculados (RN-35).
3. **Base.** El capital pendiente de la cuota en el momento del cálculo. El día de un pago parcial se calcula sobre el capital anterior al pago, porque el tramo del día se registra antes de imputarlo; los días siguientes usan el capital reducido (RN-34).
4. **Tolerancia.** Los tramos se registran como condicionales. Cuando el atraso supera la tolerancia asignada al crédito al liquidarlo, todos pasan a exigibles y su suma se agrega a los punitorios pendientes de la cuota (RN-53). Una cuota cancelada dentro de la tolerancia no paga punitorios.
5. **Cobro.** Antes de mostrar la deuda y de registrar un cobro (CU-33), se calculan los tramos hasta el día del pago, de modo que un pago al cuarto día de atraso incluye los cuatro días (RN-53).
6. **Morosidad avanzada (RN-65).** Se determina al consultar, a partir de los días de atraso de las cuotas exigibles y del umbral vigente; no se guarda una clasificación aparte.

**Consecuencias.** Sin cambios de esquema (usa `tramos_punitorios`, `cuotas.punitorio_pendiente` y `creditos.tolerancia_dias`). Componentes nuevos: `ValidadorParametrosMorosidad`, `PunitorioService`, el comando `cartera:punitorios` y la línea habilitada del crontab.

## D-34 · Importe y registro de la revocación (CU-102, RN-79)

**Contexto.** El art. 1112 del Código Civil y Comercial obliga a restituir lo recibido; RN-79 agrega el interés compensatorio devengado y excluye gastos y comisiones. Faltaba precisar sobre qué capital se calcula, hasta qué día y cómo entra el dinero.

**Decisión.**
1. **Capital.** El neto desembolsado, que es lo que el cliente recibió; el gasto de otorgamiento retenido no se restituye ni se cobra.
2. **Interés.** Interés simple a la TNA del crédito por los días corridos desde el desembolso hasta la comunicación de la revocación, más su IVA, que es un impuesto y no un gasto. Se descuenta lo que el cliente haya pagado por sus cuotas; si pagó más, el exceso queda como saldo a favor (RN-57).
3. **Plazo.** Diez días hábiles, según el calendario operativo, desde el día de la recepción del contrato o del desembolso, lo que ocurra último. Sin constancia de recepción (CU-103) no se puede computar el plazo.
4. **Dos momentos.** La comunicación registra la revocación y fija el importe; el crédito conserva su plan y sigue sujeto a CU-88 hasta que se confirma la transferencia recibida. Entonces el crédito pasa a Revocado y sus cuotas no pagadas, a SinEfecto. Si lo pagado cubre el importe, se confirma en el acto.
5. **Ingreso.** La transferencia se registra como un cobro de tipo Revocacion, sin imputaciones a cuotas, con su movimiento de caja: integra la conciliación de la jornada (RN-32) y vuelve a los fondos disponibles. No puede anularse con CU-37.

**Consecuencias.** Migración 19 y componentes `Revocacion` (con 3 pruebas), `RevocacionService` y `Admin\Revocaciones`; permiso 102 del Cajero. Se agrega el estado Revocado al diagrama de 4.2.7, ya incluido en la v12.


## D-35 · Órdenes y confirmación de pagos electrónicos (CU-34, CU-38, CU-90, CU-91; RN-30, RN-54 a RN-57)

**Contexto.** RN-30 exige que el pago electrónico se confirme una sola vez y solo con información de la pasarela, y RN-55 que los punitorios se calculen hasta la fecha efectiva del pago. Faltaba precisar cuándo existe la orden, qué estados de la pasarela son definitivos, cómo se imputa un importe distinto y qué se hace con los punitorios que el proceso nocturno registró después del pago.

**Decisión.**
1. **Orden.** Cuotas completas y consecutivas desde la más antigua (RN-29), por su total actualizado al emitirla. Vence a las 00:00 del día siguiente, hora local, como límite exclusivo. Una cuota no integra dos órdenes pendientes y vigentes (A7): se vuelve a compartir el enlace de la existente. La orden se registra y después se envía a la pasarela, fuera de la transacción; si la pasarela no responde, se elimina y el intento queda auditado (A1).
2. **Notificación.** El webhook solo registra el cuerpo y la validez de la firma y responde el acuse; una firma no válida queda Descartada como incidencia de seguridad. El proceso `pagos:notificaciones` (cada minuto) no confía en el cuerpo: consulta a la pasarela las transacciones de la orden y procesa cada una (RNF-08).
3. **Estados.** Aprobado (`approved`, `accredited`) confirma; rechazado (`rejected`, `cancelled`, `refunded`, `charged_back`) deja la deuda; cualquier otro es no definitivo: la orden pasa a En conciliación con una incidencia EstadoDesconocido, que se resuelve cuando llega el estado definitivo. El identificador externo es único: una transacción ya definitiva no se procesa de nuevo.
4. **Confirmación.** Se calculan los punitorios hasta la fecha efectiva (hora local) y se eliminan los tramos de días posteriores, descontándolos de los punitorios pendientes con su detalle en la auditoría (RN-55). El importe recibido se imputa a las cuotas impagas de la orden con el mismo orden y conceptos que en ventanilla (RN-36); el cobro tiene canal Electronico, medio PagoElectronico, la transacción que lo confirmó y comprobante.
5. **Diferencias.** Un importe distinto o en otra moneda, o una fecha efectiva igual o posterior al vencimiento, se imputan igual y generan incidencias ImporteDistinto o FueraDePlazo con alerta al Gerente (RN-56). El excedente y, si las cuotas ya se pagaron por otro medio, todo el importe (ObligacionesCanceladas) quedan como saldo a favor vinculado a la transacción (RN-57).
6. **Conciliación.** `pagos:conciliar` (cada 30 minutos) consulta las órdenes pendientes de más de 10 minutos, las En conciliación y las vencidas en los dos últimos días; una orden vencida sin transacciones pasa a Vencida, y una En conciliación por más de 48 horas genera una alerta al Gerente (CU-91 A2). El Cajero puede pedir la misma consulta desde CU-38.
7. **Simulador.** Fuera de producción, `simulador/pagar/{referencia}` permite aprobar, rechazar, dejar en proceso o pagar otro importe u otra fecha, con o sin notificación y con firma no válida.

**Consecuencias.** Migración 20 (`ordenes_pago_electronico.url_pago`). Componentes nuevos: `PagoElectronico` (con 3 pruebas), `PagoElectronicoService`, `Admin\PagosElectronicos`, `Api\Webhooks\Pasarela`, `Simulador\Pasarela` y los comandos `pagos:notificaciones` y `pagos:conciliar`, con sus líneas del crontab. `CobroService::imputar()` pasa a ser compartido por ambos canales, y CU-33 advierte las cuotas incluidas en una orden pendiente (A6).

## D-36 · Circuito de refinanciación y condonación de punitorios (CU-39 a CU-44, CU-74, CU-96; RN-36 a RN-38, RN-70)

**Contexto.** RN-36 y RN-70 fijan qué se refinancia y cuándo una propuesta queda Observada, pero no cómo se detecta que la deuda varió si los punitorios siguen creciendo después del corte, qué crédito recibe el nuevo plan ni cómo se trata el IVA de los punitorios condonados.

**Decisión.**
1. **Composición por cuota.** Para cada cuota no pagada de los créditos En mora seleccionados se guarda, en la simulación y en la propuesta, el capital pendiente, el concepto no capitalizable a la fecha de corte (interés, gastos, IVA y punitorios exigibles con su IVA si la cuota venció antes del corte, más el no capitalizable de una refinanciación anterior) y la parte de punitorios incluida. El corte de la simulación es el día en que se genera; el de la propuesta, el día de su registro.
2. **Variación.** La deuda varió si una cuota dejó de estar pendiente o volvió a estarlo, si cambió su capital o su no capitalizable sin punitorios, si bajaron sus punitorios o si un crédito dejó de estar en mora. El aumento de los punitorios no es una variación: la diferencia con el corte se incorpora al concepto no capitalizable al aplicar.
3. **Momentos de control.** CU-40 rechaza registrar la propuesta si la deuda cambió desde la simulación (A2). CU-41 y CU-74 revalidan y, si cambió, dejan la propuesta Observada sin aprobarla ni aplicarla. Además, todo cobro (ventanilla o electrónico), anulación de cobro o condonación sobre cuotas comprendidas deja Observadas en la misma transacción las propuestas pendientes o aprobadas, con alerta al Analista. Una propuesta vencida se marca Vencida al intentar operarla y, en general, por CU-89.
4. **Versiones.** Una propuesta Observada se corrige con una nueva simulación: al registrar la propuesta sobre los mismos créditos, queda como versión siguiente con el mismo número, vinculada con la anterior y con nuevo plazo de 7 días. La numeración usa la secuencia `seq_propuestas`.
5. **Aplicación.** En una sola transacción: el nuevo crédito tiene origen Refinanciación, la propuesta de origen, las condiciones de la línea del crédito original de menor número, el régimen de morosidad vigente y fecha de liquidación igual a la aplicación, sin desembolso ni movimiento de fondos. Su plan francés no tiene gastos administrativos; el IVA corresponde al interés y el no capitalizable se reparte en partes iguales (RN-70). Las cuotas originales pasan a Sustituida conservando sus saldos como antecedente, y los créditos originales, a Refinanciado. No se aplica mientras alguna cuota esté en una orden de pago electrónico vigente.
6. **Cancelación.** El Analista cancela por desistimiento con fecha y medio de la constancia; el Gerente, por causa administrativa. Procede en Pendiente de resolución, Aprobada pendiente de aceptación u Observada.
7. **Condonación.** El máximo es el porcentaje configurado de los punitorios pendientes de las cuotas elegidas, truncado a centavos y sin IVA. El importe se aplica desde la cuota más antigua y el IVA del punitorio se reduce en la misma proporción (todo, si se condona todo el punitorio de la cuota). No reduce capital ni otros conceptos.

**Consecuencias.** Migración 21: columnas de composición en `simulaciones_refinanciacion`, tabla `simulaciones_refinanciacion_cuotas`, `propuestas_cuotas.punitorio`, datos de cancelación en `propuestas_refinanciacion`, `aceptaciones_refinanciacion.fecha_constancia` y `punitorios_incorporados`, `condonaciones.cliente_id` y `condonaciones_cuotas.iva`. Componentes nuevos: `DeudaRefinanciable` y `Condonacion` (con 6 pruebas), `RefinanciacionService`, `CondonacionService`, `Admin\Refinanciaciones` y `Admin\Condonaciones`. CU-89 vence las propuestas. La cancelación anticipada (CU-97, E07) deberá llamar también a `observarPorCuotas()`.
