# Registro de decisiones

Decisiones de análisis y diseño adoptadas durante el proyecto. Cada una indica su contexto, la decisión, sus consecuencias y su estado de validación con el director. D-01 a D-08 fueron validadas el 24/10/2026 y D-09 a D-14 el 29/10/2026; su contenido está incorporado en las secciones correspondientes del documento del proyecto, y este archivo es el registro oficial de las decisiones (desde la v9 el documento no lo reproduce). Si una decisión validada requiere un cambio, se registra como una decisión nueva que reemplaza a la anterior, con los motivos del cambio.

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
| D-15 | 29/10/2026 | Propuesta (diseño de la iteración 1) |
| D-16 | 29/10/2026 | Propuesta (prueba funcional de la iteración 1) |

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

**Consecuencias.** Sin cambios de esquema. `AutorizacionService::emitirSinComunicacion()`, `ActivacionService::continuarTrasVerificacion()` y la plantilla `verificacion_correo_cliente`. Cambian CU-04 (flujo alternativo desde la verificación del correo) y CU-11 (paso 8 y postcondición) en la próxima versión del documento.

