# Prueba de usabilidad y accesibilidad de la interfaz (D-47)

Recorrido manual que verifica las correcciones de la auditoría de diseño y accesibilidad del 17/11/2026 (D-47, PR #171 a #174). Complementa las pruebas funcionales: no repite los casos de uso, sino que comprueba lo que la interfaz debe cumplir en todas las pantallas:

- accesibilidad WCAG 2.2 nivel AA;
- adaptación al celular;
- una acción principal por zona de trabajo;
- textos sin nombres internos.

Cada caso se marca con **OK** o **Falla**. Una falla se registra como *issue* con la etiqueta `bug`, el identificador del caso y lo observado.

## Preparación

1. Entorno Docker con `develop` actualizado y los datos de demostración (Anexo A.19 del plan de pruebas).
2. Navegador Chrome o Edge. Las mediciones a 375 px se hacen con las herramientas de desarrollo (F12 → modo dispositivo, 375 × 812). El contraste se mide con el inspector de color o con un verificador de contraste.
3. Usuarios: Administrador, Gerente, Analista, Cajero y un cliente con crédito en el portal (Anexo A).

## 1. Accesibilidad (WCAG 2.2 AA)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| U-01 | Foco visible en el menú | Con cualquier rol, hacer clic en el contenido y recorrer el menú lateral con Mayús + Tab. | Cada opción enfocada muestra un recuadro blanco, visible sobre la tinta y sobre la opción activa (1.4.11, 2.4.7). | |
| U-02 | Errores asociados al campo | Como Administrador, en *Registrar usuario*, enviar el formulario vacío. | Alerta «Revise los campos marcados.»; el foco queda en *Nombre*. *Número de documento* anuncia el error y su ayuda («Sin puntos ni espacios.»). Las opciones del rol quedan con borde rojo y asociadas a «Seleccione el rol.». | |
| U-03 | Etiquetas de los parámetros | Como Gerente, abrir *Parámetros de evaluación* e inspeccionar cada campo (clic en la etiqueta o lector de pantalla). | Cada etiqueta enfoca su campo: pesos, situaciones BCRA, «Más atraso», «Sin antecedentes», «Relación mayor», «Menor antigüedad», puntajes mínimos de las categorías y vigencia (1.3.1, 4.1.2). | |
| U-04 | Contraste de botones y códigos | Abrir *Mi caja* (Cajero), el *Tablero gerencial* (Gerente) y la ficha de una línea (Analista); medir los botones contorneados y el código de la línea. | Botones contorneados en el verde de la identidad y códigos en tinta, con contraste de al menos 4,5:1 sobre el fondo. Ningún botón azul (1.4.3). | |
| U-05 | Nombres de los enlaces repetidos | En la ficha de un cliente, recorrer los botones «Descargar» del legajo con un lector de pantalla; en el tablero, los «Ver el reporte». | Cada uno anuncia su archivo («Descargar DNI_….jpg») o su reporte («Ver el reporte de morosidad») (2.4.4). | |
| U-06 | Carga de localidades | En *Registrar cliente*, elegir una provincia con un lector de pantalla activo. | Se anuncia «N localidades disponibles.», o «1 localidad disponible.» si hay una sola. | |

## 2. Adaptación al celular (375 px)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| U-07 | Sin desborde horizontal | A 375 px, abrir la ficha de un cliente con legajo; la búsqueda de cliente de *Cobro en ventanilla*, *Aplicar saldo a favor*, *Revocación*, *Orden de pago electrónico* y *Condonar punitorios*; y la lista de créditos de *Cancelación anticipada*. | La página no se desplaza de costado; las tablas se desplazan dentro de su recuadro (1.4.10). | |
| U-08 | Filtros plegables | A 375 px, abrir *Clientes*, *Usuarios*, *Solicitudes* y *Líneas de crédito*; presionar «Filtrar …». | Sin criterios, los filtros están plegados y la lista se ve en la primera pantalla; el botón los despliega. Con criterios en la dirección, aparecen abiertos. | |
| U-09 | Portal en el celular | A 375 px, recorrer el inicio, el estado de cuenta, el detalle de un crédito, *Pagar una cuota*, *Comprobantes* y *Mi perfil*. | Sin desborde; el resumen «Su cuenta hoy» y los accesos se leen en una columna. | |

## 3. Listados y orden

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| U-10 | Un solo buscador | Abrir *Clientes*, *Usuarios*, *Solicitudes* y *Líneas de crédito*. | Solo están los filtros de la pantalla; no aparece el «Buscar:» de la tabla. | |
| U-11 | Orden de los recientes | Sin criterios, abrir *Clientes* y *Solicitudes*. | Los clientes registrados y las solicitudes más recientes aparecen primero, como indica el texto de la pantalla. | |
| U-12 | Documentos sin cortes | En *Clientes* y *Usuarios*, a 1.024 px o más. | «DNI 35666775» y «20-35666775-2» en un solo renglón. | |

## 4. Jerarquía y textos

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| U-13 | Alternativas | Como Analista, abrir las alternativas de una solicitud pendiente de selección de plan. | La TNA, la TEA y la vigencia figuran una vez sobre la tabla; la tabla entra sin desplazarse en escritorio; solo la alternativa *Pedida* tiene botón verde lleno, y el resto, contorneado. | |
| U-14 | Mi caja | Como Cajero con la caja abierta, abrir *Mi caja*. | «Registrar cobro» es la acción principal (verde) y «Cerrar caja», la secundaria. | |
| U-15 | Medios de cobro | En *Cobro en ventanilla*, elegir un cliente. | El medio 1 propone «Transferencia o depósito»; los medios 2 y 3 dicen «Sin usar». | |
| U-16 | Importe de la cancelación | En *Cancelación anticipada*, abrir el cálculo de un crédito. | El importe a cobrar se propone con puntos de miles y coma decimal (por ejemplo, 646.276,76), igual que el detalle. | |
| U-17 | Procesos con nombre legible | Abrir la ficha de una solicitud evaluada automáticamente y el *Régimen informativo*. | El historial y el listado muestran «Proceso automático: Evaluación automática» o «Proceso automático: Régimen informativo», no el nombre del comando. | |
| U-18 | Portal sin códigos internos | En el portal, recorrer el inicio, el estado de cuenta, una propuesta de refinanciación, una oferta y un comprobante. | Ningún texto muestra «CU-», «RN-» ni flujos alternativos como «(A1)». | |
| U-19 | Resumen del portal | Como cliente con crédito, ingresar al portal. | «Su cuenta hoy» con el próximo vencimiento y su importe, el saldo deudor, las cuotas vencidas si las hay y «Pagar cuotas». El ingreso no registra `portal.consultar-estado-cuenta` en la auditoría; abrir *Estado de cuenta* sí. | |
| U-20 | Indicadores clave del tablero | Como Gerente, abrir el *Tablero gerencial*. | Arriba, el índice de morosidad, el saldo deudor total, los cobros confirmados y el disponible para nuevas colocaciones, en 2 × 2 o en 4 columnas; la efectividad dice «sobre lo vencido en el período». | |
| U-21 | Acceso no autorizado | Como Gerente, abrir `/admin/refinanciaciones/simular`. | La pestaña dice «Acceso no autorizado \| SIG-CCR». | |
| U-22 | Recursos actualizados | Después de un `git pull` que cambie el CSS o el JS, recargar una pantalla sin forzar la recarga. | Se ve el cambio: los archivos se piden con `?v=` y su fecha de modificación. | |
| U-23 | Trabajo pendiente del rol | Como Gerente, con al menos una solicitud pendiente de resolución, abrir el inicio del panel. Repetir como Cajero sin la caja abierta y como Administrador. | El Gerente ve «Trabajo pendiente» con la cantidad y el enlace a *Resolver solicitudes*; el Cajero ve «Su caja todavía no está abierta hoy», que lleva a *Mi caja*; un rol sin colas ve «No tiene trabajo pendiente en este momento.». Ningún rol ve colas que no puede atender. | |
| U-24 | Etiquetas de estado con la identidad | Abrir *Refinanciaciones* y *Órdenes de pago* con propuestas u órdenes en distintos estados. | «Pendiente» en ámbar y «Aprobada, pendiente de aceptación» o «En conciliación» en tinta, las dos con texto blanco y contraste ≥ 4,5:1; ninguna en amarillo o celeste de Bootstrap. | |
| U-25 | Búsqueda de cliente única | Abrir *Simulador*, *Cobro en ventanilla* y *Condonar punitorios* y buscar el mismo cliente. | Las tres muestran la misma búsqueda (campo, ayuda, tabla y botón «Seleccionar a …»); el simulador no lista clientes inactivos. | |

## Registro

| Fecha | Responsable | Casos OK | Fallas (issues) | Observaciones |
|---|---|---|---|---|
| 17/11/2026 | Reauditoría con *impeccable* (Claude) | 22 de 22 | Ninguna nueva de severidad P1 o P2. Una P3 (H-36, nivel de título en el alta de cliente), corregida en esta misma rama | Entorno Docker con `develop` (PR #171 y #172), en escritorio y a 375 px, con los roles Administrador, Gerente, Analista, Cajero y Cliente. U-19 verificado en la auditoría: el inicio del portal no registra `portal.consultar-estado-cuenta`. |
| 17/11/2026 | Verificación del PR #174 (Claude) | U-23 a U-25: 3 de 3 | Ninguna | Docker con la rama `feat/inicio-panel-y-tokens`. U-23 con Gerente (1 solicitud para resolver), Cajero con la jornada abierta y Administrador; U-24 midiendo las etiquetas con el CSS del sistema (advertencia 5,4:1, información 9,6:1), porque no había propuestas ni órdenes en esos estados; U-25 en Cobro en ventanilla y Revocación del préstamo. |
