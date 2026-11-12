# Prueba funcional de la iteración 4

Recorrido manual de los casos de uso de la iteración 4. Se completa por bloques, a medida que se construyen:

- **Bloque A, portal de autogestión (SS-07, D-42):** CU-45 a CU-49, CU-75, CU-98 y CU-104, con la bandeja de pre-registros del panel y la consulta del padrón de ARCA en el alta (CU-11 A9).
- **Bloque B, campañas crediticias (SS-08):** pendiente.
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

## Registro

| Fecha | Responsable | Casos OK | Fallas (issues) | Observaciones |
|---|---|---|---|---|
| | Iván Ezequiel Thoux | de 44 (bloque A) | | |
