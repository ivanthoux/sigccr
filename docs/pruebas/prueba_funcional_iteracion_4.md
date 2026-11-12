# Prueba funcional de la iteración 4

Recorrido manual de los casos de uso de la iteración 4. Se completa por bloques, a medida que se construyen:

- **Bloque A, portal de autogestión (SS-07, D-42):** CU-45 a CU-49, CU-75, CU-98 y CU-104, con la bandeja de pre-registros del panel y la consulta del padrón de ARCA en el alta (CU-11 A9).
- **Bloque B, campañas crediticias (SS-08, D-43):** CU-50, CU-51, CU-52, CU-93 y CU-94, con la consulta de las propuestas de refinanciación desde el portal (CU-43 A5).
- **Bloque C, reportes y auditoría (SS-09) y régimen informativo (CU-105):** pendiente.
- **Bloque D, copias de seguridad (SS-11):** pendiente.

Cada caso se marca con **OK** o **Falla**. Una falla se registra como *issue* con la etiqueta `bug`, el identificador del caso y lo observado. La batería de punta a punta se ejecuta una sola vez, al cierre de la iteración (D-41). Con la prueba aprobada se publica la etiqueta `v0.5.0`.

## Preparación

1. Actualizar el entorno y aplicar la migración 26 (pre-registros). El bloque A no agrega permisos.
   ```bash
   git checkout develop && git pull
   docker compose up -d --force-recreate web app scheduler
   docker compose exec app composer migrar
   ```
2. Cargar los datos de demostración, si todavía no están (guía de demostración, sección 11):
   ```bash
   docker compose exec -e SIGCCR_DB_GRUPO=migraciones app php spark db:seed DemostracionSeeder
   ```
3. Los correos no se envían: se consultan, con su enlace, con
   ```bash
   docker compose exec app php spark sigccr:correos
   ```
4. **Activar el portal de dos clientes de demostración (CU-04).** En `/portal/activar`, con tipo DNI, ingresar el número y el correo; abrir el enlace de `sigccr:correos` y definir la contraseña `Portal-Prueba-2026`.

   | Cliente | DNI | Correo | Situación |
   |---|---|---|---|
   | Franco Molina | 35666406 | `franco.molina@demo.local` | Crédito Motos en mora: cuota 1 con 45 días y cuota 2 con unos 15 días de atraso |
   | Joaquín Medina | 38111404 | `joaquin.medina@demo.local` | Crédito Motos con la cuota 1 vencida hace 2 días, dentro de la tolerancia |

   Los atrasos son los del día en que se cargó el seeder y avanzan un día por cada día real.
5. Abrir dos ventanas: una normal para el portal y otra privada para el panel, para no mezclar las sesiones.
6. **Personas para el pre-registro.** Los CUIL terminan en 0 o son válidos para el simulador del padrón:

   | Persona | DNI | CUIL | Correo | Uso |
   |---|---|---|---|---|
   | Sofía Benítez | 41555104 | `27-41555104-0` | `sofia.benitez@prueba.local` | Pre-registro que se completa (I-01 a I-09) |
   | Mateo Acosta | 40222309 | `20-40222309-0` | `mateo.acosta@prueba.local` | Pre-registro que se descarta (I-11) |

   En el alta, el simulador del padrón responde según el CUIL: Sofía y Mateo devuelven sus datos; un DNI que empieza con 99 (`20-99000001-9`) simula el servicio sin respuesta y uno con 98 (`20-98000001-0`), sin datos.

## 1. Pre-registro en el portal (CU-104) y alta desde el pre-registro (CU-11)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| I-01 | Acceso al pre-registro | Sin sesión, abrir `/portal/login`. | Debajo de «Activar o rehabilitar acceso» aparece «¿Todavía no es cliente? Pre-registrarse», que abre el formulario con nombre, apellido, documento, CUIL, correo, teléfono opcional y la aceptación de la política de datos. | |
| I-02 | Validaciones | Enviar: vacío; con Sofía sin marcar la política; con el CUIL `27-41555105-9` (válido, de otro DNI); con `27-41555104-1`; con el correo `sofia@`. | «Revise los datos marcados.» con el mensaje de cada campo: obligatorios; «Para continuar, acepte la política de tratamiento de datos personales.» (A4); «El CUIL no corresponde al número de documento.»; «El dígito verificador no corresponde: … el último dígito es 0.»; «Ingrese un correo electrónico válido…». | |
| I-03 | Persona que ya es cliente | Pre-registrar con el DNI de Franco Molina (35666406, CUIL `20-35666406-0`) y otro correo. | «Usted ya es cliente de la entidad: active o rehabilite su acceso al portal con «Activar mi acceso»…» (A1). No se crea el pre-registro. | |
| I-04 | Correo de otro cliente | Pre-registrar a Sofía con el correo `camila.herrera@demo.local`. | El campo correo: «Ese correo ya está registrado para otro cliente: ingrese otro.» (A2). | |
| I-05 | Pre-registro válido | Pre-registrar a Sofía con sus datos. | Pantalla «Verifique su correo» con la dirección. En `sigccr:correos`, un correo «Verificar mi correo» que vence en 30 minutos. | |
| I-06 | Verificación del correo | Abrir el enlace y presionar «Verificar mi correo». | «Su correo quedó verificado. Acérquese a la sucursal con su documento…». Volver a usar el mismo enlace: «El enlace de verificación expiró o ya no es válido…» (uso único, RN-03). | |
| I-07 | Reinicio sin verificar (A3) | Pre-registrar a Mateo y no abrir el enlace. Repetir el pre-registro con los mismos datos. | Llega un enlace nuevo; el primero ya no es válido. En el panel figura un solo pre-registro de Mateo, «Sin verificar el correo». Verificar el segundo enlace. | |
| I-08 | Bandeja y alerta | En el panel, como Analista (Martín López), abrir *Crédito → Pre-registros del portal* y *Alertas*. | La bandeja lista a Sofía y a Mateo como pendientes de validación, del más antiguo al más reciente. Hay una alerta por cada uno con «Ver el pre-registro». Como Cajero, la opción no aparece y `/admin/pre-registros` responde 403. | |
| I-09 | Completar el alta | Abrir el pre-registro de Sofía y presionar «Completar el alta». Presionar «Consultar el padrón de ARCA» con su CUIL. Completar fecha de nacimiento, estado civil, nacionalidad, ocupación, empleador, categoría (relación de dependencia), ingresos ($ 1.200.000), domicilio, persona expuesta «No», adjuntar el DNI y registrar. | El formulario llega con nombre, apellido, documento, CUIL, correo y teléfono, y el aviso «Alta desde un pre-registro del portal…». El padrón responde «Datos propuestos por el padrón de ARCA…» con el domicilio fiscal Belgrano 845. Al registrar: «Cliente registrado…». El pre-registro pasa a *Completado* con el enlace al cliente y la alerta queda resuelta. | |
| I-10 | Padrón sin respuesta o sin datos | En *Registrar cliente*, consultar el padrón con `20-99000001-9`, con `20-98000001-0` y con `20-11111111-0`. | «El padrón de ARCA no responde: complete los datos manualmente.»; «El padrón no tiene datos para ese CUIL…»; con el tercero, el mensaje del dígito verificador. Ningún dato del formulario cambia. | |
| I-11 | Descartar | Abrir el pre-registro de Mateo; descartar con el motivo «abc» y después con «No se presentó a verificar su identidad.». | Con «abc»: «Indique el motivo de la operación (al menos 5 caracteres).». Con el motivo válido, después de confirmar: «Pre-registro descartado.»; queda *Descartado* con el motivo y el analista. Ya no se puede completar ni volver a descartar. | |

## 2. Perfil y preferencias de comunicación (CU-45 y CU-98)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| I-12 | Funciones del portal | Ingresar al portal como Franco Molina. | En el inicio, *Estado de cuenta*, *Pagar una cuota*, *Comprobantes*, *Mis solicitudes* y *Mi perfil* son enlaces. *Refinanciaciones y ofertas* sigue como «Próximamente» (bloque B). | |
| I-13 | Perfil | Abrir *Mi perfil* y desplegar «Solicitar corrección de datos». | Identificación (nombre, DNI, CUIL 20-35666406-0, nacimiento y estado civil), contacto con el correo y su estado de verificación, datos laborales y económicos (Chofer, Transporte Apóstoles S.R.L., relación de dependencia, $ 2.000.000) y el estado de las comunicaciones comerciales. La corrección indica que se hace en la sucursal; nada se modifica. | |
| I-14 | Revocar el consentimiento | En *Modificar preferencias de comunicación*, presionar «Revocar el consentimiento» y cancelar el aviso del navegador; repetir y aceptar. | Al cancelar no cambia nada (A3). Al aceptar: «Consentimiento revocado: no recibirá nuevas comunicaciones comerciales…». El estado actual indica la fecha y «por el portal». | |
| I-15 | Otorgar el consentimiento | Presionar «Otorgar el consentimiento» y aceptar. | «Consentimiento otorgado…». En el panel, el perfil del cliente muestra la preferencia vigente; en `consentimientos` hay dos registros nuevos con medio `Portal`. | |

## 3. Cambio de correo por autogestión (CU-75)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| I-16 | Validaciones | En *Mi perfil → Cambiar correo electrónico*, enviar: vacío; `franco.molina@demo.local`; `camila.herrera@demo.local`. | «Ingrese el nuevo correo electrónico.»; «El nuevo correo es igual al actual.»; «Ese correo ya está registrado para otro cliente: ingrese otro.» (A1). | |
| I-17 | Solicitud | Enviar `franco.nuevo@demo.local`. | «Le enviamos un enlace a su correo actual…». En `sigccr:correos`, el correo «Confirmar el cambio» va a `franco.molina@demo.local` y nombra el correo nuevo. El perfil sigue mostrando el correo anterior. | |
| I-18 | Confirmación desde el correo actual | Abrir el enlace (puede ser en la ventana privada, sin sesión) y confirmar. | «Cambio confirmado. Enviamos un enlace a franco.nuevo@demo.local…». Llega «Verificar mi correo nuevo» a la dirección nueva. El perfil muestra «Cambio pendiente de verificación». Volver a usar el enlace de confirmación: «…expiró o ya no es válido…». | |
| I-19 | Verificación del correo nuevo | Abrir el segundo enlace y confirmar. Cerrar sesión e ingresar con el correo anterior y después con el nuevo. | «Su correo quedó verificado…». El correo anterior ya no ingresa; el nuevo sí, con la misma contraseña. El perfil muestra `franco.nuevo@demo.local` verificado. | |
| I-20 | Autorizaciones invalidadas | Antes de I-19, pedir «¿Olvidó su contraseña?» con el correo anterior; después de I-19, abrir ese enlace. | El enlace de recuperación del correo sustituido ya no es válido (RN-58). | |

## 4. Solicitudes (CU-46)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| I-21 | Listado | Como Franco, abrir *Mis solicitudes*. | Su solicitud de Motos por $ 800.000 en 18 cuotas, *Liquidada*, sin acción pendiente. | |
| I-22 | Detalle | Abrir la solicitud. | Estado y fecha, la documentación presentada con la revisión verificada, la evaluación (automática, finalizada, favorable), la alternativa confirmada con su CFTEA, la aprobación, la liquidación con el neto transferido y «Ver el plan de cuotas». Aclara que la solicitud está liquidada. | |
| I-23 | Solicitud en curso | En el panel, como Analista, simular y registrar a Franco una solicitud de Electrodomésticos por $ 100.000 en 6 cuotas; verificarla con el recibo de sueldo *Observado* (motivo «Recibo ilegible»). Volver al portal. | La solicitud nueva figura *Pendiente de documentación* con «Presente en la sucursal la documentación observada o faltante.»; en el detalle, el documento observado con su motivo. | |
| I-24 | Solicitud ajena | Cambiar el número de la dirección por el de una solicitud de otro cliente (por ejemplo, la de Joaquín), y después por 999999. | En los dos casos vuelve a *Mis solicitudes* con «No encontramos la información solicitada entre sus operaciones.». En la auditoría hay dos registros `portal.acceso-denegado`, «Objeto de otro cliente» y «Objeto inexistente». | |

## 5. Estado de cuenta (CU-47)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| I-25 | Resumen | Como Franco, abrir *Estado de cuenta*. | Saldo deudor total con su discriminación (punitorios, interés, gastos, IVA y capital), cuotas pendientes y **2 vencidas** con sus días y punitorios, próximos vencimientos, «No tiene saldo a favor» y el crédito *En mora*. Los importes coinciden con los de *Cobro en ventanilla* del mismo día en el panel. | |
| I-26 | Plan y cancelación anticipada | Abrir el crédito. | Las 18 cuotas con vencimiento, capital, interés, gastos, punitorios a hoy, total actualizado y estado; las vencidas resaltadas. Arriba, el importe para cancelar hoy, igual al de *Cancelación anticipada* del panel; no se registra ninguna operación (A7). | |
| I-27 | Cuota vencida con punitorios | Abrir la cuota 1. | «Vencida hace 45 día(s): superada la tolerancia de 3 día(s), se exigen intereses punitorios desde el primer día de atraso.» y el detalle por concepto. | |
| I-28 | Cuota dentro de la tolerancia | Ingresar como Joaquín Medina y abrir la cuota 1 de su crédito. | «Vencida hace 2 día(s), dentro de la tolerancia de 3 día(s): todavía no se exigen intereses punitorios.» (A3); punitorios en $ 0,00. | |
| I-29 | Sin deuda | Activar el portal de Sofía Benítez (I-09) y abrir su estado de cuenta. | «No tiene deuda pendiente.», «No tiene saldo a favor.» y «No tiene créditos.» (A1). | |
| I-30 | Crédito ajeno | Como Franco, abrir `/portal/estado-cuenta/creditos/<id del crédito de Joaquín>`. | Vuelve al estado de cuenta con el mensaje genérico y queda el registro `portal.acceso-denegado`. | |

## 6. Pago electrónico (CU-49)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| I-31 | Selección no consecutiva | Como Franco, en *Pagar una cuota*, marcar la cuota 2 sin la 1 y generar. | El mensaje de RN-29: la selección debe comenzar por la cuota más antigua. No se genera la orden. | |
| I-32 | Generar la orden | Marcar la cuota 1, verificar que «Total a pagar» coincide con su total actualizado, generar y aceptar el aviso. | «Orden generada. El pago se acreditará cuando la pasarela lo confirme…». La orden muestra importe, vencimiento (00:00 de mañana), identificación, la cuota incluida y «Pagar en la pasarela». La cuota sigue pendiente. | |
| I-33 | Orden existente | Volver a *Pagar una cuota*. Quitar el `disabled` de la cuota 1 con las herramientas del navegador, marcarla y generar. | La cuota 1 figura «En la orden N.º …» y no se puede marcar. Forzándola: vuelve a la misma orden con «Alguna de las cuotas ya integra esta orden…» (A8); no hay una orden nueva. | |
| I-34 | Pagar | Abrir «Pagar en la pasarela», aprobar en el simulador y ejecutar `docker compose exec app php spark pagos:notificaciones`. | La orden queda *Pagada*, la cuota 1 *Pagada* en el plan y el pago figura en *Comprobantes*. En el panel, la orden aparece en *Pagos electrónicos* con el medio presentado «Portal». | |
| I-35 | Pasarela no disponible | Con el simulador configurado para no responder (si está disponible en `/simulador`), generar una orden por la cuota 2. | «La pasarela de pagos no está disponible: no se generó la orden…» (A2); no queda orden registrada. Si el simulador no ofrece esa opción, marcar el caso como no aplicable. | |

## 7. Comprobantes (CU-48)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| I-36 | Listado | Como Franco, abrir *Comprobantes*. | El pago de I-34 con número de comprobante, fecha y hora, importe, medio «Pago electrónico», crédito y cuota (por ejemplo «13/1») y estado «Confirmado». | |
| I-37 | Consulta | Abrir el comprobante. | Datos del cliente, importe, canal, medio con su operación y la cuota con su discriminación por concepto. En el panel, la auditoría registra `cobros.consultar-comprobante`. | |
| I-38 | Descargar o imprimir | Presionar «Descargar o imprimir» y guardar como PDF. | Se abre el diálogo de impresión con el comprobante sin el menú; el contador de reemisiones aumenta en 1 y la auditoría registra `cobros.reemitir-comprobante`. No hay un cobro nuevo. | |
| I-39 | Comprobante anulado | En el panel, anular como Cajero un cobro de ventanilla de un cliente con portal (por ejemplo, cobrar y anular una cuota de Joaquín). En el portal de ese cliente, abrir el comprobante. | El listado lo muestra «Anulado» y el comprobante, el aviso con la fecha y el motivo de la anulación (A2). | |
| I-40 | Comprobante ajeno | Abrir `/portal/comprobantes/<id de un cobro de otro cliente>`. | Vuelve a *Comprobantes* con el mensaje genérico y el registro `portal.acceso-denegado`. | |

## 8. Generales

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| I-41 | Sesiones separadas | Con la sesión del panel abierta, abrir `/portal/perfil`; con la del portal, abrir `/admin`. | Cada ámbito pide su propio inicio de sesión (RN-08). | |
| I-42 | Sesión vencida | Dejar el portal 15 minutos sin actividad y abrir *Estado de cuenta*. | Lleva al inicio de sesión del portal con el aviso de sesión vencida (A4/A5). | |
| I-43 | Pantalla angosta | Abrir el estado de cuenta, el plan, *Pagar una cuota*, el comprobante y el pre-registro con 360 px de ancho. | Sin desplazamiento horizontal de la página; las tablas se desplazan dentro de su recuadro. | |
| I-44 | Auditoría | Revisar `registros_auditoria` de esta prueba. | Hay registros de `pre-registros.registrar`, `pre-registros.verificar`, `pre-registros.descartar`, `clientes.consultar-padron`, `clientes.consentimiento`, `clientes.cambio-correo.solicitud`, `clientes.cambio-correo.confirmacion`, `clientes.verificar_correo`, `portal.consultar-perfil`, `portal.consultar-estado-cuenta`, `portal.consultar-plan`, `portal.consultar-solicitud`, `pagos.generar-orden` y `portal.acceso-denegado`, con el cliente como responsable cuando opera desde el portal. | |

## 9. Campañas crediticias (CU-51, CU-93, CU-50, CU-52, CU-94 y CU-43 A5)

**Preparación del bloque B.**

1. Aplicar la migración 27 y recrear el planificador, que ahora ejecuta `campanas:cerrar` cada 15 minutos:
   ```bash
   git pull && docker compose up -d --force-recreate web app scheduler
   docker compose exec app composer migrar
   ```
2. Clientes que intervienen:
   - **Joaquín Medina:** portal activado en la preparación, consentimiento comercial otorgado en el alta del seeder y crédito Motos con pocos días de atraso.
   - **Sofía Benítez:** portal activado en I-29; otorgar el consentimiento comercial desde su portal (CU-98) antes de I-47.
   - **Franco Molina:** queda fuera por su atraso de más de 45 días.
3. La campaña se activa al ejecutar la proyección de fondos, que puede repetirse en el día:
   ```bash
   docker compose exec app php spark fondos:proyectar
   ```

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| I-45 | Parámetros vigentes | Como Gerente (Laura Pérez), abrir *Gestión → Parámetros de campañas*. Como Analista, abrir `/admin/parametros/campanas`. | Formulario con activación (umbral $ 5.000.000, 30 días, 90 %, reserva $ 1.000.000), línea sin seleccionar, límites, elegibilidad, comunicación (solo correo) y vigencia (15 días). La versión 1 figura como vigente. El Analista recibe 403. | |
| I-46 | Valores incoherentes | Registrar con el capital máximo en 0, el factor en 120, sin línea, sin categorías y con la fecha de ayer. | «El capital máximo de la campaña debe ser mayor que cero.», «El factor de cobrabilidad debe estar entre 0 % y 100 %.», «Seleccione una línea de crédito activa…», «Seleccione al menos una categoría…» y, con la fecha anterior, «…no se admite retroactividad.». No se registra ninguna versión (A1). | |
| I-47 | Nueva versión | Registrar: umbral **$ 0**, período 30, factor 90, reserva mínima $ 0, línea **Electrodomésticos (ELECTRO)**, capital máximo **$ 3.000.000**, 200 ofertas, categoría *Relación de dependencia*, antigüedad 0, morosidad máxima **30 días**, admite créditos vigentes, correo, mensaje «Oferta especial de fin de año», duración 15 días, cierre por disponibilidad marcado, rige desde hoy, motivo «Prueba funcional de campañas». Aceptar la confirmación. | «Se registró la versión 2 de los parámetros de campañas…» con el aviso de RN-40. El historial muestra la versión 1 como antecedente. | |
| I-48 | Activación | Ejecutar `fondos:proyectar`. | La consola informa «activada la campaña N.º 1 con … oferta(s)». El Gerente tiene la alerta «Se activó la campaña N.º 1…» con «Ver la campaña». `sigccr:correos` muestra un correo «tiene una oferta de crédito preaprobada» para Joaquín y para Sofía, con el mensaje adicional y el enlace a la oferta. No hay ofertas para Franco (atraso) ni para los clientes sin portal activado. | |
| I-49 | Campaña activa única (RN-66) | Volver a ejecutar `fondos:proyectar`. | «la campaña activa continúa». No se crea otra campaña. | |
| I-50 | Consulta de campañas (CU-50) | Como Analista, abrir *Cartera → Campañas*; filtrar por estado *Activa*, por línea y por un número inexistente; abrir la campaña y la oferta de Joaquín. | El listado muestra número, activación, línea, condiciones, vigencia, capital asignado (el menor entre $ 3.000.000 y la disponibilidad proyectada), comprometido $ 0 y estado. El número inexistente informa que no hay coincidencias (A1). El detalle muestra la proyección y el umbral, las versiones de parámetros y de condiciones, los resultados, las ofertas por cliente y los correos por estado. La oferta muestra importe, tasas, vigencia, respuesta y comunicaciones (A4). | |
| I-51 | Ofertas en el portal | Ingresar como Joaquín y abrir *Refinanciaciones y ofertas* (ya no figura «Próximamente» en el inicio). | Su oferta vigente con línea, importe máximo, cuotas, TNA y vigencia. Al abrirla: TNA, TEA, CFTEA, cuota estimada, vigencia y la lista de alternativas que puede elegir hoy, ninguna mayor que el importe ofrecido, con la primera marcada. | |
| I-52 | Aceptar | Elegir una alternativa menor que la máxima, presionar «Aceptar la oferta», cancelar el aviso del navegador y después aceptarlo. | Al cancelar no cambia nada (A5). Al aceptar se abre la solicitud generada: «Aceptó la oferta: se generó la solicitud N.º …». La solicitud queda *Pendiente de verificación* con la alternativa elegida. En el panel, la solicitud de Joaquín figura con origen oferta y sin Analista. La campaña muestra el capital comprometido y la oferta *Aceptada* con su solicitud. En *Fondos*, «Reservas y compromisos» aumentó en el importe aceptado (RN-41). | |
| I-53 | Segunda aceptación (A3) | Volver a la oferta. Si se tiene la pantalla anterior abierta en otra pestaña, enviarla de nuevo. | La oferta muestra «Aceptó esta oferta…» con el enlace a la solicitud y sin botones. El reenvío responde «Ya aceptó esta oferta: generó la solicitud N.º … (A3, RN-42).». | |
| I-54 | Compromiso convertido en reserva | En el panel, verificar la solicitud de I-52 con toda la documentación conforme, esperar la evaluación y confirmar la alternativa elegida por el cliente (CU-79), que queda primera. | La solicitud queda *Aprobada pendiente de liquidación*. En *Fondos*, «Reservas y compromisos» **no aumenta otra vez**: el compromiso se convirtió en reserva por el neto a desembolsar (RN-41). | |
| I-55 | Rechazar (A1) | Como Sofía, abrir su oferta y presionar «Rechazar la oferta»; aceptar el aviso. | «Rechazó la oferta. No se generó ninguna solicitud.». La oferta figura *Rechazada* en el portal y en la campaña. | |
| I-56 | Cierre por vigencia (CU-94) | Con una pestaña de la oferta de otro cliente abierta (o con la de Joaquín antes de I-52, si se repite), adelantar el cierre con SQL y ejecutar el temporizador:<br>`UPDATE dbo.campanas SET vence = DATEADD(minute, -1, SYSDATETIMEOFFSET()) WHERE estado = 'Activa';`<br>`docker compose exec app php spark campanas:cerrar` | «Campaña … cerrada (Vigencia).». La campaña queda *Finalizada* por vencimiento de la vigencia, con el aviso de RN-43. Las ofertas sin respuesta quedan *Vencidas* y sus correos pendientes, cancelados. La alerta al Gerente informa las aceptadas. La solicitud de I-52 sigue su trámite. Enviar la aceptación desde la pestaña abierta: «La oferta perdió vigencia porque la campaña finalizó…» (A2). | |
| I-57 | Cierre por cupo | Registrar una versión de parámetros con capital máximo igual al importe que va a aceptar el cliente (por ejemplo, $ 100.000) y ejecutar `fondos:proyectar`. Aceptar la oferta de Sofía por $ 100.000. | Se activa la campaña N.º 2. Con la aceptación el comprometido alcanza el asignado y la campaña queda *Finalizada* por capital asignado alcanzado; las demás ofertas quedan vencidas. Otra aceptación posterior responde que la campaña ya no tiene cupo o que finalizó (A2). | |
| I-58 | Cierre por disponibilidad | Con una campaña activa (repetir la activación), registrar una versión con un umbral de $ 999.999.999 y ejecutar `fondos:proyectar`. | «campaña cerrada por disponibilidad insuficiente». La campaña queda *Finalizada* por disponibilidad insuficiente (CU-92 A1). Sin el cierre por disponibilidad marcado, la campaña continúa. | |
| I-59 | Sin clientes elegibles (A1) | Registrar una versión con umbral $ 0 y solo la categoría *Empresa*; ejecutar `fondos:proyectar`. | «no se activó: sin clientes elegibles». El Gerente recibe la alerta con «Configurar las campañas». No se crea campaña. | |
| I-60 | Línea suspendida (A2) | Suspender la línea ELECTRO (CU-18), restablecer la categoría *Relación de dependencia* y ejecutar `fondos:proyectar`. Reactivar la línea al terminar. | «no se activó: La línea Electrodomésticos habilitada para campañas no está activa.» y la alerta al Gerente. | |
| I-61 | Propuestas de refinanciación en el portal (CU-43 A5) | En el panel, simular y registrar una propuesta de refinanciación para Franco Molina. Ingresar al portal como Franco y abrir *Refinanciaciones y ofertas*; abrir la propuesta. Con su número, abrir `/portal/propuestas/refinanciaciones/<id de otra propuesta>`. | La propuesta figura con su estado. El detalle muestra la deuda incluida, el capital refinanciado, el concepto no capitalizable, la tasa, el plan si se aplicara hoy y el aviso del estado (pendiente de resolución). La propuesta ajena vuelve al listado con el mensaje genérico y el registro `portal.acceso-denegado`. | |
| I-62 | Auditoría del bloque B | Revisar `registros_auditoria`. | Registros de `parametros.campanas`, `fondos.proyectar`, `campanas.activar` (exitoso y rechazado), `campanas.consultar`, `campanas.consultar-oferta`, `portal.consultar-oferta`, `campanas.aceptar-oferta`, `campanas.rechazar-oferta`, `campanas.cerrar` con sus resultados y `portal.consultar-propuesta`. | |

## Registro

| Fecha | Responsable | Casos OK | Fallas (issues) | Observaciones |
|---|---|---|---|---|
| | Iván Ezequiel Thoux | de 44 (bloque A) | | |
| | Iván Ezequiel Thoux | de 18 (bloque B) | | |
