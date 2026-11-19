# Prueba funcional de la iteración 3 (E08)

Recorrido manual de los casos de uso de la iteración 3 en el panel:

- **Caja sin efectivo:** CU-31, CU-32, CU-36 y CU-81 (D-30).
- **Contrato e identificación del cliente:** CU-103 y RN-80 (D-31).
- **Cobranza:** CU-33, CU-35 y CU-37 (D-32).
- **Morosidad:** CU-60 y CU-88 (D-33).
- **Pagos electrónicos:** CU-34, CU-38, CU-90 y CU-91 (D-35).
- **Refinanciación y condonación:** CU-39 a CU-44, CU-74 y CU-96 (D-36).
- **Saldos a favor y devoluciones:** CU-76 a CU-78 (D-37).
- **Revocación del préstamo:** CU-102 (D-34).
- **Cancelación anticipada:** CU-97 (D-38).
- **Fondos y vencimientos:** CU-92 y CU-89.

Complementa las pruebas automáticas (suites Finanzas, Caja, Seguridad y las demás), que verifican los cálculos y las reglas sin la base de datos: amortización, punitorios, imputación, refinanciación, condonación, cancelación anticipada y devoluciones. Esta prueba verifica el sistema completo: SQL Server, los procesos del planificador, el simulador de la pasarela y las pantallas.

Cada caso se marca con **OK** o **Falla**. Una falla se registra como *issue* con la etiqueta `bug`, el identificador del caso y lo observado. La iteración se considera aprobada cuando todos los casos dan OK o sus fallas están corregidas. Con eso se publica la etiqueta `v0.4.0` (Control 3).

Las secciones siguen el orden del circuito. Primero se preparan la caja y los créditos de prueba. Después se cobra, se lleva un crédito a mora para generar punitorios y se lo refinancia. Al final se prueban los cierres y los procesos automáticos.

## Preparación

1. Actualizar y levantar el entorno:
   ```bash
   git checkout develop && git pull
   docker compose up -d --force-recreate web app scheduler
   docker compose exec app composer migrar      # aplica las migraciones pendientes (hasta la 23)
   docker compose exec app composer semillas    # permisos nuevos de la iteración 3 (102 y 103)
   ```
   Después de cargar los permisos, cerrar la sesión y volver a ingresar con cada usuario.
2. Comprobar que el planificador tenga sus procesos:
   ```bash
   docker compose exec scheduler cat /etc/cron.d/sigccr
   ```
   Tienen que figurar:

   | Proceso | Frecuencia | Caso de uso |
   |---|---|---|
   | `cartera:punitorios` | 00:05 | CU-88 |
   | `pagos:notificaciones` | cada minuto | CU-90 |
   | `pagos:conciliar` | cada 30 minutos | CU-91 |
   | `fondos:proyectar` | 06:00 | CU-92 |
   | `vencimientos:controlar` | cada 15 minutos | CU-89 |
   | `evaluacion:ejecutar` | cada 5 minutos | CU-86 |

   Para no esperar, se ejecutan a mano con `docker compose exec app php spark <proceso>`.
3. **Simulador de la pasarela** (solo fuera de producción):
   - La página de pago de cada orden es el enlace «Abrir» de la orden (`/simulador/pagar/{referencia}`). Permite aprobar, rechazar o dejar en proceso; cambiar el importe o la fecha efectiva; enviar o no la notificación; y enviarla con una firma no válida.
   - La respuesta a las devoluciones se configura en `/simulador/devoluciones`: aprobar, rechazar, dejar en proceso o no responder. Ahí también se resuelven las que quedaron pendientes.
4. **Llevar un crédito a mora sin esperar:** se adelantan todos sus vencimientos con SQL (reemplazar el número de crédito) y se ejecuta `cartera:punitorios`:
   ```sql
   UPDATE q SET q.vencimiento = DATEADD(day, -40, q.vencimiento)
   FROM dbo.cuotas q JOIN dbo.creditos c ON c.id = q.credito_id WHERE c.numero = …;
   ```
   Con −40 días, la cuota 1 queda con unos 10 días de atraso, por encima de la tolerancia de 3 días.
5. **Créditos de prueba:** se necesitan cinco créditos liquidados. Se obtienen con el circuito de la iteración 2 (simular, registrar, verificar, confirmar la alternativa, incorporar el contrato firmado y liquidar) para clientes con CUIL terminado en 0. Si hace falta, registrar antes un ingreso de fondos (Gestión → Fondos).

   | Crédito | Uso | Secciones |
   |---|---|---|
   | A | Cobros en ventanilla, anulación y saldo a favor | 3, 4 y 7 |
   | B | Mora, punitorios, refinanciación y condonación | 5 y 8 |
   | C | Pagos electrónicos | 6 y 7 |
   | D | Revocación, liquidado hoy | 9 |
   | E | Cancelación anticipada, sin modificar sus vencimientos | 10 |

6. Medios de pago y cuentas:
   - Los cobros se registran por transferencia o depósito, tarjeta de débito o tarjeta de crédito, siempre con número de operación.
   - CBU válido de prueba: `2850590909400904181356`.
   - CBU inválido (cambia el último dígito): `2850590909400904181357`.
   - CVU: `0000003110000123456781`.

## 1. Caja sin efectivo (CU-31, CU-72, CU-81 y D-30)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| H-01 | Apertura sin fondo inicial | Como Cajero, con `CAJA-01` asignada, abrir Caja y cobranzas → Mi caja y abrir la caja. | No pide un importe inicial; «Caja abierta: ya puede registrar cobros por transferencia, depósito o tarjeta.» | |
| H-02 | Segunda apertura | Con la caja abierta, volver a intentar la apertura (otra pestaña). | «Ya tiene una caja abierta (CAJA-01): ciérrela antes de abrir otra (RN-28).» | |
| H-03 | Confirmación al cambiar el estado (CU-81) | Como Gerente, en Cajas, abrir «Inhabilitar» de una caja sin jornada abierta, completar el motivo y enviar. | Pide confirmación («¿Inhabilita la caja?…»); al aceptar: «Caja inhabilitada: su asignación quedó finalizada y no podrá abrirse.». Rehabilitarla después. | |
| H-04 | Inhabilitar con jornada abierta | Intentar inhabilitar `CAJA-01` mientras su jornada está abierta. | «La caja tiene una jornada abierta: el Cajero debe cerrarla antes de inhabilitarla (CU-36).» | |

## 2. Identificación del cliente y contrato (RN-80, CU-103 y D-31)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| H-05 | Identificación obligatoria | Registrar un cliente persona humana sin nacionalidad, sin actividad principal y sin la declaración de persona expuesta políticamente. | Cada error junto a su campo; la declaración: «Registre la declaración jurada sobre la condición de persona expuesta políticamente (RN-80).» | |
| H-06 | Persona expuesta políticamente | Registrar el cliente declarando «Sí» como persona expuesta. | Se registra; el Gerente recibe una alerta por la condición declarada. | |
| H-07 | Cliente anterior incompleto | Con un cliente cargado antes de la migración 17 (sin nacionalidad), simular y registrar una solicitud. | «Antes de registrar la solicitud complete la identificación del cliente con «Modificar datos»: falta … (RN-80).»; después de completarla, se registra. | |
| H-08 | Contrato pendiente | Aprobar una solicitud (alternativa confirmada) y abrir Crédito → Contratos como Analista. | La solicitud figura con su contrato generado; el contrato muestra las condiciones aprobadas y los datos de la entidad, y se imprime en dos ejemplares. | |
| H-09 | Liquidar sin constancia | Como Cajero, intentar liquidar esa solicitud antes de incorporar la constancia. | «Falta la constancia firmada del contrato de préstamo en el legajo: el Analista debe incorporarla (CU-103) antes de liquidar (RN-25, RN-78).» | |
| H-10 | Incorporar la constancia | Como Analista, adjuntar el contrato firmado escaneado con la fecha de recepción de hoy. | «Constancia firmada incorporada al legajo: la solicitud ya puede liquidarse (CU-27)…»; luego el Cajero liquida por transferencia. | |

## 3. Cobro en ventanilla y comprobantes (CU-33, CU-35 y CU-32)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| H-11 | Deuda del cliente | Como Cajero, en Cobro en ventanilla, buscar al titular del crédito A. | Lista sus créditos con las cuotas pendientes en orden, con capital, interés, gastos, IVA, punitorios y total. | |
| H-12 | Sin caja abierta | Con otro Cajero sin caja abierta, intentar registrar un cobro. | «No tiene una caja abierta hoy: ábrala antes de registrar cobros (CU-31, RN-28).» | |
| H-13 | Selección no consecutiva | Seleccionar la cuota 2 sin la 1. | «Crédito …: la selección debe comenzar por la cuota 1…» (A8, RN-29). | |
| H-14 | Validaciones del importe y los medios | Con la cuota 1 elegida, probar: importe mayor que el total; importe 0; un medio «Efectivo» (modificando el formulario); sin número de operación; dos medios con el mismo número; medios que no suman el importe. | «El importe a cobrar no puede superar el total de las cuotas seleccionadas…»; «Ingrese un importe a cobrar mayor que cero.»; «…no se reciben pagos en efectivo…»; «…informe el número de operación…»; «…está repetido.»; «Los medios suman $ …: faltan $ …». | |
| H-15 | Cobro total | Cobrar la cuota 1 completa por transferencia con número de operación. | «Cobro N.º … registrado por $ …: 1 cuota(s) pagada(s).»; se abre el comprobante con el medio, la cuota y sus conceptos. | |
| H-16 | Cobro parcial | Cobrar $ 1.000 de la cuota 2 dividido en tarjeta de débito y transferencia (el medio 2 empieza «Sin usar»: elegir el medio, D-47). | «…0 cuota(s) pagada(s) y la cuota 2 con saldo pendiente (D-12).»; el comprobante muestra los dos medios; la cuota 2 conserva su saldo. | |
| H-17 | Reemisión del comprobante | En Comprobantes de pago, buscar el cobro de H-15 por número, por cliente y por fecha; reemitirlo e imprimirlo. | Lo encuentra con cada filtro; la reemisión incrementa el contador y aclara que no genera un nuevo cobro (RN-31). | |
| H-18 | Movimientos de caja | En Movimientos de caja, abrir la jornada del día. | Figuran los cobros de H-15 y H-16, un movimiento por cada medio, con los totales por medio de pago. | |

## 4. Anulación de cobros (CU-37)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| H-19 | Anular con dependientes | Como Gerente, en Anular cobros, anular el cobro de H-15 (hay un cobro posterior, el de H-16) sin confirmar los dependientes. | «Hay 1 cobro(s) posteriores sobre las mismas cuotas. Revíselos o confirme…»; al confirmar con el motivo, se anula. | |
| H-20 | Efectos de la anulación | Revisar el crédito A, la caja y los saldos del cliente. | «Cobro anulado: 1 cuota(s) recuperaron su saldo y el importe de $ … quedó como saldo a favor…»; la cuota 1 vuelve a estar pendiente; el cobro y sus movimientos de caja se conservan, marcados como anulados. | |
| H-21 | Anular dos veces | Volver a anular el mismo cobro. | «El cobro N.º … ya fue anulado: no se generan nuevos ajustes (A1).» | |
| H-22 | Motivo obligatorio | Anular otro cobro sin motivo. | El motivo se pide junto a su campo (RN-33). | |

## 5. Parámetros de morosidad y punitorios (CU-60 y CU-88)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| H-23 | Validaciones de los parámetros | Como Gerente, en Gestión → Parámetros de morosidad, probar: tasa diaria 2 %, tope 60 %, tolerancia 45, umbral menor que la tolerancia, límite de condonación 150 %, TNA máxima de refinanciación menor que la mínima y 200 cuotas máximas. | Cada error junto a su campo («La tasa punitoria diaria debe estar entre 0 % y 1 % por día.», «El tope debe estar entre 0 % y 50 % de la TNA…», «…mayor que la tolerancia (RN-65).», «La TNA máxima de refinanciación debe ser mayor que la mínima.», etc.). | |
| H-24 | Nueva versión | Registrar una versión con los valores iniciales (tasa 0,12 % diaria, tope 50 %, tolerancia 3, umbral 60, condonación 100 %, refinanciación 30 % a 80 % y hasta 36 cuotas) y un motivo. | «Se registró la versión … de los parámetros de morosidad, vigente desde …»; figura en el historial. | |
| H-25 | Dentro de la tolerancia | Adelantar 2 días los vencimientos del crédito B (preparación 4, con −32 en lugar de −40) y ejecutar `cartera:punitorios`. | La cuota 1 queda Vencida y el crédito En mora; los tramos son condicionales y la cuota no muestra punitorios exigibles. | |
| H-26 | Superada la tolerancia | Adelantar los vencimientos del crédito B 8 días más y ejecutar de nuevo. | Los tramos pasan a exigibles. Los punitorios de la cuota son su capital pendiente × la tasa diaria × los días de atraso, con la tasa limitada a TNA × 50 % / 365. En el cobro se ven con su IVA. | |
| H-27 | Proceso repetido | Ejecutar `cartera:punitorios` dos veces seguidas. | La segunda no agrega tramos ni cambia los importes (A2). | |
| H-28 | Punitorios al día del cobro | Abrir el cobro del cliente del crédito B al día siguiente, sin ejecutar el proceso. | La deuda incluye el día de hoy: los punitorios se calculan hasta el día del pago (D-33). | |

## 6. Pagos electrónicos (CU-34, CU-38, CU-90 y CU-91)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| H-29 | Generar la orden | Como Cajero, en Pagos electrónicos → Nueva orden, elegir al cliente del crédito C y la cuota 1. | «Orden N.º … generada por $ …»; la orden muestra el enlace de pago, vence a las 00:00 y queda Pendiente de confirmación. | |
| H-30 | Cuota en otra orden | Generar otra orden con la misma cuota. | La cuota figura con la insignia «Orden N.º …» y no se puede elegir; si se fuerza, «Alguna cuota seleccionada ya integra la orden N.º …» (A7). | |
| H-31 | Aviso en ventanilla | Abrir el cobro en ventanilla del mismo cliente. | Aviso «Pago electrónico pendiente…» y la insignia en la cuota (CU-33 A6). | |
| H-32 | Pago aprobado | Abrir el enlace de la orden y simular el pago aprobado con notificación; ejecutar `pagos:notificaciones`. | La orden pasa a Confirmada con el cobro electrónico y su comprobante; la cuota queda Pagada. | |
| H-33 | Notificación repetida | En el simulador, «Actualizar un pago anterior» con el mismo pago aprobado y volver a ejecutar el proceso. | No se registra un segundo cobro (RN-30). | |
| H-34 | Pago rechazado | Con otra orden, simular el pago rechazado. | La orden pasa a Rechazada y la deuda se mantiene. | |
| H-35 | Pago en proceso | Con otra orden, simular «En proceso» y ejecutar el proceso; luego actualizar ese pago a aprobado. | Primero queda En conciliación con la incidencia «Estado no definitivo»; después se confirma y la incidencia queda Resuelta. | |
| H-36 | Importe mayor | Pagar una orden por $ 500 más que su importe. | Se cobra el importe de la cuota; el excedente queda como saldo a favor (RN-57) y se genera la incidencia «Importe distinto» con alerta al Gerente. | |
| H-37 | Importe menor | Pagar una orden por la mitad de su importe. | La cuota queda con saldo pendiente; incidencia «Importe distinto». | |
| H-38 | Firma no válida | Simular un pago marcando «Con firma no válida». | La notificación queda Descartada como incidencia de seguridad y no cambia la orden. | |
| H-39 | Sin notificación | Simular un pago aprobado sin notificación; esperar 10 minutos y ejecutar `pagos:conciliar`. | La conciliación lo detecta y lo confirma (CU-91). | |
| H-40 | Cuotas ya pagadas | Con una orden pendiente, cobrar esa cuota en ventanilla y después pagar la orden. | Todo el importe queda como saldo a favor con la incidencia «Cuotas ya canceladas…» (A6). | |
| H-41 | Fecha efectiva y punitorios | En una cuota en mora, pagar la orden con una fecha efectiva de dos días atrás. | Los punitorios se calculan hasta la fecha efectiva; los de los días posteriores se revierten (RN-55) y figuran en la auditoría. | |
| H-42 | Orden vencida | Llevar el `vence` de una orden pendiente al pasado (`UPDATE dbo.ordenes_pago_electronico SET vence = DATEADD(minute, -1, SYSDATETIMEOFFSET()) WHERE numero = …`) y ejecutar `vencimientos:controlar`. | Queda Vencida (CU-89); su cuota se puede incluir en una orden nueva. | |
| H-43 | Consultar órdenes | En Pagos electrónicos, buscar por número, por cliente y por estado; abrir una orden y usar «Consultar a la pasarela». | Cada orden con sus cuotas, transacciones, incidencias, cobro y saldo a favor; la consulta informa el resultado. | |

## 7. Saldos a favor y devoluciones (CU-76, CU-77 y CU-78)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| H-44 | Saldos con su origen | Como Cajero, en Aplicar saldo a favor, buscar al cliente del crédito A (saldo de H-20). | El saldo con su origen («Anulación del cobro N.º …»), el disponible y el reservado. | |
| H-45 | Constancia obligatoria | Aplicar sin medio de conformidad y con fecha futura. | Los dos errores junto a sus campos (A3). | |
| H-46 | Aplicar el saldo | Aplicar el saldo a la cuota 1 con la conformidad del cliente. | «Se aplicaron $ … del saldo a favor: …»; si no alcanza, «…con imputación parcial excepcional». Figura en «Aplicaciones registradas». | |
| H-47 | Sin saldo | Volver a aplicar con el saldo en cero. | «El cliente no tiene saldo a favor disponible (A1).» | |
| H-48 | Destinos no admitidos | Como Gerente, en Autorizar devoluciones, sobre el saldo de H-36: probar un CVU, el CBU inválido, el CUIT de otra persona, sin entidad ni evidencia y un importe mayor que el disponible. | Cada error junto a su campo: «No se admiten CVU…», «El CBU debe tener 22 dígitos…», «…no se devuelve a cuentas de terceros (A3, RN-73).», «El importe supera el saldo disponible…». | |
| H-49 | Medio original solo para pagos electrónicos | Comparar el saldo de H-20 (anulación) con el de H-36 (pago electrónico). | El de la anulación solo ofrece la transferencia; el del pago electrónico ofrece también el medio de pago original. | |
| H-50 | Autorizar y reservar | Autorizar la devolución del saldo de H-36 por el medio original. | «Devolución N.º … autorizada…»; el importe pasa a reservado; el Cajero recibe la alerta; aplicar ese saldo informa que está reservado (A2). | |
| H-51 | Ejecutar por la pasarela | Con el simulador en «Aprobar», como Cajero, en Devoluciones, ejecutarla. | «Devolución completada: la pasarela la confirmó…»; se consume la reserva; la constancia se puede imprimir. | |
| H-52 | Rechazo de la pasarela | Repetir H-50 y H-51 con otro saldo y el simulador en «Rechazar». | Queda Fallida, se libera el importe y el Gerente recibe la alerta (A5). | |
| H-53 | Resultado incierto | Repetir con el simulador en «No responder»; luego resolverla como aprobada en el simulador y ejecutar `pagos:conciliar`. | Primero En conciliación, con el importe reservado y el aviso de no repetir la operación (A4); después Completada. | |
| H-54 | Transferencia | Autorizar la devolución del saldo de H-44 por transferencia al CBU válido; ejecutarla primero con otro importe y después con los datos correctos. | El importe distinto se rechaza y alerta al Gerente (A6); con los datos correctos queda Completada con el comprobante. | |
| H-55 | Anulación y vencimiento | Autorizar otra devolución y anularla con motivo; autorizar una más, llevar su `vence` al pasado y ejecutar `vencimientos:controlar`. | La anulada libera el importe; la vencida queda Vencida y también lo libera. | |

## 8. Refinanciación y condonación (CU-39 a CU-44, CU-74 y CU-96)

Se usa el crédito B, en mora desde la sección 5.

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| H-56 | Créditos admitidos | Como Analista, en Cartera → Simular refinanciación, buscar al titular del crédito B. | El crédito B (En mora) con su capital, interés vencido, gastos e IVA, punitorios y total; un crédito Activo figura como no admitido (A5). | |
| H-57 | Límites de las condiciones | Simular con TNA 20 %, TNA 90 % y 48 cuotas. | «La TNA debe estar entre 30 % y 80 %…» y «La cantidad de cuotas no puede superar 36…» (RN-70). | |
| H-58 | Simulación | Simular con TNA 60 % y 12 cuotas. | Capital refinanciado y concepto no capitalizable separados, deuda consolidada, plan francés preliminar y vigencia de 7 días; aclara que no modifica la deuda (RN-36). | |
| H-59 | Registrar la propuesta | Desde la simulación, «Registrar como propuesta». | «Propuesta N.º … registrada, pendiente de resolución gerencial y vigente por 7 días corridos…»; el Gerente recibe la alerta. | |
| H-60 | Propuesta duplicada | Simular de nuevo el mismo crédito y registrar otra propuesta. | «El cliente ya tiene la propuesta N.º … vigente sobre alguno de estos créditos…» (A3) y lleva a la propuesta existente. | |
| H-61 | Deuda cambiada desde la simulación | Con otra simulación vigente sin propuesta, cobrar una cuota del crédito y registrar la propuesta. | «La deuda cambió desde la simulación: genere una nueva (A2).», con el detalle. | |
| H-62 | Consultar | En Cartera → Refinanciaciones, buscar por número, por documento, por estado y por fechas; probar un rango invertido. Abrir la propuesta. | Resultados filtrados; el rango invertido se rechaza (A2). El detalle muestra cuotas, plan, versiones, alertas y vigencia. | |
| H-63 | Observar | Como Gerente, resolver la propuesta de H-59 con «Observar» y un fundamento. | Queda Observada con el motivo; el Analista recibe la alerta para registrar una nueva versión. | |
| H-64 | Nueva versión | Como Analista, simular otra vez y registrar la propuesta. | «Propuesta N.º … (versión 2) registrada…», con el mismo número y la versión 1 en «Versiones». | |
| H-65 | Aprobar | Como Gerente, aprobar la versión 2 con fundamento. | «Propuesta aprobada: queda pendiente de la aceptación del cliente…»; el Analista recibe la alerta. | |
| H-66 | Plan definitivo | Al día siguiente (o después de ejecutar `cartera:punitorios`), abrir «Registrar aceptación y aplicar». | El no capitalizable al corte más los punitorios devengados desde entonces (RN-70) y el plan con el primer vencimiento a 30 días. | |
| H-67 | Aplicar | Registrar la aceptación con fecha de hoy y medio «Firma presencial». | «Refinanciación aplicada: el crédito N.º … reemplaza a 1 crédito(s) con … cuota(s) sustituida(s), sin desembolso.». El crédito nuevo tiene origen Refinanciación; el B queda Refinanciado y sus cuotas, Sustituidas; Fondos no cambia. | |
| H-68 | Pago sobre una propuesta vigente | Con otra propuesta aprobada o pendiente, cobrar una cuota comprendida. | La propuesta pasa a Observada en el acto, con el motivo del cobro (RN-36). | |
| H-69 | Rechazar | Resolver otra propuesta con «Rechazar». | «Propuesta rechazada (A3).»; las obligaciones no cambian. | |
| H-70 | Cancelar | Como Analista, cancelar una propuesta pendiente por desistimiento, sin fecha y sin medio y luego con ellos; como Gerente, cancelar otra por causa administrativa. | Sin constancia se rechaza; con ella: «Propuesta cancelada por desistimiento del cliente…»; la del Gerente, «…por causa administrativa…». | |
| H-71 | Propuesta vencida | Llevar el `vence` de una propuesta pendiente al pasado e intentar resolverla; con otra, ejecutar `vencimientos:controlar`. | «…quedó Vencida y no puede resolverse (A1).»; la otra queda Vencida por el proceso. | |
| H-72 | Condonación: máximo | Como Gerente, en Cartera → Condonar punitorios, elegir cuotas con punitorios con un límite del 50 % (cambiarlo en H-24) e ingresar más del máximo. | «El importe supera el máximo condonable: $ … (50 % de $ … de punitorios pendientes, A1).» | |
| H-73 | Condonar | Condonar un importe válido con fundamento; sin fundamento antes. | Sin fundamento se rechaza (A2); con él: «Condonación registrada por $ …, con su IVA. El capital adeudado no se redujo.»; los punitorios y su IVA bajan desde la cuota más antigua. | |
| H-74 | Condonación sobre una propuesta | Condonar sobre cuotas de una propuesta vigente. | El mensaje informa que la propuesta quedó Observada (RN-36). | |

## 9. Revocación del préstamo (CU-102)

Se usa el crédito D, liquidado hoy con su constancia de recepción del contrato.

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| H-75 | Importe a restituir | Como Cajero, en Revocación del préstamo, abrir el crédito D. | Plazo de 10 días hábiles desde la recepción del contrato o el desembolso; importe = neto desembolsado + interés simple por los días transcurridos + IVA, menos lo pagado; sin gastos. | |
| H-76 | Registrar la revocación | Registrar la comunicación del cliente con fecha de hoy y su medio. | «Revocación registrada. El cliente debe transferir $ …; el crédito conserva su plan hasta confirmar la restitución.» | |
| H-77 | Importe distinto | Confirmar la restitución con un importe menor. | «El importe a restituir es $ …: faltan $ … (A3).» | |
| H-78 | Confirmar | Confirmar con el importe exacto, transferencia y número de operación. | «Restitución confirmada: el crédito quedó revocado y sus cuotas, sin efecto…»; el cobro de tipo revocación figura en la caja y no se puede anular. | |
| H-79 | Fuera de plazo | Con otro crédito, llevar con SQL la `fecha_recepcion` de su fila en `contratos_prestamo` y la fecha de su liquidación a más de 10 días hábiles atrás, e intentar revocar. | «El plazo de revocación venció el …: el cliente puede cancelar el crédito anticipadamente (CU-97) (A1).» | |

## 10. Cancelación anticipada (CU-97)

Se usa el crédito E, sin vencimientos modificados.

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| H-80 | Cálculo | Como Cajero, en Cancelación anticipada, buscar al titular del crédito E y «Calcular». | Detalle: cuotas vencidas actualizadas, capital de las no vencidas, interés de la cuota en curso por los días transcurridos (de 30) con su IVA, total y la reducción de lo no devengado. Sin comisión (Ley 24.240, art. 36). El importe a cobrar se propone con el formato del sistema (por ejemplo, 646.276,76; D-47). | |
| H-81 | Importe distinto | Registrar con un importe menor que el total. | «El importe debe ser igual al total de la cancelación: $ …. No se admite la cancelación parcial (A3, RN-67).» | |
| H-82 | Con una orden de pago pendiente | Generar una orden electrónica con una cuota del crédito E y volver a calcular. | Aviso de la orden pendiente y el botón deshabilitado (A4). | |
| H-83 | Registrar | Vencer o pagar esa orden y registrar la cancelación por el total, por transferencia. | «Crédito cancelado anticipadamente con el cobro N.º …». El comprobante incluye «Cancelación anticipada total…» con la reducción; todas las cuotas quedan Pagadas y el crédito, Cancelado. | |
| H-84 | No se anula | Como Gerente, intentar anular ese cobro. | «El cobro de una cancelación anticipada no se anula…» (D-38). | |
| H-85 | Crédito refinanciado | Calcular la cancelación del crédito nuevo de H-67. | Incluye el concepto no capitalizable de las cuotas no vencidas (D-38). | |
| H-86 | Sin caja abierta | Sin caja abierta, abrir el cálculo. | Aviso de que debe abrir la caja y el registro deshabilitado (A1). | |

## 11. Cierre de caja, proyección de fondos y vencimientos (CU-36, CU-92 y CU-89)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| H-87 | Conciliación con diferencia | Como Cajero, en Mi caja → Cerrar, informar para un medio un total acreditado distinto del registrado, sin explicación; luego con explicación. | «Hay una diferencia de …: explique su causa (al menos 10 caracteres).»; con la explicación: «Caja cerrada. La conciliación registró diferencias en 1 medio(s)…». | |
| H-88 | Conciliación sin diferencias | Con otra jornada, cerrar informando los totales exactos. | «Caja cerrada. La conciliación no tiene diferencias. Su sesión sigue activa (RN-59).» | |
| H-89 | Jornada cerrada | Abrir la jornada cerrada en Movimientos de caja. | Sus cobros por medio, la conciliación (registrado, acreditado, diferencia y explicación) y la hora de cierre. | |
| H-90 | Proyección de fondos | Ejecutar `fondos:proyectar` y abrir Gestión → Fondos. | La consola muestra lo proyectado y si correspondería activar una campaña; Fondos muestra la «Última proyección» con sus componentes. | |
| H-91 | Proyección negativa | Registrar un egreso programado grande (dentro del período) y volver a proyectar. | Disponible proyectado negativo y alerta al Gerente (A2). | |
| H-92 | Vencimientos repetidos | Ejecutar `vencimientos:controlar` dos veces seguidas. | La segunda no cambia estados ni repite alertas. | |

## 12. Generales

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| H-93 | Permisos por rol | Como Cajero, abrir `/admin/punitorios/condonar`, `/admin/refinanciaciones/simular` y `/admin/saldos-favor/devoluciones`; como Analista, `/admin/cobros` y `/admin/devoluciones`; como Gerente, `/admin/creditos/cancelacion-anticipada`. | Acceso denegado (403) en todos; el menú de cada rol muestra solo sus opciones. | |
| H-94 | Operaciones simultáneas | Abrir la misma propuesta aprobada en dos pestañas y aplicar en ambas; repetir con la ejecución de una devolución. | La segunda informa que cambió o ya no está en el estado esperado; no hay doble aplicación ni doble devolución. | |
| H-95 | Pantalla angosta | Abrir el cobro en ventanilla, la propuesta, la aplicación del saldo y la cancelación anticipada con 360 px de ancho. | Formularios en una columna y tablas con desplazamiento propio, sin desplazamiento horizontal de la página. | |
| H-96 | Auditoría | En Gestión → Auditoría o en `registros_auditoria`, revisar las operaciones de esta prueba. | Cada cobro, anulación, orden, confirmación, propuesta, resolución, aplicación, condonación, devolución, revocación y cancelación tiene su registro con usuario, fecha y detalle. | |
| H-97 | Fondos coherentes | Comparar Fondos antes y después de la sección 8 y de las devoluciones. | La refinanciación no mueve fondos; los cobros los aumentan; las devoluciones de saldos a favor no los reducen (D-37). | |
| H-98 | Circuito completo | Con un cliente nuevo: liquidar un crédito, cobrar la cuota 1 en ventanilla, pagar la cuota 2 con una orden electrónica, llevarlo a mora, refinanciarlo y cancelar anticipadamente el crédito nuevo, sin intervenir la base salvo para el atraso. | Cada paso deja sus estados, comprobantes y registros de auditoría; al final el crédito original está Refinanciado y el nuevo, Cancelado. | |

## Registro

| Fecha | Responsable | Casos OK | Fallas (issues) | Observaciones |
|---|---|---|---|---|
| | Iván Ezequiel Thoux | de 98 | | |
