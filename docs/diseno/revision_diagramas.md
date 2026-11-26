# Revisión de los diagramas del Trabajo Final v18 (J06)

Revisión del 26/11/2026 de las 87 figuras del documento frente al sistema v0.7.0 (rama `develop`). Los diagramas de secuencia se compararon paso a paso con el código de cada controlador, servicio y biblioteca; el resto, con el esquema (`docs/modelo-datos/spec.py`), los casos de uso y `docker-compose.yml`.

## 1. Resumen

| Grupo | Figuras | Contenido | Legibilidad impresa | Acción |
|---|---|---|---|---|
| Diagramas de secuencia | 39 (34 distintos) | 12 correctos, 7 con ajustes menores, 15 con pasos incorrectos o faltantes | Letra de unos 5 pt; solapamientos en varios | Rehacer los 34 con el estilo nuevo (sección 2) |
| Casos de uso por subsistema | 11 | Faltan CU-106, CU-107 y CU-108; SS-02 muestra un actor que no existe | 4 figuras a menos de 150 ppp | Corregir 3 y reexportar 4 |
| Modelo de dominio | 9 | Faltan PreRegistro, SolicitudDerechoTitular y la fecha de la declaración PEP en SS-02 | 3 figuras a menos de 120 ppp | Corregir 1, verificar 1 y reexportar 3 |
| Diagramas de estados | 11 | Correctos; falta el de la solicitud de derechos (opcional) | Algunos ocupan 7 cm de ancho, con letra de unos 3 pt | Ampliar en la página |
| Entidad-relación | 12 | Al día (86 tablas, regenerados el 23/11) | Letra de 2 a 5,6 pt | Reorganizar y pasar a página apaisada |
| Arquitectura | 2 | Vista lógica dice 82 tablas (son 86); la de despliegue omite conexiones de `app` | Despliegue: letra de unos 4 pt | Corregir ambos |
| Gantt, actores, subsistemas | 3 | Gantt con la actividad 10 «En curso» | Actores a 148 ppp | Actualizar al cierre (J08) |

**Criterio de legibilidad.** Un diagrama impreso se lee con comodidad a partir de 8 pt. La letra impresa se estimó con el tamaño de la fuente del diagrama, su ancho natural y el ancho que ocupa en la página (17,1 cm en vertical, 24,7 cm en apaisado).

## 2. Diagramas de secuencia

### 2.1 Legibilidad

Los diagramas de las iteraciones 1 a 3 se generan con Mermaid (`mermaid.json`: letra de 15 px, líneas de vida de 190 px) y tienen 6 a 9 líneas de vida. Ocupan todo el ancho de la página, así que la letra impresa queda en **unos 5 pt**. Además, las autollamadas con texto largo y los mensajes dentro de `alt` y `opt` se superponen con los números de paso y los bordes de los marcos (por ejemplo CU-22 pasos 7, 10 y 15; CU-52 pasos 7 y 13; CU-63 pasos 3, 7 a 10 y 14).

**Estilo propuesto** (probado con CU-22, `cu22_propuesta.png`): letra impresa de **unos 10 pt en página vertical**, sin solapamientos.

1. Hasta 7 líneas de vida. Se quita la línea «Vista»: el actor envía la petición al controlador, como ya hacen los diagramas de la iteración 4.
2. `mermaid.json`: letra de 16 px, líneas de vida de 150 px, `actorMargin` 20 y `messageMargin` 40.
3. Las validaciones largas pasan de autollamadas a notas (`Note over S: …`).
4. Las respuestas al actor vuelven siempre por el controlador, punteadas.
5. `alt` siempre con sus dos ramas (`alt … else …`); un marco de una sola rama es `opt`.
6. Una clase por línea de vida (no «Periodo, Indicadores»).

### 2.2 Corrección de los pasos

**Correctos** (solo cambian por el estilo): CU-01, CU-02, CU-13, CU-17, CU-83, CU-95, CU-23, CU-33, CU-37, CU-88, CU-74 y CU-97.

**Ajustes menores:**

| CU | Figura | Cambio |
|---|---|---|
| CU-04 | 56 | Agregar la validación de la política de contraseñas al definirla (D-63). |
| CU-12 | 58 | Agregar el cambio de la declaración PEP: fecha y alerta. |
| CU-34 | 74 | Agregar A3: el importe de la orden debe ser mayor que cero. |
| CU-78 | 77 | Agregar la alerta por comprobante con importe o cuenta distintos (A6). |
| CU-89 | 70 | El comando hoy también vence propuestas de refinanciación, órdenes de pago y autorizaciones de devolución, y depura pre-registros: agregarlo como `loop` o nota. |
| CU-90 | 51, 75 | Agregar la deduplicación por identificador de evento, la notificación sin orden (Descartada con alerta) y los reintentos hasta 5. |
| CU-102 | 78 | Agregar el control de constancia de recepción del contrato y el caso con importe a restituir cero (se confirma en el acto). |

**Pasos incorrectos o faltantes:**

| CU | Figura | Problema |
|---|---|---|
| CU-21 | 63 | Faltan la versión vigente, el tope de CFTEA (RN-14), la alícuota de IVA de los parámetros y el aviso de exceso de capacidad con el importe máximo (**D-65**). |
| CU-22 | 64 | Falta el control de capacidad de pago (**D-65**, RN-18); `validarArchivos` lo llama el servicio, no el controlador; la falta de documentos no aplica a las solicitudes que vienen de una oferta. |
| CU-86 | 52, 66 | Faltan el registro de los intercambios con el BCRA y de los antecedentes externos; la estructuración solo corre si el destino es selección de plan; sin alternativas viables pasa a PendienteResolucion. |
| CU-79 | 67 | Faltan la validación de la conformidad (RN-23), el resultado de A2 y A5, el paso a PendienteResolucion si no quedan alternativas, y la **generación del contrato** al aprobar. |
| CU-26 | 68 | Faltan la **generación del contrato** y el control de línea activa al aprobar; A3 y A5 sin su resultado; la reserva anterior se libera antes de reservar. |
| CU-27 | 69 | Faltan los controles de **contrato firmado** (CU-103) y **declaración PEP** antes de liquidar; A1 y A2 sin su resultado. |
| CU-11 | 49, 57 | Faltan la alerta por persona expuesta políticamente, la validación de la localidad y el cierre del pre-registro (CU-104). |
| CU-103 | 80 | El contrato se genera con la versión vigente del **modelo de contrato** (CU-106). |
| CU-104 | 81 | «Formulario prellenado (y padrón de ARCA)» es incorrecto: el sistema no consulta ARCA (D-60); el Analista llama directo al servicio; faltan «ya es cliente» y el reinicio de un pre-registro. |
| CU-93 | 82 | **Orden incorrecto**: crea las ofertas antes que la campaña y pone el caso «sin elegibles o línea suspendida» después del COMMIT. El código verifica la línea primero y crea la campaña solo si hay ofertas. Las alternativas las calcula `Estructurador`. |
| CU-52 | 83 | Las respuestas al Cliente saltean el controlador; falta A3 (oferta ya aceptada); marco `alt` de una rama; textos superpuestos. |
| CU-65 | 84 | La excepción y la descarga saltean el servicio y el controlador; dos clases por línea de vida. |
| CU-105 | 85 | El Gerente llama directo al servicio; la rectificación es otro escenario; falta A1 (período ya generado). |
| CU-61 | 86 | El paso 14 tiene la **flecha invertida**; falta la respuesta inmediata al pedido; el caso de falla queda después del éxito. |
| CU-63 | 87 | Faltan la lectura del pedido, el descifrado y la extracción del paquete, la restauración de adjuntos y A3 (sin copia previa no restaura); textos superpuestos. |

**Fuentes.** Los diagramas de la iteración 4 (figuras 81 a 87) no tienen fuente en el repositorio (H07). Se escriben en Mermaid en `docs/diseno/iteracion-4/` con el estilo nuevo.

## 3. Otros diagramas

| Figura | Diagrama | Cambio |
|---|---|---|
| 1 | Gantt | Fechas reales y estado de las actividades 10 a 12 al cierre. |
| 2 | Jerarquía de actores | Reexportar (148 ppp). |
| 3 | CU de SS-01 | Agregar **CU-107 Cambiar Contraseña** (actor Usuario). |
| 4 | CU de SS-02 | Agregar **CU-108 Atender Derechos del Titular** (Cliente y Analista); reemplazar el actor «Servicio de padrón de ARCA» por la Central de Deudores del BCRA (D-60); reexportar (118 ppp). |
| 5 | CU de SS-03 | Reexportar (120 ppp). |
| 6 | CU de SS-04 | Agregar **CU-106 Configurar Modelo de Contrato** (H06); reexportar (148 ppp). |
| 14 | Subsistemas | Sin cambios de contenido. |
| 15 | Dominio de SS-01 | Reexportar (117 ppp). |
| 16 | Dominio de SS-02 | Agregar **PreRegistro**, **SolicitudDerechoTitular** y `fechaDeclaracionPep`. |
| 18 | Dominio de SS-04 | Verificar la asociación de ContratoPrestamo con la versión del modelo de contrato. |
| 21, 23 | Dominio de SS-08 y SS-11 | Reexportar (104 y 96 ppp; SS-11 ocupa 3,5 cm). |
| 24 | Estados de cuenta de acceso | Agregar CU-107 al bloqueo por intentos (RN-62); ampliar de 7 a 12 cm. |
| — | Estados de la solicitud de derechos | Opcional: Pendiente → Atendida o Denegada, con su plazo legal. |
| 35 a 46 | Entidad-relación | Contenido al día. Letra de 2 a 5,6 pt: página apaisada y distribución en más filas; SS-10 (74 cm de ancho natural), SS-02 y SS-06 son los más afectados. El detalle de columnas queda en el diccionario de datos. |
| 47 | Vista lógica | «82 tablas» → 86. |
| 48 | Vista de despliegue | `app` también llama al BCRA (evaluación inmediata, CU-23) y al correo (envío inmediato); Mailpit solo en desarrollo; versión de SQL Server fijada (G13); letra de unos 4 pt. |

## 4. Trabajo para J08

1. Aplicar el estilo nuevo en `docs/diseno/mermaid.json` y quitar la línea «Vista» de las fuentes de las iteraciones 1 a 3.
2. Corregir los 22 diagramas de secuencia de la sección 2.2 y escribir las fuentes de la iteración 4.
3. Corregir en draw.io los casos de uso de SS-01, SS-02 y SS-04, el dominio de SS-02, la cuenta de acceso, la vista lógica y la de despliegue.
4. Regenerar los entidad-relación en apaisado.
5. Reexportar a 300 ppp las figuras de baja resolución y reemplazar las imágenes del documento.
