# Prueba funcional de la iteración 1 (C11)

Recorrido manual de los casos de uso de la iteración 1 (CU-01 a CU-20, CU-53, CU-54, CU-73, CU-83 y CU-95) en el panel y en el portal. Complementa las pruebas automáticas (suites Finanzas, Unitarias y Rutas), que verifican las reglas y la autorización de cada ruta sin la base de datos; esta prueba verifica el sistema completo, con SQL Server, la cola de comunicaciones y las pantallas.

Cada caso se marca con **OK** o **Falla**. Una falla se registra como *issue* con la etiqueta `bug`, el identificador del caso y lo observado. La iteración se considera aprobada cuando todos los casos dan OK o sus fallas están corregidas (Control 1, 03/11/2026).

## Preparación

1. Actualizar y levantar el entorno:
   ```bash
   git checkout develop && git pull
   docker compose up -d --force-recreate web app scheduler
   docker compose exec app composer migrar   # migraciones 10 y 11, con el propietario del esquema
   ```
2. Ingresar a `https://localhost/admin` con el Administrador creado por `inicializar.sh`.
3. Los correos no se envían: el simulador los guarda y se consultan, con su enlace, con
   ```bash
   docker compose exec app php spark sigccr:correos
   ```
4. El contenedor `scheduler` ejecuta `comunicaciones:enviar` cada minuto (CU-95). Para no esperar, se puede ejecutar a mano: `docker compose exec app php spark comunicaciones:enviar`.
5. El simulador de correo falla a propósito según el destinatario: `falla.transitoria@…` (reintentos), `falla.definitiva@…` (fallida) e `incierto@…` (en revisión).

Datos de prueba sugeridos (correos ficticios):

| Uso | Correo | Rol o tipo |
|---|---|---|
| Gerente | `gerente@prueba.local` | Gerente |
| Analista | `analista@prueba.local` | Analista de Crédito |
| Cajero | `cajero@prueba.local` | Cajero |
| Cliente persona humana | `ana.gomez@prueba.local` | DNI de 8 dígitos y su CUIL correspondiente |
| Cliente persona jurídica | `pyme@prueba.local` | CUIT válido |
| Cliente para fallos | `falla.definitiva@prueba.local` | persona humana |

## 1. Acceso al panel (CU-01 a CU-03)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| F-01 | Inicio de sesión correcto | Ingresar con el Administrador. | Pantalla principal con el menú del rol; el pie muestra nombre y rol. | |
| F-02 | Contraseña incorrecta | Ingresar con una contraseña errónea. | «Usuario o contraseña incorrectos.» sin indicar qué dato falló; el correo queda cargado. | |
| F-03 | Advertencia y bloqueo | Fallar 3, 4 y 5 veces seguidas con el mismo correo. | Al tercer fallo: «Le quedan 2 intentos…»; al cuarto: «Le queda un intento…»; al quinto: acceso bloqueado 15 minutos, aun con la contraseña correcta. | |
| F-04 | Correo inexistente | Fallar 5 veces con un correo que no existe. | La misma secuencia de mensajes que F-03 (no revela que la cuenta no existe). | |
| F-05 | Campos vacíos | Enviar el formulario sin datos. | «Ingrese el correo electrónico y la contraseña.» | |
| F-06 | Sesión activa | Con la sesión iniciada, abrir `/admin/login`. | Redirige a la pantalla principal sin pedir credenciales. | |
| F-07 | Cierre de sesión | Menú → Cerrar sesión → confirmar. | Vuelve al inicio de sesión con «La sesión se cerró correctamente.»; `/admin` vuelve a pedir credenciales. | |
| F-08 | Recuperar acceso | «¿Olvidó su contraseña?» con un correo existente y luego con uno inexistente. | El mismo mensaje en los dos casos; solo el existente recibe el enlace (`sigccr:correos`), que vence en 15 minutos. | |
| F-09 | Definir la contraseña | Abrir el enlace de F-08 y probar: contraseña de 10 caracteres, `password1234567`, confirmación distinta y finalmente una válida. | Rechaza las tres primeras con su motivo (largo de 15 a 128, contraseña comprometida, confirmación); la válida permite ingresar. | |
| F-10 | Enlace usado o vencido | Volver a abrir el enlace de F-09. | «El enlace expiró o ya no es válido…» | |

## 2. Usuarios internos (CU-05 a CU-10 y CU-53)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| F-11 | Registrar usuarios | Como Administrador, registrar Gerente, Analista y Cajero con los datos sugeridos. | Cada uno queda Pendiente de activación y recibe el enlace de definición inicial (24 horas). | |
| F-12 | Validaciones del alta | Intentar un alta con: nombre vacío, correo inválido, DNI con letras, correo o documento ya registrados. | Cada error aparece junto a su campo y no se crea el usuario. | |
| F-12b | Documento con caracteres sueltos | Registrar un usuario con DNI `28999111.`, `.28999111` y `28-999-111`; luego con `28.999.111`. | Los tres primeros se rechazan («…sin puntos»); el último se acepta como 28999111. | |
| F-13 | Definición inicial | Abrir los enlaces de F-11, definir las contraseñas e ingresar con cada usuario. | Cada usuario ve solo las opciones de su rol (el Cajero no ve Clientes → Registrar ni Líneas). | |
| F-14 | Localizar | Buscar usuarios por nombre, documento, rol y estado. | Resultados que cumplen los filtros. | |
| F-15 | Modificar correo | Cambiar el correo del Cajero. | Sigue vigente el correo anterior hasta abrir el enlace enviado al nuevo (30 minutos); después ingresa con el nuevo. | |
| F-16 | Edición simultánea | Abrir la modificación de un usuario en dos pestañas, guardar en una y luego en la otra. | La segunda informa «El registro … fue modificado por otra operación. Actualice la pantalla y vuelva a intentarlo.» y no pisa los cambios (RN-24). | |
| F-17 | Restablecer credenciales | Restablecer las del Analista. | El Analista recibe el enlace; sus sesiones abiertas finalizan al definir la contraseña nueva. | |
| F-18 | Asignar rol | Con el Cajero con la sesión abierta en otro navegador, cambiarle el rol a Analista. | Su sesión finaliza en la siguiente acción; al ingresar ve el menú del rol nuevo. | |
| F-19 | Desactivar | Desactivar al Cajero con motivo; intentar desactivar al propio Administrador. | El Cajero no puede ingresar y sus sesiones se cierran; desactivarse a sí mismo o al último Administrador se rechaza (RN-05). | |
| F-20 | Reactivar | Reactivar al Cajero. | Vuelve a poder ingresar con su contraseña. | |
| F-21 | Sin permiso | Como Cajero, abrir `/admin/usuarios`. | Pantalla de acceso denegado (403), sin datos. | |

## 3. Clientes (CU-11 a CU-15 y CU-54)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| F-22 | Registrar persona humana | Como Analista, registrar a Ana Gómez con su DNI adjunto (PDF o JPG). | Perfil con el aviso de verificación del correo; cuenta del portal Pendiente de activación. | |
| F-23 | Validaciones del alta | Probar: menor de 18 años, CUIL que no corresponde al DNI, sin aceptar la política de datos, sin adjuntar el DNI, archivo de más de 5 MB o de otro tipo, documento o correo repetidos. | Cada error junto a su campo; no se registra el cliente ni queda ningún archivo guardado. | |
| F-23b | CUIL según el prefijo | Para el mismo DNI, cargar el CUIL con 20, 27 y 23 y su dígito; luego cambiar solo el prefijo sin cambiar el dígito, y probar un prefijo de empresa (30). | Los tres con su dígito correcto se aceptan; el de dígito sin actualizar indica cuál corresponde; 30 se rechaza para una persona humana. | |
| F-24 | Registrar persona jurídica | Registrar la pyme con CUIT, representante, constancia de CUIT, DNI del representante y poder. | Perfil de persona jurídica con los documentos en el legajo. | |
| F-25 | Verificar el correo y activar | Abrir el enlace de `sigccr:correos` (portal/verificar-correo) y confirmar. | Lleva directamente a definir la contraseña del portal (D-16); después, el perfil muestra el correo como verificado y la cuenta Habilitada. | |
| F-26 | Legajo | En el perfil, subir otro documento y descargarlo. | El documento aparece en el legajo y se descarga con su nombre original. | |
| F-27 | Localizar | Buscar por parte del nombre, DNI, CUIT, tipo y estado. | La lista aparece solo después de buscar y respeta los filtros. | |
| F-28 | Modificar datos | Cambiar el domicilio y los ingresos. | «Datos actualizados.»; la auditoría registra los valores anteriores y nuevos. | |
| F-29 | Cambiar el correo | Cambiar el correo sin y con la evidencia de identidad. | Sin evidencia se rechaza (RN-58); con evidencia sigue vigente el anterior hasta verificar el nuevo. | |
| F-30 | Revocar comunicaciones | Desmarcar «acepta comunicaciones comerciales». | El perfil muestra que no acepta, con la fecha y el medio. | |
| F-31 | Historial | Abrir el historial de un cliente nuevo. | Mensaje de que el cliente no tiene antecedentes crediticios en la entidad. | |
| F-32 | Desactivar y reactivar | Desactivar a Ana con motivo; luego reactivarla. | Desactivada no puede usar el portal; al reactivarla: «Cliente reactivado. Para volver a usar el portal debe activar o rehabilitar su acceso (CU-04).» | |

## 4. Acceso del cliente al portal (CU-01 a CU-04)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| F-33 | Activar el acceso sin el enlace de verificación | Con un cliente con el correo verificado y la cuenta Pendiente de activación, ir a `https://localhost/portal/activar`, ingresar su DNI y correo, abrir el enlace y definir la contraseña. | Lleva al inicio de sesión del portal con el correo cargado; ingresa a su pantalla principal. | |
| F-34 | Datos que no coinciden | Repetir F-33 con un DNI inexistente o un correo distinto. | «No pudimos verificar los datos ingresados…» | |
| F-35 | Cuenta ya activa | Repetir F-33 con la cuenta ya habilitada. | El mismo mensaje que un envío exitoso, sin enviar correo (no revela el estado). | |
| F-36 | Persona jurídica | Activar el acceso de la pyme con su CUIT. | Igual que F-33. | |
| F-37 | Inicio y cierre en el portal | Ingresar, cerrar sesión y probar una contraseña errónea. | Mismos mensajes y bloqueo que en el panel; la sesión del portal no abre el panel ni al revés. | |
| F-38 | Recuperar en el portal | «¿Olvidó su contraseña?» del portal. | Igual que F-08 y F-09, con enlaces del portal. | |
| F-39 | Rehabilitar | Con Ana desactivada y reactivada (F-32), activar de nuevo su acceso. | Se rehabilita la misma cuenta y vuelve a ingresar. | |
| F-40 | Cliente desactivado | Desactivar a Ana e intentar ingresar al portal con la contraseña correcta. | Rechazo genérico (RN-10). | |

## 5. Líneas de crédito (CU-16 a CU-20 y CU-73)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| F-41 | Registrar línea | Como Gerente, registrar «Motos» con mínimo $ 300.000, máximo $ 1.500.000, TNA, cuotas, categorías y documentación. | «Línea registrada con su versión 1 de condiciones…» | |
| F-42 | Validaciones | Probar mínimo mayor que el máximo, cuotas mínimas mayores que las máximas, vigencia inconsistente y una categoría sin el documento de identidad. | Cada error junto a su campo (RN-14, RN-76, D-14). | |
| F-43 | Modificar | Cambiar la TNA con vigencia desde hoy y luego con una fecha pasada. | La primera crea la versión 2; la fecha pasada se rechaza por no admitir retroactividad. | |
| F-44 | Consultar | Abrir la línea. | Versión vigente, historial de versiones, documentación por categoría y suspensiones o reactivaciones con su motivo. | |
| F-45 | Suspender y reactivar | Suspender con motivo y reactivar. | Cambia el estado con su mensaje; suspender dos veces se rechaza. | |
| F-46 | Localizar | Buscar por nombre y estado; como Analista, abrir una línea. | El Analista consulta pero no ve registrar, modificar ni suspender. | |
| F-47 | Parámetros de evaluación | Como Gerente, abrir los parámetros de evaluación, cargar los sugeridos y guardar un peso inválido. | Los sugeridos se cargan; el valor inválido se rechaza junto a su campo (CU-58, adelantado). | |

## 6. Alertas y comunicaciones (CU-83 y CU-95)

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| F-48 | Comunicación fallida | Registrar un cliente con `falla.definitiva@prueba.local` y esperar un minuto (o ejecutar `comunicaciones:enviar`). | La comunicación queda Fallida y el Administrador ve una alerta de Integración pendiente en el menú. | |
| F-49 | Reintentos | Repetir con `falla.transitoria@prueba.local` y ejecutar el comando varias veces durante seis minutos. | Reintenta al minuto, a los 3 y a los 5 minutos del primer intento y luego queda Fallida con su alerta. | |
| F-50 | Resultado incierto | Repetir con `incierto@prueba.local`. | Queda En revisión, no se reenvía y genera la alerta. | |
| F-51 | Consultar alertas | Como Administrador, filtrar por estado, tipo y fechas y abrir una alerta. | Detalle con causa y acción pendiente; queda marcada como vista. | |
| F-52 | Alertas de otro rol | Como Cajero, abrir la URL del detalle de F-51. | «La alerta no existe o no está dirigida a usted.» | |
| F-53 | Autorización vencida | Pedir dos recuperaciones seguidas del mismo correo y abrir el primer enlace. | El primero ya no es válido (lo sustituyó el segundo, RN-03). | |

## 7. Generales

| ID | Caso | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| F-53b | Fin del bloqueo temporal | Bloquear una cuenta (F-03), esperar 15 minutos y, sin iniciar sesión, consultar el usuario como Administrador y pedir la recuperación de acceso. | El usuario figura Habilitado y la recuperación envía el enlace; luego inicia sesión normalmente. | |
| F-54 | Inactividad | Dejar el panel 30 minutos sin uso (el portal, 15) y operar. | «La sesión finalizó por inactividad.» | |
| F-55 | Pantalla angosta | Abrir el panel y el portal con 360 px de ancho (herramientas del navegador). | Menú desplegable, formularios en una columna y tablas con desplazamiento propio, sin desplazamiento horizontal de la página. | |
| F-56 | Errores técnicos | Abrir `/admin/clientes/999999`. | Mensaje de cliente inexistente, sin detalles técnicos. | |
| F-57 | Auditoría | Consultar en la base `registros_auditoria` las operaciones de esta prueba. | Cada operación tiene su registro, sin contraseñas ni códigos de enlace (RN-45, RNF-14). | |

## Registro

| Fecha | Responsable | Casos OK | Fallas (issues) | Observaciones |
|---|---|---|---|---|
| 29/10/2026 | Iván Ezequiel Thoux | 55 de 60 | 5 fallas: documento con punto final, CUIL según el prefijo, activación tras verificar el correo, lista inicial de clientes y fin del bloqueo temporal | Corregidas en el PR #104 (D-16); se agregaron F-12b, F-23b y F-53b. |
| 29/10/2026 | Iván Ezequiel Thoux | 60 de 60 | Ninguna | Repetición completa después de las correcciones: validaciones, mensajes por campo y acceso por rol correctos. Iteración 1 aprobada. |
