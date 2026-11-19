# Prueba funcional de la iteración 2 (D07)

Recorrido manual de los casos de uso de la iteración 2 en el panel:

- **Simulación y solicitud:** CU-21 a CU-23, CU-84 y CU-99.
- **Evaluación:** CU-24, CU-25 y CU-86.
- **Alternativas:** CU-64, CU-79 y CU-87.
- **Otorgamiento:** CU-26 a CU-28.
- **Consultas:** CU-29 y CU-30.
- **Cajas y fondos:** CU-31, CU-36, CU-72, CU-80, CU-81 y CU-85.
- **Parámetros y calendario:** CU-59 y CU-82.
- **Vencimientos:** CU-89.

Complementa las pruebas automáticas (suites Finanzas, Unitarias y Rutas), que verifican los cálculos, las reglas y la autorización de cada ruta sin la base de datos. Esta prueba verifica el sistema completo: SQL Server, los procesos del planificador (`evaluacion:ejecutar`, `vencimientos:controlar`) y las pantallas.

Cada caso se marca con **OK** o **Falla**. Una falla se registra como *issue* con la etiqueta `bug`, el identificador del caso y lo observado. La iteración se considera aprobada cuando todos los casos dan OK o sus fallas están corregidas. Con eso se publica la etiqueta `v0.3.0` (Control 2, 13/11/2026).

Las secciones siguen el orden del circuito: primero se preparan los fondos y la caja, porque la aprobación reserva fondos. Desde D-27 el desembolso es solo por transferencia a un CBU del cliente; la caja se sigue probando como está y se rediseña en la iteración 3. La sección 10 cubre los cambios pedidos en la presentación del 02/11/2026 (D-23 a D-28).

## Preparación

1. Actualizar y levantar el entorno:
   ```bash
   git checkout develop && git pull
   docker compose up -d --force-recreate web app scheduler
   docker compose exec app composer migrar      # aplica las migraciones pendientes (hasta la 15)
   docker compose exec app bash -c 'SIGCCR_DB_GRUPO=migraciones php spark db:seed PermisosSeeder'
   ```
   Después de cargar los permisos, cerrar la sesión y volver a ingresar con cada usuario.
2. Comprobar que el planificador tenga sus procesos:
   ```bash
   docker compose exec scheduler cat /etc/cron.d/sigccr
   ```
   Tienen que figurar `evaluacion:ejecutar` (cada 5 minutos) y `vencimientos:controlar` (cada 15 minutos). Para no esperar, se pueden ejecutar a mano:
   ```bash
   docker compose exec app php spark evaluacion:ejecutar
   docker compose exec app php spark vencimientos:controlar
   ```
3. La Central de Deudores se consulta con el simulador. El último dígito del CUIT/CUIL define la respuesta:

   | Dígito | Respuesta del simulador |
   |---|---|
   | 0 | Sin deudas informadas |
   | 1 | Situación 1 |
   | 2 | Situación 2 |
   | 3 | Situación 3 |
   | 5 | Situación 5 vigente; es excluyente, así que el resultado es desfavorable |
   | 9 | Servicio no disponible; la solicitud pasa a contingencia |

4. Para adelantar un vencimiento sin esperar, se modifica la fecha con SQL (sección 8) y se ejecuta `vencimientos:controlar`.
5. Los usuarios Gerente, Analista y Cajero de la iteración 1 se siguen usando. También la línea «Motos» (mínimo $ 300.000, máximo $ 1.500.000).

Datos de prueba sugeridos:

| Uso | Cliente | CUIL (último dígito) | Ingresos declarados |
|---|---|---|---|
| Circuito favorable | Ana Gómez | termina en 0 | $ 1.200.000 |
| Favorable con deuda menor | cliente de prueba | termina en 1 | $ 1.200.000 |
| Análisis gerencial | cliente de prueba | termina en 2 o en 3 | $ 1.200.000 |
| Desfavorable | cliente de prueba | termina en 5 | $ 1.200.000 |
| Contingencia | cliente de prueba | termina en 9 | $ 1.200.000 |
| Sin capacidad de pago | cliente de prueba | termina en 0 | $ 150.000 |

Los clientes de la tabla son los que ya se cargaron para probar la evaluación (D02). Con ingresos de $ 1.200.000 y la afectación inicial del 30 %, la capacidad de pago es de $ 360.000 por mes, menos las cuotas vigentes.

Para las transferencias:
- CBU válido de prueba: `2850590909400904181356`.
- CBU inválido (cambia el último dígito): `2850590909400904181357`.

## 1. Fondos y cajas (CU-85, CU-80, CU-81, CU-72, CU-31 y CU-36)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| G-01 | Ingreso de fondos | Como Gerente, en Gestión → Fondos, registrar un ingreso realizado de $ 5.000.000 con fecha de hoy. | «Movimiento registrado. La disponibilidad se actualizó.»; el disponible libre sube en $ 5.000.000. | |
| G-02 | Validaciones del movimiento | Probar: importe 0, un movimiento realizado con fecha futura, uno programado con fecha pasada y un egreso mayor que el disponible libre. | Cada error junto a su campo: «Ingrese un importe mayor que cero.», «…márquelo como programado.», «Un movimiento programado debe tener una fecha futura.» y el rechazo del egreso por superar el disponible (RN-71). | |
| G-03 | Movimiento programado | Registrar un ingreso programado para la semana próxima; intentar confirmarlo; luego anularlo. | Figura en la proyección y no en el disponible; confirmarlo se rechaza con «Todavía no llegó la fecha del movimiento…»; la anulación lo quita. | |
| G-04 | Corrección | Registrar un egreso realizado de $ 10.000 y corregirlo con motivo; intentar corregir la corrección. | Se crea el movimiento inverso vinculado y el disponible vuelve al valor anterior; la segunda corrección se rechaza con «El movimiento ya es una corrección o ya fue corregido.» | |
| G-05 | Registrar caja | En Caja y cobranzas → Cajas, registrar `CAJA-01`; repetir el código; probar el código `caja 01!`. | La primera: «Caja registrada y habilitada…»; el código repetido: «Ya existe una caja con ese código.»; el inválido se rechaza junto a su campo. | |
| G-06 | Asignar caja | Asignar `CAJA-01` al Cajero desde hoy, sin fecha de fin; intentar asignarla a otro usuario y con fecha de inicio pasada. | «Caja asignada: el Cajero ya puede registrar su apertura (CU-31).»; la segunda asignación se rechaza («…finalice la asignación vigente…»); la fecha pasada también. | |
| G-07 | Apertura | Como Cajero, en Mi caja, abrir la caja con un fondo inicial de $ 200.000; cerrar el navegador y volver a ingresar. | «Caja abierta: ya puede registrar cobros y desembolsos en efectivo.»; al volver, la jornada sigue abierta y no pide una nueva apertura. | |
| G-08 | Inhabilitar con jornada abierta | Como Gerente, intentar inhabilitar `CAJA-01` y finalizar su asignación mientras la jornada está abierta. | Ambas se rechazan: «La caja tiene una jornada abierta: el Cajero debe cerrarla antes de…» | |
| G-09 | Sin caja asignada | Con otro usuario Cajero sin asignación, abrir Mi caja e intentar la apertura. | «No tiene una caja asignada y habilitada: solicite la asignación al Gerente (CU-72).» | |

El cierre de la caja (CU-36) se prueba en la sección 6.

## 2. Simulación y comparación de líneas (CU-21 y CU-99)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| G-10 | Simular | Como Analista, en Crédito → Simulador, simular para Ana $ 500.000 en 12 cuotas en «Motos». | «Simulación registrada. Es preliminar: no genera solicitud ni deuda y vale por 7 días corridos.»; muestra cuota, TEA, CFTEA, total a pagar y el plan de cuotas. | |
| G-11 | Validaciones de la simulación | Probar: importe vacío, importe `abc`, cuotas vacías, cuotas `6,5`, importe menor que el mínimo de la línea y cuotas fuera del rango. | Cada error junto a su campo; los límites de la línea indican el valor admitido (RN-14, RN-76). | |
| G-12 | Línea no admitida | Simular con una línea suspendida o con una línea cuya categoría no admite al cliente. | El motivo de la línea seguido de «Seleccione otra línea.» | |
| G-13 | Comparar líneas | En Crédito → Comparar líneas, comparar para Ana sin importe ni cuotas, con el criterio «Mayor monto prestable»; repetir con «Menor costo (CFTEA)». | «Comparación registrada. Es preliminar…»; cada línea viable con su monto máximo y su CFTEA, las no viables con su motivo, y marcadas la de mayor monto y la de menor costo. | |
| G-14 | Comparar sin ingresos | Comparar para un cliente sin ingresos declarados. | «El cliente no tiene ingresos declarados: regístrelos al modificar sus datos (CU-12) y vuelva a simular.» | |
| G-15 | Formalizar desde la comparación | Desde una línea de la comparación de G-13, pasar a registrar la solicitud. | Abre el registro de la solicitud con esa simulación cargada. | |

## 3. Registro, verificación y corrección (CU-22, CU-23 y CU-84)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| G-16 | Registrar la solicitud | Desde la simulación de G-10, registrar la solicitud de Ana. | «Solicitud N.º … registrada: queda pendiente de verificación del legajo. No implica otorgamiento ni desembolso.»; figura en Crédito → Solicitudes del Analista y del Gerente. | |
| G-17 | Solicitud repetida | Simular y registrar otra solicitud de Ana en la misma línea. | «El cliente ya tiene la solicitud N.º … de esta línea en trámite…» | |
| G-18 | Simulación vencida | En la base, adelantar el `vence` de una simulación vigente y registrar con ella (o ejecutar antes `vencimientos:controlar`). | «La simulación superó su vigencia de 7 días corridos: genere una nueva (RN-48).» | |
| G-19 | Cliente desactivado | Desactivar un cliente que tiene una simulación vigente e intentar registrar la solicitud. | «El cliente está desactivado: no puede registrar solicitudes.» | |
| G-20 | Legajo observado | Abrir la verificación de la solicitud de G-16, marcar un documento como Observado y elegir «Observada» con observaciones de menos de 10 caracteres; luego con una observación completa. | La primera se rechaza junto a su campo; la segunda deja la solicitud en Pendiente de documentación con las observaciones visibles. | |
| G-21 | Resultado incoherente | Con todos los documentos Conformes, elegir «Observada». | «Toda la documentación está conforme: corresponde verificar el legajo.» | |
| G-22 | Corrección parcial | En Corregir, adjuntar solo parte de lo pedido y guardar. | «Corrección parcial guardada. La solicitud sigue pendiente de documentación hasta completar: …» | |
| G-23 | Corrección completa | Adjuntar lo que falta o registrar una aclaración de al menos 10 caracteres. | «Corrección registrada: la solicitud pasó a Pendiente de verificación.» | |
| G-24 | Rechazo documental | En otra solicitud, elegir «Rechazada» sin motivo y luego con motivo. | Sin motivo se rechaza junto al campo (RN-19); con motivo, la solicitud queda Rechazada con el motivo documental. | |
| G-25 | Legajo verificado | Verificar el legajo de Ana con todos los documentos Conformes. | La solicitud pasa a Pendiente de evaluación y la evaluación automática la toma en el momento; al recargar, está en Pendiente de selección de plan. | |

## 4. Evaluación crediticia (CU-86, CU-24 y CU-25)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| G-26 | Evaluación favorable | Abrir la evaluación de la solicitud de G-25 (CUIL terminado en 0). | Resultado Favorable, con puntaje, categoría, los cuatro indicadores con su peso y aporte, el resumen de la Central de Deudores («sin deudas») y la vigencia; la ficha de la solicitud muestra el resumen. | |
| G-27 | Situación 1 | Registrar, verificar y evaluar una solicitud del cliente con CUIL terminado en 1. | Favorable, con la deuda de BANCO SIMULADO S.A. en situación 1 en el detalle. | |
| G-28 | Análisis gerencial | Repetir con el CUIL terminado en 2 o en 3. | El resultado corresponde a la categoría del puntaje: con «Requiere análisis gerencial» la solicitud pasa a Pendiente de resolución y el Gerente recibe la alerta. | |
| G-29 | Desfavorable por situación excluyente | Repetir con el CUIL terminado en 5. | Desfavorable aunque el puntaje sea alto (situación 5 vigente, RN-69); la solicitud queda Rechazada, con el resultado y su motivo en la evaluación. | |
| G-30 | Servicio no disponible | Repetir con el CUIL terminado en 9 y ejecutar `evaluacion:ejecutar`. | El intento queda no completado, la solicitud pasa a Pendiente de evaluación de contingencia y figura en Crédito → Contingencias. | |
| G-31 | Validaciones de la contingencia | Abrir la contingencia de G-30 y enviar: sin antecedentes, una deuda con período `13/2025` o de hace más de 12 meses, situación 7, sin monto, deudas sin marcar la consulta manual y una justificación corta. | Cada error junto a su campo («El período debe tener el formato mm/aaaa…», «La situación debe ser un número de 1 a 6.», «Las deudas informadas corresponden a la consulta manual…»). | |
| G-32 | Registrar la contingencia | Completar antecedentes, una deuda de la consulta manual, la recomendación Favorable y la justificación. | «Evaluación de contingencia registrada: la solicitud queda pendiente de resolución. La recomendación no aprueba la solicitud; resuelve el Gerente.» | |
| G-33 | Contingencia repetida | Volver a abrir la URL de la contingencia de G-32 y enviar. | «La solicitud ya no está pendiente de evaluación de contingencia…» o «…ya tiene una evaluación de contingencia registrada…» | |
| G-34 | Intentos anteriores | En la evaluación de G-32, revisar el historial. | Figuran el intento automático no completado (con el código del simulador) y la evaluación de contingencia. | |

## 5. Alternativas y confirmación (CU-87, CU-64 y CU-79)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| G-35 | Alternativas generadas | En la solicitud de Ana (G-25), abrir Alternativas. | La capacidad de pago ($ 360.000 menos las cuotas vigentes), el monto máximo financiable, la combinación pedida en primer lugar y las demás por importe descendente; las descartadas al final con su motivo. Sobre la tabla, la TNA, la TEA y la vigencia comunes a todas; solo la pedida tiene el botón principal (D-47). | |
| G-36 | Detalle de la alternativa | Abrir una alternativa viable. | El plan de cuotas completo (francés), el gasto de otorgamiento, el neto a desembolsar, TEA, CFTEA y la fecha de vencimiento de la alternativa. | |
| G-37 | Validaciones de la conformidad | Confirmar sin fecha, con fecha futura, con fecha anterior a la generación y sin medio. | Cada error junto a su campo («…no puede ser futura.», «…no puede ser anterior a la generación de la alternativa.», «Seleccione el medio de la conformidad.»). | |
| G-38 | Aprobación automática | Confirmar la alternativa pedida con la fecha de hoy y el medio «Presencial, con firma del cliente». | «Alternativa confirmada y solicitud aprobada automáticamente. La aprobación vence el …: antes de esa fecha debe liquidarse (CU-27).»; en Fondos, el disponible baja en el neto a desembolsar y aparece la reserva. | |
| G-39 | Vencimiento en horas hábiles | Antes de G-38, cargar un feriado para mañana en el calendario (sección 7) y revisar la fecha de vencimiento de la aprobación. | El vencimiento salta el feriado y los fines de semana: 72 horas hábiles (RN-49). | |
| G-40 | Sin capacidad de pago | Registrar, verificar y evaluar una solicitud del cliente con ingresos de $ 150.000 y un importe alto. | Sin alternativas viables: «Tampoco resultaron alternativas viables: la solicitud pasó a resolución del Gerente.» o el motivo de capacidad nula o insuficiente; la solicitud queda en Pendiente de resolución. | |
| G-41 | Fondos insuficientes | Con el disponible libre por debajo del neto de una alternativa (registrar un egreso que lo deje así), confirmar una alternativa. | «Alternativa confirmada. No se aprobó automáticamente porque fondos insuficientes…: la solicitud pasó a resolución del Gerente.» | |
| G-42 | Regenerar alternativas | Con la solicitud en Pendiente de selección de plan, usar «Regenerar». | «Se generaron N alternativas…»; las anteriores dejan de estar disponibles para confirmar. | |
| G-43 | Confirmación simultánea | Abrir la misma alternativa en dos pestañas y confirmar en ambas. | La segunda informa que la solicitud cambió o que la alternativa ya no está disponible, y no se crea una segunda reserva. | |

## 6. Resolución, liquidación, cierre de caja y cancelación (CU-26, CU-27, CU-36 y CU-28)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| G-44 | Bandeja del Gerente | Como Gerente, abrir Crédito → Resolver solicitudes. | Las solicitudes de G-28, G-32, G-40 y G-41; cada una muestra la evaluación, la capacidad de pago actual y los fondos disponibles. | |
| G-45 | Validaciones de la resolución | Enviar sin decisión; «Devolver» sin etapa; «Aprobar» sin plan y con un plan definido sin importe ni cuotas. | Cada error junto a su campo («Seleccione la decisión…», «Indique la etapa que debe retomarse.», «Seleccione el plan que se aprueba…»). | |
| G-46 | Aprobar con plan del Gerente | En la solicitud de G-28, aprobar con un plan definido (importe y cuotas propios) y un fundamento. | «Solicitud aprobada: …» con el vencimiento; se constituye la reserva por el neto del plan. | |
| G-47 | Tope global | En Parámetros de estructuración, fijar un tope global de $ 400.000 (sección 7) y aprobar con un plan de $ 600.000. | «El importe supera el tope global por cliente ($ 400.000,00…)» junto al importe. Después quitar el tope. | |
| G-48 | Rechazar | Rechazar la solicitud de G-40 con fundamento. | «Solicitud rechazada. Se conserva el motivo y se liberaron sus afectaciones de fondos.» | |
| G-49 | Devolver | Devolver la solicitud de G-32 a la etapa «Verificación». | «Solicitud devuelta para revisión: el Analista debe retomar …»; queda Devuelta para revisión y vuelve con una corrección (CU-84) a Pendiente de verificación. | |
| G-50 | Aprobar sin fondos | Con el disponible libre menor que el neto, aprobar la solicitud de G-41. | «Fondos insuficientes para el desembolso de …»: no se aprueba y se puede rechazar o devolver. | |
| G-51 | Bandeja del Cajero | Como Cajero, abrir Caja y cobranzas → Liquidar créditos. | Las solicitudes aprobadas pendientes de liquidación, con su vencimiento. | |
| G-52 | Validaciones del desembolso | En la solicitud de G-38, liquidar con un documento distinto del cliente; con el CBU inválido, sin evidencia de titularidad, sin número de operación, con fecha futura y con un importe distinto del neto. | Cada error junto a su campo («El documento no coincide…», «Ingrese un CBU válido de 22 dígitos.», «El importe transferido debe ser el neto a desembolsar.»). | |
| G-53 | Liquidar por transferencia | Liquidar la solicitud de G-38 con el CBU válido, la evidencia, el número de operación, la fecha de hoy y el neto. | «Crédito liquidado: se desembolsaron …»; constancia de desembolso imprimible; la reserva se consume y figura el egreso en Fondos. | |
| G-54 | Liquidación repetida | Volver a abrir la URL de la liquidación de G-53 y enviar. | «La solicitud ya fue liquidada el …» | |
| G-55 | Conformidad del plan del Gerente | Liquidar la solicitud de G-46 por transferencia sin marcar la conformidad. | «El plan lo definió el Gerente: registre la conformidad del cliente antes de liquidar (RN-22).» | |
| G-56 | Liquidar el plan del Gerente | Repetir G-55 con la conformidad marcada y los datos de la transferencia. | «Crédito liquidado…» con el neto del plan definido por el Gerente. | |
| G-57 | Sin efectivo ni CVU (D-27) | En otra liquidación, comprobar que no se ofrece el efectivo y cargar el CVU `0000003110000123456781`. | La pantalla indica que el desembolso es solo por transferencia a un CBU; el CVU se rechaza: «Es un CVU de una billetera virtual…». | |
| G-58 | Cierre con diferencia | Como Cajero, cerrar la caja declarando un efectivo distinto del fondo inicial y sin explicación; luego con explicación. | La primera: «Hay una diferencia de …» y pide la explicación; la segunda cierra la jornada con la diferencia registrada. | |
| G-59 | Cierre sin diferencia | Al día siguiente (o con otra jornada), cerrar con el efectivo exacto. | La jornada se cierra sin explicación; el efectivo esperado coincide con el informado. | |
| G-60 | Inhabilitar y rehabilitar caja | Con la jornada cerrada, inhabilitar `CAJA-01` con motivo; intentar asignarla; rehabilitarla. | «Caja inhabilitada: su asignación quedó finalizada y no podrá abrirse.»; la asignación se rechaza; «Caja rehabilitada: ya se puede asignar.» | |
| G-61 | Cancelar | Como Analista, cancelar con motivo una solicitud en trámite; luego cancelar una aprobada (pide la confirmación adicional); por último, intentar cancelar una Liquidada. | «Solicitud cancelada. Se liberaron sus afectaciones de fondos…»; la aprobada exige la confirmación y libera la reserva; la Liquidada: «La solicitud ya finalizó: no se puede cancelar.» | |

## 7. Consultas, parámetros y calendario (CU-29, CU-30, CU-59 y CU-82)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| G-62 | Buscar solicitudes | En Crédito → Solicitudes, buscar por número, por documento o CUIT/CUIL, por estado, por línea y por rango de fechas; probar un rango invertido. | Resultados que cumplen los filtros; el rango invertido se rechaza junto a su campo (A2). | |
| G-63 | Búsqueda del Cajero | Como Cajero, buscar y hacer clic en el número de una solicitud aprobada y en el de una de otro estado. | La aprobada abre su liquidación; la otra no ofrece un enlace que lleve a una pantalla sin permiso (A3). | |
| G-64 | Ficha completa | Abrir la ficha de la solicitud liquidada de G-53. | Datos, historial de estados, evaluación, alternativas consideradas y la confirmada, afectaciones de fondos (reserva y consumo), alertas y liquidación. | |
| G-65 | Ficha incompleta | Abrir la ficha de una solicitud recién registrada. | Las secciones que todavía no existen figuran como «No registrada» (A4). | |
| G-66 | Parámetros de estructuración | Como Gerente, en Gestión → Parámetros de estructuración, probar: afectación 0 % y 120 %, IVA 150 %, vigencia 0 y 45 días, redondeo vacío, escalones `75 50` y `100 50 75`, y siete escalones. | Cada error junto a su campo (afectación entre 1 % y 100 %, IVA entre 0 % y 100 %, vigencia entre 1 y 30 días, «El primer escalón debe ser 100 %…», «…ir de mayor a menor.», «Se admiten hasta 6 escalones.»). | |
| G-67 | Nueva versión de parámetros | Guardar una afectación del 35 % con vigencia desde hoy y motivo; regenerar las alternativas de una solicitud. | Se crea la versión nueva con su fecha y motivo en el historial; las alternativas nuevas usan el 35 %; las solicitudes ya aprobadas conservan su versión. | |
| G-68 | Tope global en la comparación | Con el tope global de G-47 activo, comparar líneas para Ana. | Ninguna línea supera el tope como monto máximo. | |
| G-69 | Alta de un día no hábil | En Gestión → Calendario operativo, cargar un feriado futuro; repetirlo; cargar una fecha pasada. | El primero se agrega; el repetido se rechaza («ya está registrada (A2)»); la fecha pasada también («es una fecha pasada (A1)»). | |
| G-70 | Carga anual | Pegar varias líneas `dd/mm/aaaa; descripción; tipo`, incluyendo una fecha inválida, una sin descripción y un tipo inexistente. | Se agregan las válidas y «Fechas rechazadas: …» lista cada rechazada con su motivo. | |
| G-71 | Baja de un día | Dar de baja un día futuro; intentar dar de baja uno pasado. | «Día no hábil dado de baja. Las aprobaciones ya registradas conservan su vencimiento (RN-49).»; el pasado se rechaza («Solo se pueden dar de baja días de hoy en adelante…»). | |

## 8. Vencimientos automáticos (CU-89)

En cada caso se adelanta la fecha con SQL (reemplazar el número de solicitud) y se ejecuta `docker compose exec app php spark vencimientos:controlar`.

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| G-72 | Aprobación vencida | Con una solicitud aprobada sin liquidar: `UPDATE r SET r.vence_aprobacion = DATEADD(minute, -1, SYSDATETIMEOFFSET()) FROM dbo.resoluciones r JOIN dbo.solicitudes_credito s ON s.id = r.solicitud_id WHERE s.numero = … AND r.decision = 'Aprobada' AND r.vigente = 1;` | La solicitud queda Vencida, la reserva se libera (el disponible sube) y el Analista recibe la alerta; ya no figura en la bandeja del Cajero. | |
| G-73 | Liquidar una aprobación vencida | Repetir G-72 sin ejecutar el proceso e intentar liquidar. | «La aprobación venció sin desembolso: la solicitud quedó Vencida y se liberó la reserva de fondos (RN-49).» | |
| G-74 | Evaluación vencida | Con una solicitud en Pendiente de selección de plan, poner su `vence` de `evaluaciones_crediticias` en el pasado y ejecutar el proceso. | Vuelve a Pendiente de evaluación con la alerta; con `evaluacion:ejecutar` se evalúa de nuevo y genera alternativas nuevas. | |
| G-75 | Evaluación vencida al confirmar | Repetir G-74 sin ejecutar el proceso e intentar confirmar una alternativa. | «La evaluación crediticia ya no está vigente: la solicitud volvió a Pendiente de evaluación y se evaluará nuevamente (RN-20).» | |
| G-76 | Alternativa vencida | Poner en el pasado el `vence` de las alternativas de una solicitud e intentar confirmar una. | «La alternativa superó su vigencia de 7 días corridos y no se puede confirmar (RN-48). Genere nuevas alternativas.» | |
| G-77 | Solicitud inactiva | Con una solicitud en Pendiente de documentación, llevar su fecha de último movimiento 31 días atrás y ejecutar el proceso. | Queda Vencida (RN-72) con la alerta al Analista. | |
| G-78 | Simulaciones vencidas | Poner en el pasado el `vence` de una simulación y ejecutar el proceso. | La simulación figura Vencida y no se puede formalizar. | |
| G-79 | Proceso repetido | Ejecutar `vencimientos:controlar` dos veces seguidas. | La segunda no cambia nada ni genera alertas repetidas. | |

## 9. Circuito completo y generales

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| G-80 | Circuito completo | Con un cliente nuevo (CUIL terminado en 0): simular, registrar, verificar, confirmar la alternativa y liquidar por transferencia, sin intervenir la base. | La solicitud recorre Pendiente de verificación → Pendiente de evaluación → Pendiente de selección de plan → Aprobada pendiente de liquidación → Liquidada; el historial registra cada paso con su usuario y hora. | |
| G-81 | Permisos por rol | Como Cajero, abrir `/admin/simulaciones`, `/admin/resoluciones` y `/admin/fondos`; como Analista, abrir `/admin/fondos`, `/admin/cajas` y `/admin/parametros/estructuracion`. | Acceso denegado (403) en todos, sin datos; el menú de cada rol muestra solo sus opciones. | |
| G-82 | Resolución simultánea | Abrir la misma solicitud pendiente de resolución en dos pestañas del Gerente y aprobar en ambas. | La segunda: «La solicitud ya no está pendiente de resolución…» o «La solicitud cambió mientras la resolvía…»; hay una sola reserva. | |
| G-83 | Inexistentes | Abrir `/admin/solicitudes/999999` y `/admin/liquidaciones/999999`. | «La solicitud no existe.», sin detalles técnicos. | |
| G-84 | Pantalla angosta | Abrir las alternativas, la resolución y la liquidación con 360 px de ancho. | Formularios en una columna y tablas con desplazamiento propio, sin desplazamiento horizontal de la página. | |
| G-85 | Auditoría y fondos | Consultar en la base `registros_auditoria` y `movimientos_fondos` las operaciones de esta prueba. | Cada operación tiene su registro; la suma de reservas, consumos y liberaciones coincide con el disponible que muestra Fondos. | |

## 10. Observaciones de la presentación del 02/11/2026 (D-23 a D-28)

Antes de esta sección: aplicar la migración 15 y volver a cargar los permisos (preparación, paso 1).

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| G-86 | Rangos iguales (D-23) | Registrar una línea con importe mínimo y máximo de $ 500.000; luego con 12 cuotas mínimas y máximas; luego con edad mínima y máxima de 40. | «El importe máximo debe ser mayor que el mínimo…», «La cantidad máxima de cuotas debe ser mayor que la mínima…» y «La edad máxima debe ser mayor que la mínima…». | |
| G-87 | Gasto administrativo porcentual (D-28) | Registrar una línea con «Porcentaje del capital en cada cuota» de 0,5; probar 15 y vacío. Simular $ 800.000 en 12 cuotas. | 15 y vacío se rechazan junto al campo; la línea muestra «0,50 % del capital por cuota»; en la simulación cada cuota tiene $ 4.000,00 de gastos y su IVA. | |
| G-88 | Gasto fijo sin cambios | Simular con una línea registrada antes de la migración 15. | Muestra el importe fijo por cuota y el mismo plan que antes. | |
| G-89 | Datos del cliente en el simulador | Simular y comparar líneas para un cliente con cuotas vigentes o una solicitud en trámite. | Recuadro con documento, edad, situación laboral, empleador, ingresos, cuotas vigentes y comprometidas y capacidad de pago. | |
| G-90 | Plan paginado | Simular 36 cuotas; cambiar a 24 y a «Todas»; imprimir la simulación. | Muestra 12 cuotas por página con «Cuotas 1 a 12 de 36»; la impresión incluye las 36. | |
| G-91 | Documentos en la resolución | Como Gerente, abrir una solicitud pendiente de resolución y usar «Ver» en cada documento. | Lista los documentos con su resultado de verificación; cada uno se abre en otra pestaña sin descargarse. | |
| G-92 | Confirmación al modificar una línea (D-24) | Con una solicitud en trámite de una línea, modificar su TNA. Enviar sin marcar la confirmación y luego marcándola. | Primero: «Hay N solicitud(es) en trámite… confirme el cambio…» con la casilla; después se registra la versión nueva y la solicitud conserva la anterior. | |
| G-93 | Confirmación de parámetros (D-24) | Con solicitudes en trámite, registrar una versión de los parámetros de estructuración y otra de evaluación. | Piden la confirmación y la registran al marcarla. | |
| G-94 | Nuevo tipo de documento (D-25) | En Gestión → Documentación exigible, registrar «Certificado de servicios del empleador» para personas humanas; marcarlo en «Relación de dependencia» y guardar. Registrar una línea nueva. | El tipo queda activo con su código; en la línea nueva, la categoría Relación de dependencia trae marcado el certificado. | |
| G-95 | Reglas del catálogo (D-25) | Quitar el documento de identidad de una categoría y guardar; intentar desactivar el documento de identidad; cambiar «Corresponde a» de un tipo con documentos en legajos. | Se rechaza cada uno con su motivo; desactivar un tipo sin restricciones lo deja Inactivo. | |
| G-96 | Escala del monotributo (D-26) | En Gestión → Categorías del monotributo, registrar una escala con fecha pasada; luego con el tope de B menor que el de A; luego una válida desde el 01/02/2027. | Las dos primeras se rechazan; la válida figura como «Próxima» en las escalas registradas. | |
| G-97 | Cliente monotributista (D-26) | Registrar un monotributista sin categoría; luego con categoría A e ingresos de $ 1.200.000; luego con categoría B. | «Seleccione la categoría del monotributo.»; «Doce meses de los ingresos declarados… superan el tope de la categoría A…»; con B se registra y el perfil muestra la categoría. | |

## Registro

| Fecha | Responsable | Casos OK | Fallas (issues) | Observaciones |
|---|---|---|---|---|
| 06/11/2026 | Iván Ezequiel Thoux | 97 de 97 | Ninguna | Circuito completo de la solicitud, observaciones de la presentación (G-86 a G-97), vencimientos y permisos por rol correctos. Iteración 2 aprobada. |
