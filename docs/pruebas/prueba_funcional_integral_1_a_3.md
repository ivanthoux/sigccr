# Prueba funcional integral: iteraciones 1 a 3

Prueba de regresión antes de la iteración 4. Recorre en un solo circuito todo lo construido hasta la iteración 3:

- **Iteración 1:** acceso, usuarios, clientes, portal, líneas y comunicaciones.
- **Iteración 2:** parámetros, calendario, fondos, cajas, simulación, solicitud, evaluación, alternativas, resolución, contrato y liquidación.
- **Iteración 3:** cobranza, morosidad, pagos electrónicos, saldos a favor, refinanciación, revocación, cancelación anticipada y procesos automáticos.

Las pruebas por iteración (`prueba_funcional_iteracion_1.md` a `_3.md`) detallan más validaciones de cada pantalla. Esta prueba conserva las principales y agrega el circuito completo con datos nuevos. Cada dato que hay que cargar está en el **Anexo A**. Los archivos para adjuntar están en la carpeta **`Datos de prueba`** de la documentación (Anexo B).

Cada caso se marca con **OK** o **Falla**. Una falla se registra como *issue* con la etiqueta `bug`, el identificador del caso y lo observado. La prueba se aprueba cuando todos los casos dan OK o sus fallas están corregidas; con eso se publica `v0.4.0` y empieza la iteración 4.

Duración estimada: dos jornadas. Jornada 1: partes 0 a 2 (hasta la sección 14). Jornada 2: parte 3. El caso R-124 conviene hacerlo al día siguiente de R-123, para que el cálculo de punitorios corra de un día para el otro.

## Parte 0. Preparación

### 0.1 Instalación limpia (recomendada)

Así también se prueban las 23 migraciones y las semillas desde cero. **Borra la base actual.**

```bash
git checkout develop && git pull
docker compose down -v
docker compose up -d
docker compose exec app bash docker/inicializar.sh
```

`inicializar.sh` crea la base, aplica las migraciones, carga las semillas y pide los datos del Administrador inicial (Anexo A.1). Al terminar, el resumen tiene que mostrar OK en todos los pasos, incluidas las pruebas unitarias y la verificación de punta a punta.

Opción sin borrar: `composer migrar` y `composer semillas` sobre la base actual. Los datos del Anexo A no deberían chocar con los anteriores; si algún documento o correo ya existe, cambiar el último dígito del DNI y recalcular el CUIL con la pantalla, que indica el dígito correcto.

### 0.2 Herramientas

| Para | Comando |
|---|---|
| Ver los correos simulados con su enlace | `docker compose exec app php spark sigccr:correos 5` |
| Ejecutar un proceso sin esperar | `docker compose exec app php spark <proceso>` |
| Procesos del planificador | `docker compose exec scheduler cat /etc/cron.d/sigccr` |
| Consultas SQL de apoyo | `Datos de prueba/consultas_de_apoyo.sql` (Anexo C) |

Procesos: `comunicaciones:enviar` (cada minuto), `evaluacion:ejecutar` (cada 5 minutos), `vencimientos:controlar` (cada 15 minutos), `cartera:punitorios` (00:05), `pagos:notificaciones` (cada minuto), `pagos:conciliar` (cada 30 minutos), `fondos:proyectar` (06:00).

Simuladores:

- **Correo.** No envía nada: guarda los mensajes. Falla a propósito con `falla.transitoria@…`, `falla.definitiva@…` e `incierto@…`.
- **Central de Deudores.** El último dígito del CUIL define la respuesta: 0 sin deudas, 1 a 3 situación 1 a 3, 5 situación 5 (excluyente) y 9 servicio no disponible.
- **Pasarela de pagos.** Página de pago en el enlace de cada orden; devoluciones en `/simulador/devoluciones`.

---

## Parte 1. Iteración 1

### 1. Acceso al panel (CU-01 a CU-03)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-001 | Inicio de sesión | Ingresar a `https://localhost/admin/login` con el Administrador (A.1). | Pantalla principal con el menú del rol; el pie muestra nombre y rol. | |
| R-002 | Contraseña incorrecta y bloqueo | Fallar la contraseña 5 veces seguidas con el correo del Administrador. | «Usuario o contraseña incorrectos.»; al tercer fallo «Le quedan 2 intentos…», al cuarto «Le queda un intento…», al quinto el bloqueo de 15 minutos aun con la contraseña correcta. | |
| R-003 | Correo inexistente | Fallar 5 veces con `nadie@prueba.local`. | La misma secuencia de mensajes (no revela que la cuenta no existe). | |
| R-004 | Campos vacíos | Enviar el formulario vacío. | «Ingrese el correo electrónico y la contraseña.» | |
| R-005 | Recuperar acceso | Esperar el fin del bloqueo; «¿Olvidó su contraseña?» con el correo del Administrador y con `nadie@prueba.local`. | El mismo mensaje en los dos casos; solo el existente recibe el enlace (`sigccr:correos`). | |
| R-006 | Contraseña nueva | Abrir el enlace y probar las contraseñas inválidas de A.1; luego la válida. | Rechaza cada inválida con su motivo (largo, contraseña comprometida, confirmación distinta); la válida permite ingresar. Reabrir el enlace: «El enlace expiró o ya no es válido…». | |
| R-007 | Cierre de sesión | Menú → Cerrar sesión. | «La sesión se cerró correctamente.»; `/admin` vuelve a pedir credenciales. | |

### 2. Usuarios internos (CU-05 a CU-10 y CU-53)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-008 | Registrar usuarios | Registrar los cinco usuarios de A.1 (Gerente, Analista, dos Cajeros y un Cajero para desactivar). | Cada uno queda Pendiente de activación y recibe el enlace de 24 horas. | |
| R-009 | Validaciones del alta | Con los datos «inválidos» de A.1: nombre vacío, correo `laura@`, DNI `26A11222`, DNI `26111222.`, y repetir el correo y el DNI de la Gerente. | Cada error junto a su campo; no se crea el usuario. Con `26.111.222` se acepta como 26111222 (luego cancelar). | |
| R-010 | Definición inicial | Abrir cada enlace, definir la contraseña de A.1 e ingresar con cada usuario. | Cada usuario ve solo las opciones de su rol. | |
| R-011 | Localizar y edición simultánea | Buscar por nombre, documento, rol y estado. Abrir la modificación de Tomás Silva en dos pestañas y guardar en ambas. | Resultados filtrados; la segunda: «El registro … fue modificado por otra operación…» (RN-24). | |
| R-012 | Asignar rol | Con Tomás con la sesión abierta en otro navegador, cambiarlo a Analista y luego volverlo a Cajero. | Su sesión finaliza en la siguiente acción; al ingresar ve el menú del rol nuevo. | |
| R-013 | Desactivar y reactivar | Desactivar a Rocío Báez con motivo; intentar desactivar al propio Administrador; reactivar a Rocío. | Rocío no puede ingresar; desactivarse a sí mismo se rechaza (RN-05); reactivada vuelve a ingresar. | |
| R-014 | Sin permiso | Como Cajero, abrir `/admin/usuarios`. | Acceso denegado (403), sin datos. | |

### 3. Clientes (CU-11 a CU-15, CU-54, RN-80 y D-26)

Como Analista. Los datos están en A.2 y A.3, y los archivos en la carpeta `Datos de prueba/clientes`.

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-015 | Registrar persona humana | Registrar a Ana Gómez (C-01) con su DNI y sus recibos de sueldo. | Perfil con el aviso de verificación del correo; cuenta del portal Pendiente de activación. | |
| R-016 | Validaciones del alta | Con los datos de Sofía Menor (C-14), probar en orden: fecha de nacimiento de menor de 18 años; CUIL `27480000001`; sin aceptar la política de datos; sin adjuntar el DNI; adjuntar `archivo_grande_mas_de_5MB.pdf` y `archivo_no_admitido.txt`; repetir el DNI y el correo de Ana. | Cada error junto a su campo; no se registra ni queda ningún archivo guardado. | |
| R-017 | CUIL según el prefijo | Para el DNI de Bruno (C-02), probar el CUIL `27312223350` (prefijo cambiado sin cambiar el dígito) y `30312223350`; luego el correcto. | El primero indica qué dígito corresponde; el 30 se rechaza para una persona humana; el correcto se acepta. | |
| R-018 | Identificación UIF | Registrar a Bruno sin nacionalidad, sin actividad principal y sin la declaración de persona expuesta; luego completo. | «Registre la declaración jurada sobre la condición de persona expuesta políticamente (RN-80).» y los otros errores junto a su campo; completo se registra. | |
| R-019 | Resto de los clientes | Registrar los clientes C-03 a C-13 de A.2 con sus archivos. | Todos registrados. Nora Kowalski (C-13), que se declara persona expuesta, genera una alerta para el Gerente. | |
| R-020 | Monotributista | Registrar a Marcos Paredes (C-12) primero sin categoría, luego con la A y por último con la B. | «Seleccione la categoría del monotributo.»; con A: «Doce meses de los ingresos declarados… superan el tope de la categoría A…»; con B se registra. | |
| R-021 | Persona jurídica | Registrar Yerbatera del Sur S.A. (P-01 de A.3) con sus cuatro documentos y el DNI del representante. | Perfil de persona jurídica con los documentos en el legajo. | |
| R-022 | Legajo | En el perfil de Ana, subir `otro_documento_prueba.pdf` y descargarlo. | Aparece en el legajo y se descarga con su nombre original. | |
| R-023 | Localizar | Buscar «Góm», el DNI de Bruno, el CUIT de la pyme, por tipo y por estado. | Sin criterios muestra los clientes registrados más recientemente; cada búsqueda respeta sus filtros. | |
| R-024 | Modificar y cambiar el correo | Cambiar el domicilio de Ana (A.2, columna «Modificación»); cambiar su correo sin evidencia y luego con la evidencia de A.2. | «Datos actualizados.»; sin evidencia se rechaza (RN-58); con evidencia sigue vigente el anterior hasta verificar el nuevo. | |
| R-025 | Historial y comunicaciones | Abrir el historial de Ana; desmarcar «acepta comunicaciones comerciales». | «…no tiene antecedentes crediticios en la entidad»; el perfil muestra que no acepta, con la fecha. | |
| R-026 | Desactivar y reactivar | Desactivar a la pyme con motivo y reactivarla. | «Cliente reactivado. Para volver a usar el portal debe activar o rehabilitar su acceso (CU-04).» | |

### 4. Portal del cliente (CU-01 a CU-04)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-027 | Verificar el correo y activar | Abrir el enlace de verificación de Ana (`sigccr:correos`) y definir la contraseña del portal de A.2. | Lleva a definir la contraseña (D-16); el perfil muestra el correo verificado y la cuenta Habilitada. | |
| R-028 | Activar sin el enlace | Con Bruno, en `https://localhost/portal/activar`, ingresar su DNI y correo, abrir el enlace y definir la contraseña. | Ingresa a la pantalla principal del portal. | |
| R-029 | Datos que no coinciden | Repetir con el DNI de Bruno y el correo de Ana. | «No pudimos verificar los datos ingresados…» | |
| R-030 | Persona jurídica | Activar el portal de la pyme con su CUIT. | Igual que R-028. | |
| R-031 | Sesiones separadas | Con la sesión del portal abierta, abrir `/admin`; cerrar la sesión del portal. | La sesión del portal no abre el panel; el cierre funciona igual que en el panel. | |

### 5. Líneas y catálogos (CU-16 a CU-20, CU-73 y D-23 a D-26)

Como Gerente, con los datos de A.4.

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-032 | Validaciones de la línea | Registrar «Motos» con mínimo y máximo de $ 500.000; con 12 cuotas mínimas y máximas; con edad mínima y máxima de 40; con el gasto porcentual en 15. | «El importe máximo debe ser mayor que el mínimo (RN-14, D-23).», lo mismo para cuotas y edad, y el gasto se rechaza junto a su campo. | |
| R-033 | Registrar las líneas | Registrar «Motos», «Personal» y «Capital de trabajo Pyme» (L-01 a L-03). | «Línea registrada con su versión 1 de condiciones…». «Personal» muestra «0,50 % del capital por cuota». | |
| R-034 | Modificar con confirmación | Modificar la TNA de «Personal» con vigencia desde hoy; probar una fecha pasada. | Se crea la versión 2; la fecha pasada se rechaza. (Con solicitudes en trámite pedirá la confirmación de D-24: se prueba en R-091.) | |
| R-035 | Suspender, consultar y localizar | Suspender «Capital de trabajo Pyme» con motivo; consultarla; reactivarla. Como Analista, abrir una línea. | Historial de versiones y suspensiones; el Analista consulta sin poder modificar. | |
| R-036 | Documentación exigible | En Gestión → Documentación exigible, registrar el tipo de A.5 y marcarlo para Relación de dependencia; intentar desactivar el documento de identidad. | El tipo queda activo; desactivar el documento de identidad se rechaza. | |
| R-037 | Escala del monotributo | En Gestión → Categorías del monotributo, registrar la escala de A.5 con fecha pasada, luego con el tope de B menor que el de A y luego la válida. | Las dos primeras se rechazan; la válida figura como «Próxima». | |

### 6. Comunicaciones y alertas (CU-83 y CU-95)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-038 | Fallida, reintentos e incierta | Los clientes C-07, C-08 y C-09 tienen los correos de falla. Ejecutar `comunicaciones:enviar` varias veces durante 6 minutos. | `falla.definitiva`: Fallida; `falla.transitoria`: reintenta a los 1, 3 y 5 minutos y queda Fallida; `incierto`: En revisión, sin reenviar. Cada una con su alerta. | |
| R-039 | Consultar alertas | Como Administrador, filtrar las alertas por estado, tipo y fecha y abrir una. | Detalle con causa y acción pendiente; queda vista. | |
| R-040 | Alerta de otro rol | Como Cajero, abrir la URL de R-039. | «La alerta no existe o no está dirigida a usted.» | |

---

## Parte 2. Iteración 2

### 7. Parámetros y calendario (CU-58, CU-59, CU-60 y CU-82)

Como Gerente, con los datos de A.6 y A.7.

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-041 | Parámetros de evaluación | Cargar los sugeridos y guardar; probar un peso de 1,5. | Los sugeridos se guardan como versión nueva; el peso inválido se rechaza junto a su campo. | |
| R-042 | Parámetros de estructuración | Probar los valores inválidos de A.6; guardar los válidos. | Cada error junto a su campo («El primer escalón debe ser 100 %…», «Se admiten hasta 6 escalones.»…); los válidos crean la versión. | |
| R-043 | Parámetros de morosidad | Probar los valores inválidos de A.6; guardar los válidos. | «La tasa punitoria diaria debe estar entre 0 % y 1 % por día.», «El tope debe estar entre 0 % y 50 % de la TNA…» y los demás; los válidos crean la versión. | |
| R-044 | Calendario | Cargar los feriados de A.7 de a uno; repetir uno; cargar una fecha pasada; pegar la carga anual de A.7 (con dos líneas erróneas). | Agrega los válidos; el repetido «…ya está registrada (A2)»; el pasado «…es una fecha pasada (A1)»; la carga anual lista las rechazadas con su motivo. | |
| R-045 | Baja de un día | Dar de baja el 25/12/2026 y volver a cargarlo. | «Día no hábil dado de baja…»; se vuelve a cargar sin problema. | |

### 8. Fondos y cajas (CU-85, CU-80, CU-72, CU-31 y CU-81)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-046 | Ingreso de fondos | Como Gerente, en Gestión → Fondos, registrar el ingreso de A.8. | «Movimiento registrado…»; el disponible libre sube en $ 20.000.000. | |
| R-047 | Validaciones y movimiento programado | Probar: importe 0; realizado con fecha futura; programado con fecha pasada. Registrar el egreso programado de A.8 y anularlo. | Cada error junto a su campo; el programado figura y la anulación lo quita. | |
| R-048 | Corrección | Registrar el egreso de A.8 de $ 10.000 y corregirlo con motivo. | Movimiento inverso vinculado; el disponible vuelve al valor anterior; corregir la corrección se rechaza. | |
| R-049 | Cajas | Como Gerente, registrar `CAJA-01` y `CAJA-02` (A.8); probar el código `caja 01!` y repetir `CAJA-01`. | Registradas y habilitadas; el código inválido y el repetido se rechazan. | |
| R-050 | Asignar | Asignar `CAJA-01` a Paula Romero desde hoy; intentar asignarla también a Tomás; asignar `CAJA-02` a Tomás. | La segunda asignación de `CAJA-01` se rechaza («…finalice la asignación vigente…»). | |
| R-051 | Apertura sin efectivo | Como Paula, en Mi caja, abrir la caja. | No pide un importe; «Caja abierta: ya puede registrar cobros por transferencia, depósito o tarjeta.»; una segunda apertura: «Ya tiene una caja abierta (CAJA-01)…». | |
| R-052 | Inhabilitar | Como Gerente, intentar inhabilitar `CAJA-01` (jornada abierta); inhabilitar `CAJA-02` (pide confirmación) y rehabilitarla; volver a asignarla a Tomás. | La primera: «La caja tiene una jornada abierta…»; `CAJA-02`: «Caja inhabilitada…» y «Caja rehabilitada: ya se puede asignar.» | |

### 9. Simulación y comparación (CU-21 y CU-99)

Como Analista Martín López.

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-053 | Simular | Simular para Ana $ 500.000 en 12 cuotas en «Motos». | «Simulación registrada. Es preliminar…»; cuota, TEA, CFTEA, total, plan paginado y el recuadro con los datos del cliente. | |
| R-054 | Validaciones | Importe vacío, `abc`, cuotas `6,5`, $ 100.000 (menor que el mínimo) y 30 cuotas. | Cada error junto a su campo con los límites de la línea. | |
| R-055 | Línea no admitida | Simular para Ana en «Capital de trabajo Pyme». | No permite simularla: figura en «Líneas que no admiten al cliente» con su motivo («La línea no admite personas humanas.»). | |
| R-056 | Gasto porcentual | Simular para Bruno $ 800.000 en 12 cuotas en «Personal». | Cada cuota tiene $ 4.000,00 de gastos y su IVA (0,5 % del capital). | |
| R-057 | Comparar líneas | Comparar para Ana con «Mayor monto prestable» y con «Menor costo (CFTEA)». | Líneas viables con su monto y CFTEA, las no viables con su motivo, y marcadas la de mayor monto y la de menor costo. | |

### 10. Solicitud, verificación y corrección (CU-22, CU-23 y CU-84)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-058 | Registrar | Desde R-053, registrar la solicitud de Ana. | «Solicitud N.º … registrada: queda pendiente de verificación del legajo…» | |
| R-059 | Solicitud repetida | Simular y registrar otra de Ana en «Motos». | «El cliente ya tiene la solicitud N.º … de esta línea en trámite…» | |
| R-060 | Legajo observado | Verificar la de Ana marcando el recibo como Observado, con «Observada» y la observación corta de A.9; luego con la completa. | La corta se rechaza; la completa deja la solicitud en Pendiente de documentación. | |
| R-061 | Corrección | Corregir adjuntando `recibos_sueldo_Gomez.pdf`. | «Corrección registrada: la solicitud pasó a Pendiente de verificación.» | |
| R-062 | Verificar | Verificar con todo Conforme. | Pasa a Pendiente de evaluación y la evaluación automática la toma; al recargar, Pendiente de selección de plan. | |
| R-063 | Rechazo documental | Para Gabriela Duarte (C-07): simular, registrar y rechazar en la verificación, primero sin motivo y luego con el de A.9. | Sin motivo se rechaza (RN-19); con motivo, Rechazada. | |

### 11. Evaluación crediticia (CU-86, CU-24 y CU-25)

Para cada cliente: simular $ 500.000 en 12 cuotas en «Motos», registrar y verificar con todo Conforme (o usar la solicitud ya creada).

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-064 | Favorable | Abrir la evaluación de Ana. | Favorable, con puntaje, categoría, los cuatro indicadores y «sin deudas»; vigencia de 30 días. | |
| R-065 | Situación 1 | Elena Benítez (C-05). | Favorable, con la deuda de BANCO SIMULADO S.A. en situación 1. | |
| R-066 | Análisis gerencial | Federico Acosta (C-06). | Requiere análisis gerencial: pasa a Pendiente de resolución y el Gerente recibe la alerta. | |
| R-067 | Excluyente | Hernán Ojeda (C-08). | Desfavorable por la situación 5 vigente (RN-69); la solicitud queda Rechazada. | |
| R-068 | Contingencia | Inés Villalba (C-09); ejecutar `evaluacion:ejecutar`. | Pendiente de evaluación de contingencia; figura en Crédito → Contingencias. | |
| R-069 | Registrar la contingencia | Probar los datos inválidos de A.9; registrar con los válidos. | Cada error junto a su campo; con los válidos: «Evaluación de contingencia registrada: la solicitud queda pendiente de resolución…» | |
| R-070 | Sin capacidad | Lucía Martínez (C-11, ingresos $ 100.000), $ 1.000.000 en 6 cuotas en «Personal». | Sin alternativas viables: pasa a resolución del Gerente. | |

### 12. Alternativas y resolución (CU-87, CU-64, CU-79 y CU-26)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-071 | Alternativas | Abrir las alternativas de Ana. | Capacidad de pago, monto máximo, la combinación pedida primero y las descartadas con su motivo. | |
| R-072 | Validaciones de la conformidad | Confirmar sin fecha, con fecha futura y sin medio. | Cada error junto a su campo. | |
| R-073 | Aprobación automática | Confirmar la alternativa pedida con la fecha de hoy y «Presencial, con firma del cliente». | «Alternativa confirmada y solicitud aprobada automáticamente. La aprobación vence el …»; el vencimiento salta feriados y fines de semana (RN-49); Fondos muestra la reserva. | |
| R-074 | Confirmación simultánea | En otra solicitud, abrir la misma alternativa en dos pestañas y confirmar en ambas. | La segunda informa que la solicitud cambió; hay una sola reserva. | |
| R-075 | Bandeja del Gerente | Como Gerente, abrir Resolver solicitudes. | Las de Federico, Inés y Lucía, con evaluación, capacidad de pago y fondos disponibles. | |
| R-076 | Validaciones de la resolución | Enviar sin decisión; «Devolver» sin etapa; «Aprobar» sin plan. | Cada error junto a su campo. | |
| R-077 | Aprobar con plan del Gerente | Aprobar a Federico con el plan de A.9 y fundamento. | «Solicitud aprobada: …», con la reserva del neto. | |
| R-078 | Tope global | Fijar el tope global de A.6, aprobar a Inés con un plan de $ 600.000; luego quitar el tope y aprobarla con $ 400.000. | «El importe supera el tope global por cliente…»; sin tope se aprueba. | |
| R-079 | Devolver y rechazar | Devolver la de Lucía a «Verificación» con el fundamento de A.9; como Analista, corregirla con una aclaración y verificarla; cuando vuelva a la bandeja, rechazarla con fundamento. | «Solicitud devuelta para revisión…»; con la corrección vuelve a Pendiente de verificación; al final, «Solicitud rechazada…». | |

### 13. Contrato y liquidación (CU-103, CU-27 y CU-28)

En esta sección se liquidan los cinco créditos de la iteración 3. Para cada cliente: simular, registrar, verificar y confirmar la alternativa. Los importes están en A.10.

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-080 | Contrato generado | Como Analista, abrir Crédito → Contratos. | Las solicitudes aprobadas con su contrato: condiciones, datos de la entidad, impresión en dos ejemplares. | |
| R-081 | Liquidar sin constancia | Como Paula, en Liquidar créditos, intentar liquidar la de Ana. | «Falta la constancia firmada del contrato de préstamo en el legajo…» | |
| R-082 | Incorporar la constancia | Como Analista, adjuntar `contrato_firmado_prueba.pdf` con la fecha de recepción de hoy. | «Constancia firmada incorporada al legajo: la solicitud ya puede liquidarse (CU-27)…» | |
| R-083 | Validaciones del desembolso | Liquidar la de Ana con el DNI de Bruno, el CBU inválido, el CVU, sin evidencia, sin número de operación, con fecha futura y con otro importe. | Cada error junto a su campo («El documento no coincide…», «Ingrese un CBU válido de 22 dígitos.», «Es un CVU de una billetera virtual…», «El importe transferido debe ser el neto a desembolsar.»). | |
| R-084 | Liquidar | Liquidar con los datos de A.10 (CBU de Ana). | «Crédito liquidado: se desembolsaron …»; constancia imprimible; la reserva se consume. Repetir la liquidación: «La solicitud ya fue liquidada el …» | |
| R-085 | Plan del Gerente | Liquidar la de Federico sin marcar la conformidad y luego marcándola. | «El plan lo definió el Gerente: registre la conformidad del cliente antes de liquidar (RN-22).»; luego liquida. | |
| R-086 | Créditos de la iteración 3 | Liquidar los de Bruno, Carla, Diego y Julián con A.10 (cada uno con su contrato). | Quedan los créditos A (Ana), B (Bruno), C (Carla), D (Diego) y E (Julián). Anotar sus números en A.10. | |
| R-087 | Cancelar una solicitud | Confirmar la alternativa de Elena (queda aprobada) y cancelarla con el motivo de A.9 (pide confirmación adicional); intentar cancelar la de Ana. | Se cancela y libera la reserva; la de Ana: «La solicitud ya finalizó: no se puede cancelar.» | |

### 14. Consultas y confirmaciones (CU-29, CU-30, D-24)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-088 | Buscar solicitudes | Buscar por número, DNI, estado, línea y fechas; probar un rango invertido. | Resultados filtrados; el rango invertido se rechaza (A2). | |
| R-089 | Ficha completa | Abrir la ficha de la solicitud de Ana. | Datos, historial de estados, evaluación, alternativas, afectaciones de fondos, contrato y liquidación. | |
| R-090 | Búsqueda del Cajero | Como Paula, buscar solicitudes y hacer clic en una aprobada y en otra. | La aprobada abre la liquidación; la otra no ofrece un enlace sin permiso. | |
| R-091 | Confirmación de cambios | Simular y registrar una solicitud de Marcos en «Motos» ($ 500.000, 12 cuotas) y dejarla en trámite; modificar la TNA de «Motos» a 62 % sin y con la confirmación. | «Hay N solicitud(es) en trámite… confirme el cambio…»; confirmado se registra y la solicitud conserva su versión. | |

---

## Parte 3. Iteración 3

Antes de empezar: Paula con `CAJA-01` abierta (R-051). Los créditos A a E son los de R-084 y R-086.

### 15. Cobranza en ventanilla (CU-33, CU-35, CU-32 y CU-37)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-092 | Deuda del cliente | Como Paula, en Cobro en ventanilla, buscar a Ana. | El crédito A con sus cuotas pendientes en orden y sus conceptos. | |
| R-093 | Sin caja abierta | Como Tomás (sin abrir `CAJA-02`), intentar cobrar. | «No tiene una caja abierta hoy: ábrala antes de registrar cobros (CU-31, RN-28).» | |
| R-094 | Selección y medios inválidos | Elegir la cuota 2 sin la 1; con la 1, probar importe mayor que el total, importe 0, sin número de operación, dos medios con el mismo número y medios que no suman el importe (A.11). | «…la selección debe comenzar por la cuota 1…», «El importe a cobrar no puede superar…», «Ingrese un importe a cobrar mayor que cero.», «…informe el número de operación…», «…está repetido.», «Los medios suman $ …: faltan $ …». | |
| R-095 | Cobro total | Cobrar la cuota 1 de Ana por transferencia con la operación de A.11. | «Cobro N.º … registrado por $ …: 1 cuota(s) pagada(s).»; comprobante con medio, cuota y conceptos. | |
| R-096 | Cobro parcial con dos medios | Cobrar $ 20.000 de la cuota 2 de Ana: $ 12.000 con tarjeta de débito y $ 8.000 por transferencia (A.11). | «…la cuota 2 con saldo pendiente (D-12).»; el comprobante muestra los dos medios. | |
| R-097 | Comprobantes y movimientos | Buscar el comprobante de R-095 por número, cliente y fecha, y reemitirlo; abrir la jornada en Movimientos de caja. | La reemisión incrementa el contador (RN-31); la jornada muestra un movimiento por medio y los totales por medio. | |
| R-098 | Anular con dependientes | Como Gerente, en Anular cobros, anular el de R-095 con el motivo de A.11, primero sin confirmar los dependientes. | «Hay 1 cobro(s) posteriores sobre las mismas cuotas…»; al confirmar: «Cobro anulado: 1 cuota(s) recuperaron su saldo y el importe de $ … quedó como saldo a favor…» | |
| R-099 | Efectos y repetición | Revisar el crédito A y la caja; volver a anular el mismo cobro. | La cuota 1 vuelve a pendiente; el cobro y sus movimientos se conservan como anulados; «…ya fue anulado: no se generan nuevos ajustes (A1).» | |
| R-100 | Volver a cobrar | Como Paula, cobrar la cuota 1 de Ana por tarjeta de crédito (A.11). | Cobro registrado; el saldo a favor de R-098 sigue disponible para la sección 18. | |

### 16. Morosidad y punitorios (CU-60 y CU-88)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-101 | Dentro de la tolerancia | Con la consulta C.1 (−32 días) sobre el crédito B de Bruno, ejecutar `cartera:punitorios`. | La cuota 1 Vencida y el crédito En mora; tramos condicionales y sin punitorios exigibles. | |
| R-102 | Superada la tolerancia | Con la consulta C.1 (−8 días más), ejecutar el proceso. | Tramos exigibles: capital pendiente × tasa diaria × días de atraso, con la tasa limitada a TNA × 50 % / 365. En el cobro se ven con su IVA. | |
| R-103 | Proceso repetido | Ejecutar `cartera:punitorios` otra vez. | No agrega tramos ni cambia importes (A2). | |
| R-104 | Morosidad avanzada | Abrir el cobro de Bruno. | Días de atraso de la cuota 1 y la indicación de mora; sin morosidad avanzada (el umbral es 60 días). | |

### 17. Pagos electrónicos (CU-34, CU-38, CU-90 y CU-91)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-105 | Generar la orden | Como Paula, en Pagos electrónicos → Nueva orden, elegir la cuota 1 del crédito C de Carla. | «Orden N.º … generada por $ …»; enlace de pago y vencimiento a las 00:00. | |
| R-106 | Cuota en otra orden y aviso en ventanilla | Intentar otra orden con la misma cuota; abrir el cobro en ventanilla de Carla. | La cuota no se puede elegir (A7); en ventanilla, «Pago electrónico pendiente…» y la insignia «Orden N.º …». | |
| R-107 | Pago aprobado | Abrir el enlace y simular el pago aprobado con notificación; ejecutar `pagos:notificaciones`. | La orden pasa a Confirmada con el cobro electrónico y su comprobante; la cuota queda Pagada. Repetir la notificación no crea otro cobro (RN-30). | |
| R-108 | Rechazado y en proceso | Orden de la cuota 2 de Carla: simular rechazado. Nueva orden de la cuota 2: simular «En proceso» y luego actualizar ese pago a aprobado. | Primero Rechazada; después En conciliación con la incidencia de estado no definitivo, y por último Confirmada con la incidencia Resuelta. | |
| R-109 | Importe mayor | Orden de la cuota 3 de Carla pagada con $ 5.000 más (A.12). | Se cobra la cuota; los $ 5.000 quedan como saldo a favor con la incidencia «Importe distinto» y la alerta al Gerente. | |
| R-110 | Firma no válida y sin notificación | Orden de la cuota 4: simular con firma no válida; luego aprobado sin notificación y, 10 minutos después, `pagos:conciliar`. | La notificación queda Descartada; la conciliación detecta el pago y lo confirma. | |
| R-111 | Cuotas ya pagadas | Orden de la cuota 5; cobrarla en ventanilla y después pagar la orden. | Todo el importe queda como saldo a favor con la incidencia de cuotas ya canceladas (A6). | |
| R-112 | Orden vencida | Orden de la cuota 6; vencerla con la consulta C.2 y ejecutar `vencimientos:controlar`. | Queda Vencida (CU-89); la cuota se puede incluir en una orden nueva. | |
| R-113 | Consultar | Buscar órdenes por número, cliente y estado; abrir la de R-109 y usar «Consultar a la pasarela». | Cuotas, transacciones, incidencias, cobro y saldo a favor. | |

### 18. Saldos a favor y devoluciones (CU-76 a CU-78)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-114 | Aplicar un saldo | Como Paula, en Aplicar saldo a favor, buscar a Ana; probar sin medio y con fecha futura; aplicar el saldo de R-098 a la cuota 2 con la conformidad de A.13. | Los errores junto a su campo (A3); luego «Se aplicaron $ … del saldo a favor…». | |
| R-115 | Destinos no admitidos | Como Gerente, en Autorizar devoluciones, sobre el saldo de Carla de R-109, probar los datos inválidos de A.13. | «No se admiten CVU…», «El CBU debe tener 22 dígitos…», «…no se devuelve a cuentas de terceros (A3, RN-73).», «El importe supera el saldo disponible…». | |
| R-116 | Autorizar por el medio original | Autorizar la devolución de los $ 5.000 por el medio de pago original. | «Devolución N.º … autorizada…»; el importe pasa a reservado y los Cajeros reciben la alerta. | |
| R-117 | Ejecutar por la pasarela | Con `/simulador/devoluciones` en «Aprobar», como Paula, en Devoluciones, ejecutarla. | «Devolución completada…»; constancia imprimible. | |
| R-118 | Rechazo e incierta | Saldo de R-111: autorizar $ 1.000 por el medio original con el simulador en «Rechazar» y ejecutar; autorizar otros $ 1.000 con «No responder», ejecutar, aprobarla en el simulador y ejecutar `pagos:conciliar`. | La primera: Fallida, libera el importe y alerta al Gerente. La segunda: En conciliación con el importe reservado y después Completada. | |
| R-119 | Transferencia | Del saldo de R-111, autorizar $ 2.000 por transferencia al CBU de Carla (A.13); ejecutar con otro importe y luego con los datos correctos. | El importe distinto se rechaza y alerta al Gerente (A6); el correcto deja la devolución Completada. | |

### 19. Refinanciación y condonación (CU-39 a CU-44, CU-74 y CU-96)

Con el crédito B de Bruno en mora (sección 16). Analista Martín, Gerente Laura.

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-120 | Créditos admitidos y límites | En Cartera → Simular refinanciación, buscar a Bruno; simular con TNA 20 %, 90 % y 48 cuotas. | El crédito B admitido con su deuda; «La TNA debe estar entre 30 % y 80 %…» y «…no puede superar 36…». | |
| R-121 | Simular | TNA 60 % y 12 cuotas. | Capital y no capitalizable separados, plan preliminar y vigencia de 7 días; no modifica la deuda (RN-36). | |
| R-122 | Registrar la propuesta y duplicado | Registrarla; simular de nuevo y registrar otra. | «Propuesta N.º … registrada…» con alerta al Gerente; la segunda: «…ya tiene la propuesta N.º … vigente…» (A3). | |
| R-123 | Observar y nueva versión | Como Gerente, observarla con el fundamento de A.14; como Analista, simular y registrar otra vez. | Observada con el motivo; luego «Propuesta N.º … (versión 2) registrada…» con el mismo número. | |
| R-124 | Aprobar y plan definitivo | Como Gerente, aprobar la versión 2; al día siguiente (o después de `cartera:punitorios`), abrir «Registrar aceptación y aplicar». | «Propuesta aprobada…»; el plan definitivo suma los punitorios posteriores al corte (RN-70). | |
| R-125 | Aplicar | Registrar la aceptación con la fecha de hoy y «Firma presencial». | «Refinanciación aplicada: el crédito N.º … reemplaza a 1 crédito(s)…»; el crédito B queda Refinanciado y sus cuotas Sustituidas; Fondos no cambia. Anotar el crédito nuevo (F) en A.10. | |
| R-126 | Observación por un pago | Llevar a mora el crédito A de Ana con la consulta C.1 (−40 días) y `cartera:punitorios`; simular y registrar una propuesta; como Paula, cobrar en ventanilla la cuota vencida más antigua. | La propuesta pasa a Observada en el acto, con el motivo del cobro (RN-36). | |
| R-127 | Rechazar, cancelar y vencer | Con nuevas propuestas sobre el crédito A: rechazar una; cancelar otra como Analista por desistimiento (sin y con fecha y medio) y otra como Gerente; vencer otra con la consulta C.3. | «Propuesta rechazada (A3).»; «…cancelada por desistimiento del cliente…»; «…por causa administrativa…»; la vencida no puede resolverse (A1). | |
| R-128 | Condonación | Como Gerente, en Cartera → Condonar punitorios, con Ana: fijar el límite en 50 % (parámetros de morosidad), intentar más del máximo, sin fundamento y luego válido (A.14). | «El importe supera el máximo condonable…»; sin fundamento se rechaza; válida: «Condonación registrada por $ …, con su IVA. El capital adeudado no se redujo.». Volver el límite al 100 %. | |

### 20. Revocación (CU-102)

Con el crédito D de Diego, liquidado en R-086.

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-129 | Importe a restituir | Como Paula, en Revocación del préstamo, abrir el crédito D. | Plazo de 10 días hábiles; importe = neto desembolsado + interés simple por los días transcurridos + IVA, sin gastos. | |
| R-130 | Registrar y confirmar | Registrar la comunicación de hoy (A.15); confirmar con un importe menor y luego con el exacto. | «Revocación registrada. El cliente debe transferir $ …»; «…faltan $ … (A3).»; «Restitución confirmada: el crédito quedó revocado…». | |
| R-131 | Fuera de plazo | Con el crédito de Federico, llevar la recepción del contrato y la liquidación 20 días atrás con la consulta C.4 e intentar revocar. | «El plazo de revocación venció el …: el cliente puede cancelar el crédito anticipadamente (CU-97) (A1).» | |

### 21. Cancelación anticipada (CU-97)

Con el crédito E de Julián, sin modificar sus vencimientos.

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-132 | Cálculo | Como Paula, en Cancelación anticipada, buscar a Julián y calcular. | Cuotas vencidas, capital de las no vencidas, interés de la cuota en curso por los días transcurridos (de 30) y su IVA, total y la reducción de lo no devengado; sin comisión. | |
| R-133 | Parcial y orden pendiente | Registrar con $ 1.000 menos; generar una orden electrónica de la cuota 1 y volver a calcular. | «El importe debe ser igual al total de la cancelación…»; con la orden, aviso y registro deshabilitado (A4). | |
| R-134 | Registrar | Vencer la orden (C.2 y `vencimientos:controlar`) y registrar por el total por transferencia (A.16). | «Crédito cancelado anticipadamente con el cobro N.º …»; el comprobante incluye la reducción; cuotas Pagadas y crédito Cancelado. | |
| R-135 | No se anula y crédito refinanciado | Como Gerente, intentar anular ese cobro; como Paula, calcular la cancelación del crédito F (refinanciado). | «El cobro de una cancelación anticipada no se anula…»; el cálculo del F incluye el no capitalizable de las cuotas no vencidas. | |

### 22. Cierre, proyección y vencimientos (CU-36, CU-92 y CU-89)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-136 | Conciliación con diferencia | Como Paula, en Mi caja → Cerrar, informar en «Transferencia» $ 100 menos que lo registrado, sin explicación y luego con la de A.17. | «Hay una diferencia de …: explique su causa…»; luego «Caja cerrada. La conciliación registró diferencias en 1 medio(s)…». | |
| R-137 | Conciliación sin diferencias | Como Tomás, abrir `CAJA-02`, cobrar la cuota 1 del crédito de Federico por transferencia (`TRF-9001`) y cerrar informando exactamente ese total. | «Caja cerrada. La conciliación no tiene diferencias…» | |
| R-138 | Proyección | Ejecutar `fondos:proyectar` y abrir Fondos; registrar un egreso programado de $ 30.000.000 para dentro de 10 días y volver a proyectar. | La última proyección con sus componentes; con el egreso, disponible proyectado negativo y alerta al Gerente (A2). | |
| R-139 | Vencimientos completos | Autorizar otra devolución de $ 1.000 del saldo de R-111. Con la consulta C.5, vencer una simulación de Marcos, las alternativas de su solicitud (R-091), la aprobación de Inés (R-078, sin liquidar) y esa autorización; ejecutar `vencimientos:controlar` dos veces. | Cada objeto queda Vencido y se liberan sus reservas; la segunda ejecución no cambia nada ni repite alertas. | |
| R-140 | Evaluación repetida | Ejecutar `evaluacion:ejecutar` dos veces seguidas sin solicitudes nuevas. | No vuelve a evaluar solicitudes ya evaluadas ni genera alertas repetidas. | |

### 23. Generales

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| R-141 | Permisos por rol | Abrir las rutas de A.18 con cada rol. | Acceso denegado (403) donde corresponde; el menú de cada rol muestra solo sus opciones. | |
| R-142 | Operaciones simultáneas | Aplicar la misma propuesta aprobada en dos pestañas; ejecutar la misma devolución en dos pestañas. | La segunda informa que cambió; no hay doble aplicación ni doble devolución. | |
| R-143 | Inexistentes | Abrir `/admin/clientes/999999`, `/admin/solicitudes/999999`, `/admin/refinanciaciones/999999` y `/admin/devoluciones/999999`. | Mensaje de inexistente, sin detalles técnicos. | |
| R-144 | Inactividad | Dejar el panel 30 minutos y el portal 15 sin uso, y operar. | «La sesión finalizó por inactividad.» | |
| R-145 | Pantalla angosta | Con 360 px de ancho: clientes, simulación, liquidación, cobro, propuesta, aplicación de saldo, cancelación anticipada y portal. | Formularios en una columna y tablas con desplazamiento propio, sin desplazamiento horizontal. | |
| R-146 | Auditoría | En Gestión → Auditoría (o `registros_auditoria`), revisar las operaciones de esta prueba. | Cada operación con su registro, usuario, fecha y detalle, sin contraseñas ni códigos de enlace (RN-45). | |
| R-147 | Fondos coherentes | Comparar el disponible de Fondos con lo que surge de la prueba: + 20.000.000 de ingreso, − desembolsos, + cobros imputados (incluidos los saldos aplicados), sin restar devoluciones de saldos. | Coincide con la explicación de la pantalla (D-37). | |

---

## Anexo A. Datos de prueba

Todos los CUIL, CUIT y CBU tienen dígitos verificadores válidos; se comprobaron con las validaciones del sistema. Los correos usan el dominio ficticio `prueba.local`.

### A.1 Usuarios internos

| Rol | Nombre | Apellido | DNI | Correo | Contraseña |
|---|---|---|---|---|---|
| Administrador inicial | Iván Ezequiel | Thoux | el suyo | el suyo | la que elija (15 caracteres o más) |
| Gerente | Laura | Pérez | 26111222 | `gerente@prueba.local` | `Gerencia-Prueba-2026` |
| Analista de Crédito | Martín | López | 28333444 | `analista@prueba.local` | `Analisis-Prueba-2026` |
| Cajero | Paula | Romero | 30555666 | `cajero@prueba.local` | `Caja-Uno-Prueba-2026` |
| Cajero | Tomás | Silva | 32777888 | `cajero2@prueba.local` | `Caja-Dos-Prueba-2026` |
| Cajero (para desactivar) | Rocío | Báez | 34999000 | `cajero3@prueba.local` | `Caja-Tres-Prueba-2026` |

Contraseñas inválidas para R-006: `Corta12345` (menos de 15), `password1234567` (comprometida) y una confirmación distinta.

Datos inválidos para R-009: nombre vacío; correo `laura@`; DNI `26A11222`; DNI `26111222.`; correo `gerente@prueba.local` y DNI 26111222 repetidos.

### A.2 Clientes persona humana

Domicilio común: provincia **Misiones**, localidad **Apóstoles**, código postal **3350**, calle «Av. San Martín» con el número de la tabla. Nacionalidad «Argentina» salvo que se indique otra. Todos aceptan la política de datos; aceptan comunicaciones comerciales salvo Lucía.

| Código | Nombre | Apellido | DNI | CUIL | Nacimiento | Estado civil | Correo | Teléfono | N.º | Categoría de ingresos | Ingresos ($) | Actividad principal | Empleador | PEP | Uso |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| C-01 | Ana | Gómez | 30111233 | 27301112330 | 14/03/1985 | Casada | `ana.gomez@prueba.local` | 3758 41-1001 | 101 | Relación de dependencia | 1.500.000 | Docente | Escuela Normal N.º 3 | No | Crédito A: cobros, anulación, saldos |
| C-02 | Bruno | Fernández | 31222335 | 20312223350 | 02/07/1986 | Soltero | `bruno.fernandez@prueba.local` | 3758 41-1002 | 202 | Relación de dependencia | 1.500.000 | Empleado administrativo | Cooperativa Agrícola Apóstoles | No | Crédito B: mora y refinanciación |
| C-03 | Carla | Sosa | 32333445 | 27323334450 | 21/11/1987 | Soltera | `carla.sosa@prueba.local` | 3758 41-1003 | 303 | Relación de dependencia | 1.500.000 | Enfermera | Hospital de Área Apóstoles | No | Crédito C: pagos electrónicos |
| C-04 | Diego | Ramírez | 33444550 | 20334445500 | 09/05/1988 | Casado | `diego.ramirez@prueba.local` | 3758 41-1004 | 404 | Relación de dependencia | 1.500.000 | Técnico electricista | Energía de Misiones S.A. | No | Crédito D: revocación |
| C-05 | Elena | Benítez | 34555665 | 27345556651 | 30/01/1989 | Divorciada | `elena.benitez@prueba.local` | 3758 41-1005 | 505 | Jubilado/pensionado | 1.500.000 | Jubilada | — | No | Situación 1 |
| C-06 | Federico | Acosta | 35666775 | 20356667752 | 17/08/1990 | Soltero | `federico.acosta@prueba.local` | 3758 41-1006 | 606 | Relación de dependencia | 1.500.000 | Vendedor | Comercial del Sur S.R.L. | No | Análisis gerencial |
| C-07 | Gabriela | Duarte | 36777893 | 27367778933 | 05/12/1991 | Soltera | `falla.definitiva@prueba.local` | 3758 41-1007 | 707 | Relación de dependencia | 1.500.000 | Cajera | Supermercado Norte | No | Rechazo documental y correo fallido |
| C-08 | Hernán | Ojeda | 37888994 | 20378889945 | 23/04/1992 | Casado | `falla.transitoria@prueba.local` | 3758 41-1008 | 808 | Relación de dependencia | 1.500.000 | Chofer | Transporte Apóstoles S.R.L. | No | Situación 5 excluyente y reintentos |
| C-09 | Inés | Villalba | 38999102 | 27389991029 | 11/09/1993 | Soltera | `incierto@prueba.local` | 3758 41-1009 | 909 | Relación de dependencia | 1.500.000 | Administrativa | Municipalidad de Apóstoles | No | Contingencia y resultado incierto |
| C-10 | Julián | Ríos | 29111203 | 20291112030 | 28/02/1982 | Casado | `julian.rios@prueba.local` | 3758 41-1010 | 1010 | Relación de dependencia | 1.500.000 | Contador | Estudio Ríos y Asociados | No | Crédito E: cancelación anticipada |
| C-11 | Lucía | Martínez | 40111304 | 27401113040 | 19/06/1996 | Soltera | `lucia.martinez@prueba.local` | 3758 41-1011 | 1111 | Relación de dependencia | 100.000 | Ayudante de cocina | Restaurante El Paso | No | Sin capacidad de pago |
| C-12 | Marcos | Paredes | 27222403 | 20272224030 | 03/10/1979 | Casado | `marcos.paredes@prueba.local` | 3758 41-1012 | 1212 | Monotributista (categoría B) | 1.200.000 | Comerciante | Almacén propio | No | Monotributo |
| C-13 | Nora | Kowalski | 25333502 | 27253335020 | 12/01/1976 | Viuda | `nora.kowalski@prueba.local` | 3758 41-1013 | 1313 | Relación de dependencia | 1.500.000 | Concejal | Concejo Deliberante de Apóstoles | **Sí** | Persona expuesta políticamente; nacionalidad «Paraguaya» |
| C-14 | Sofía | Menor | 48000000 | 27480000000 | 15/05/2010 | Soltera | `sofia.menor@prueba.local` | 3758 41-1014 | 1414 | Relación de dependencia | 300.000 | Estudiante | — | No | Solo validaciones (menor de edad) |

Modificación de Ana (R-024): «Av. San Martín 150», teléfono 3758 41-2001. Correo nuevo: `ana.gomez2@prueba.local`. Evidencia de identidad: «DNI exhibido en ventanilla el día de hoy».

Contraseñas del portal: Ana `Portal-Ana-2026-OK`; Bruno `Portal-Bruno-2026-OK`; la pyme `Portal-Yerba-2026-OK`.

### A.3 Clientes persona jurídica

| Código | Razón social | CUIT | Constitución | Correo | Teléfono | Domicilio | Categoría | Ingresos netos mensuales ($) | Representante | DNI | Cargo |
|---|---|---|---|---|---|---|---|---|---|---|---|
| P-01 | Yerbatera del Sur S.A. | 30701112330 | 10/04/2015 | `pyme@prueba.local` | 3758 42-5000 | Ruta 14 km 820, Apóstoles (3350) | Empresa | 8.000.000 | Ricardo Benítez | 22111333 | Presidente |
| P-02 | Transporte Apóstoles S.R.L. | 30703334470 | 01/09/2025 | `transporte@prueba.local` | 3758 42-6000 | Belgrano 455, Apóstoles (3350) | Empresa | 3.000.000 | Mónica Ayala | 24555777 | Socia gerente |

P-02 tiene menos de 24 meses de antigüedad: «Capital de trabajo Pyme» no la admite (sirve para comparar líneas).

### A.4 Líneas de crédito

| Campo | L-01 Motos | L-02 Personal | L-03 Capital de trabajo Pyme |
|---|---|---|---|
| Identificación | `MOTOS` | `PERSONAL` | `PYME-CT` |
| Descripción | Financiación de motos nuevas y usadas | Préstamo personal de libre destino | Capital de trabajo para empresas |
| Admite | Persona humana | Persona humana | Persona jurídica |
| Categorías | Relación de dependencia, Jubilado/pensionado, Monotributista | Todas las de persona humana | Empresa |
| Importe mínimo y máximo | 300.000 y 1.500.000 | 100.000 y 2.000.000 | 500.000 y 5.000.000 |
| Cuotas mínimas y máximas | 6 y 24 | 3 y 36 | 6 y 24 |
| TNA | 60 % | 70 % (en R-034, 72 %) | 55 % |
| Gasto de otorgamiento | 3 % | 2 % | 2,5 % |
| Gasto administrativo | Importe fijo: 500 | Porcentaje: 0,5 % del capital | Importe fijo: 1.000 |
| Edad mínima y máxima | 18 y 75 | 21 y 70 | — |
| Antigüedad mínima de la empresa | — | — | 24 meses |
| Rige desde | hoy | hoy | hoy |
| Documentación | la sugerida por categoría | la sugerida | la sugerida |

Motivos: «Alta de la línea para la prueba integral», «Actualización de la tasa de mercado», «Suspensión temporal por revisión de la cartera».

### A.5 Catálogos

- Tipo de documento nuevo: «Certificado de servicios del empleador», código `CERT_SERVICIOS`, para persona humana.
- Escala del monotributo: rige desde el 01/02/2027. Topes anuales: A 13.000.000, B 19.000.000, C 26.500.000, D 33.000.000, E 39.000.000, F 48.500.000, G 58.000.000, H 88.000.000, I 98.500.000, J 113.000.000 y K 136.000.000. Para el caso inválido, el tope de B en 12.000.000.

### A.6 Parámetros

| Parámetros | Inválidos (uno por vez) | Válidos |
|---|---|---|
| Evaluación | peso de un indicador 1,5 | los sugeridos |
| Estructuración | afectación 0 % y 120 %; IVA 150 %; vigencia 0 y 45 días; escalones `75 50`, `100 50 75` y `100 90 80 70 60 50 40` | afectación 30 %, IVA 21 %, vigencia de alternativas 7 días, redondeo 1.000, escalones `100 75 50`, sin tope global. Para R-078, tope global 500.000 |
| Morosidad | tasa diaria 2 %; tope 60 %; tolerancia 45; umbral 2 (menor que la tolerancia); condonación 150 %; TNA de refinanciación máxima 20 % con mínima 30 %; 200 cuotas | tasa diaria 0,12 %, tope 50 %, tolerancia 3, umbral 60, condonación 100 %, refinanciación de 30 % a 80 % y hasta 36 cuotas |

Motivo de las versiones: «Configuración inicial para la prueba integral».

### A.7 Calendario operativo

Feriados de a uno: 08/12/2026 «Inmaculada Concepción de María» (Feriado); 25/12/2026 «Navidad» (Feriado); 24/12/2026 «Cierre anticipado de fin de año» (Cierre de la entidad). Fecha pasada: 01/11/2026.

Carga anual (pegar tal cual; las dos últimas líneas son erróneas):

```
01/01/2027; Año Nuevo; Feriado
15/02/2027; Carnaval; Feriado
16/02/2027; Carnaval; Feriado
24/03/2027; Día Nacional de la Memoria por la Verdad y la Justicia; Feriado
02/04/2027; Día del Veterano y de los Caídos en la Guerra de Malvinas; Feriado
01/05/2027; Día del Trabajador; Feriado
25/05/2027; Día de la Revolución de Mayo; Feriado
31/02/2027; Fecha inexistente; Feriado
10/06/2027; ; Feriado
```

### A.8 Fondos y cajas

- Ingreso realizado: $ 20.000.000, hoy, concepto «Aporte de capital para la prueba integral».
- Egreso programado: $ 50.000, dentro de 7 días, «Pago de servicios».
- Egreso realizado para corregir: $ 10.000, hoy, «Gastos bancarios»; corrección «Importe cargado por error».
- Cajas: `CAJA-01` «Caja principal», ubicación «Planta baja»; `CAJA-02` «Caja secundaria», ubicación «Primer piso». Motivo de la inhabilitación: «Mantenimiento del puesto».

### A.9 Solicitudes y resolución

- Observación corta: «Ilegible». Completa: «El recibo de sueldo de septiembre está incompleto: falta la segunda hoja».
- Motivo del rechazo documental: «La documentación presentada no corresponde al titular de la solicitud».
- Contingencia de Inés. Inválidos: período `13/2025`; período de hace más de 12 meses; situación 7; deuda sin monto; justificación «Ok». Válidos: antecedentes «Consulta telefónica a la Central de Deudores por falla del servicio»; deuda de «Banco Macro» del período del mes pasado, situación 1, monto 180.000, marcada como de la consulta manual; recomendación Favorable; justificación «Situación 1 en una sola entidad, sin atrasos, con ingresos estables».
- Plan del Gerente para Federico: $ 450.000 en 12 cuotas; fundamento «Se reduce el importe por el análisis de su endeudamiento».
- Fundamentos: devolución de Lucía «Revisar la verificación del legajo»; aclaración del Analista «Se verificó nuevamente el recibo de sueldo con el empleador»; rechazo de Lucía «Ingresos insuficientes para la cuota mínima»; cancelación de Elena «El cliente desiste del crédito».

### A.10 Créditos de la iteración 3

Cada uno en «Motos», 12 cuotas, confirmado y liquidado por transferencia con la fecha de hoy.

| Crédito | Cliente | Importe | CBU de desembolso | Evidencia de titularidad | N.º de operación | N.º de crédito (anotar) |
|---|---|---|---|---|---|---|
| A | Ana Gómez | 500.000 | `0170099240000000123457` | «Constancia de CBU del Banco Francés presentada por la titular» | `DES-0001` | |
| B | Bruno Fernández | 600.000 | `0110599520000000678908` | «Constancia de CBU del Banco Nación» | `DES-0002` | |
| C | Carla Sosa | 400.000 | `0720123988000000456784` | «Constancia de CBU del Banco Santander» | `DES-0003` | |
| D | Diego Ramírez | 300.000 | `1500001531000000987653` | «Constancia de CBU del HSBC» | `DES-0004` | |
| E | Julián Ríos | 450.000 | `0140321155000000112233` | «Constancia de CBU del Banco Provincia» | `DES-0005` | |
| (Federico) | Federico Acosta | plan del Gerente | `0340007466000000334455` | «Constancia de CBU del Banco Patagonia» | `DES-0006` | |
| F | Bruno (refinanciado) | — | — | — | — | |

CBU inválido: `2850590909400904181357`. CVU: `0000003110000123456781`.

### A.11 Cobros en ventanilla

| Caso | Medio | N.º de operación | Importe |
|---|---|---|---|
| R-094 (repetido) | Transferencia y tarjeta | `TRF-1001` en los dos | — |
| R-095 | Transferencia | `TRF-1001` | total de la cuota 1 |
| R-096 | Tarjeta de débito + transferencia | `DEB-2001` y `TRF-1002` | 12.000 + 8.000 |
| R-100 | Tarjeta de crédito | `CRE-3001` | total de la cuota 1 |

Motivo de la anulación (R-098): «El cliente informó que la transferencia fue rechazada por su banco».

### A.12 Pagos electrónicos

Importe mayor de R-109: el de la orden más 5.000. Las fechas efectivas, vacías (ahora), salvo que el caso diga otra cosa.

### A.13 Saldos y devoluciones

- Conformidad de R-114: fecha de hoy, medio «Presencial, con firma del cliente», observación «Aplica el saldo a la cuota 2».
- Inválidos de R-115: CBU `0000003110000123456781` (CVU); CBU `2850590909400904181357`; CUIT del titular `20312223350` (Bruno); importe 999.999.
- Autorización válida: motivo «El cliente solicita el reintegro del excedente del pago electrónico»; solicitud de hoy por «Nota firmada en ventanilla».
- Transferencia de R-119: CBU de Carla `0150500977000000223346`, titular 27323334450, entidad «Banco Credicoop», evidencia «Constancia de CBU emitida por el banco»; comprobante `DEV-TRF-01`, con fecha de hoy.

### A.14 Refinanciación y condonación

- Fundamento de la observación: «Revisar la cantidad de cuotas con el cliente».
- Fundamento de la aprobación: «Plan acorde a la capacidad de pago actual».
- Desistimiento: motivo «El cliente decidió pagar la deuda con fondos propios», fecha de hoy, medio «Nota firmada».
- Cancelación administrativa: «Propuesta registrada por error».
- Condonación: el máximo que indique la pantalla más 1; fundamento «Bonificación por regularización voluntaria de la deuda».

### A.15 Revocación

Comunicación del cliente: hoy, medio «Nota presentada en ventanilla». Transferencia de restitución: `REV-0001`.

### A.16 Cancelación anticipada

Transferencia `CAN-0001` por el total que indique la pantalla.

### A.17 Cierre de caja

Explicación de la diferencia: «Comisión bancaria descontada de una transferencia».

### A.18 Rutas para los permisos

| Ruta | Gerente | Analista | Cajero |
|---|---|---|---|
| `/admin/usuarios` | 403 | 403 | 403 |
| `/admin/fondos` | permitido | 403 | 403 |
| `/admin/parametros/estructuracion` | permitido | 403 | 403 |
| `/admin/simulaciones` | 403 | permitido | 403 |
| `/admin/resoluciones` | permitido | 403 | 403 |
| `/admin/cobros` | 403 | 403 | permitido |
| `/admin/punitorios/condonar` | permitido | 403 | 403 |
| `/admin/refinanciaciones/simular` | 403 | permitido | 403 |
| `/admin/saldos-favor/devoluciones` | permitido | 403 | 403 |
| `/admin/devoluciones` | 403 | 403 | permitido |
| `/admin/creditos/cancelacion-anticipada` | 403 | 403 | permitido |

## Anexo B. Archivos para adjuntar

Carpeta `Datos de prueba` de la documentación. Son documentos ficticios, marcados «DOCUMENTO DE PRUEBA – SIN VALIDEZ».

| Archivo | Para |
|---|---|
| `clientes/DNI_<Apellido>.pdf` y `.jpg` | Documento de identidad de cada cliente (C-01 a C-14) |
| `clientes/recibos_sueldo_<Apellido>.pdf` | Recibos de sueldo (relación de dependencia) |
| `clientes/recibo_haberes_Benitez.pdf` | Elena (jubilada) |
| `clientes/constancia_monotributo_Paredes.pdf` y `pagos_monotributo_Paredes.pdf` | Marcos |
| `empresas/<CUIT>_estatuto.pdf`, `_constancia_cuit.pdf`, `_estados_contables.pdf`, `_poder.pdf` y `DNI_representante_<Apellido>.pdf` | P-01 y P-02 |
| `contrato_firmado_prueba.pdf` | Constancia firmada del contrato (R-082 y R-086) |
| `otro_documento_prueba.pdf` | Documento adicional del legajo (R-022) |
| `archivo_grande_mas_de_5MB.pdf` | Validación del tamaño máximo (R-016) |
| `archivo_no_admitido.txt` | Validación del tipo de archivo (R-016) |
| `consultas_de_apoyo.sql` | Anexo C |

## Anexo C. Consultas de apoyo

En `consultas_de_apoyo.sql`, reemplazar los números marcados con `…`.

- **C.1 Llevar un crédito a mora.** Adelanta todos sus vencimientos; después, ejecutar `cartera:punitorios`.
- **C.2 Vencer una orden de pago.**
- **C.3 Vencer una propuesta de refinanciación.**
- **C.4 Sacar un crédito del plazo de revocación.**
- **C.5 Vencer una simulación, una alternativa, una aprobación y una autorización de devolución.**
- **C.6 Ver los créditos, cuotas y saldos de un cliente**, para anotar los números de la prueba.

## Registro

| Fecha | Responsable | Casos OK | Fallas (issues) | Observaciones |
|---|---|---|---|---|
| | Iván Ezequiel Thoux | de 147 | | |
