# Lista de comprobación OWASP ASVS 5.0.0 — nivel 1 (RNF-12)

Referencia: *OWASP Application Security Verification Standard* 5.0.0. El nivel 1 reúne **70 requisitos** de los 345 del
estándar. Cada fila indica el estado, la evidencia (dónde está resuelto en el código o la configuración) y la prueba que
lo sostiene. Los identificadores `S-xx` son los casos del nivel de prueba «Seguridad» (Plan de pruebas v1.5); `IN-xx`,
las pruebas de integración; `R-xx`, las de punta a punta.

Estados: **Cumple** · **Cumple (corregido)** = se resolvió con esta lista de comprobación · **No aplica** (con motivo) ·
**Desvío documentado** = no se cumple y la decisión D-63 explica el motivo y el plan.

## Resumen

| Estado | Requisitos |
|---|---|
| Cumple | 46 |
| Cumple (corregido en esta revisión) | 9 |
| No aplica | 14 |
| Desvío documentado | 1 |
| **Total** | **70** |

Los nueve requisitos corregidos son V3.2.1, V3.3.1, V6.2.2, V6.2.3, V6.2.4, V11.3.2, V14.3.1, V15.1.1 y V15.2.1.
El desvío documentado es V14.2.1 (ver su fila).

## V1 · Codificación y saneamiento

| Req. | Estado | Evidencia | Prueba |
|---|---|---|---|
| V1.2.1 | Cumple | Todas las vistas escapan con `esc()` o con asistentes que ya devuelven HTML escapado; lo que se imprime sin escapar son identificadores enteros, constantes del código y HTML armado por los asistentes. Revisión de todas las vistas el 19/11/2026. | revisión de vistas (19/11/2026) |
| V1.2.2 | Cumple | Los enlaces se arman con `url_to()` y `rawurlencode`/`http_build_query` para los parámetros. | revisión de vistas (19/11/2026) |
| V1.2.3 | Cumple | El JavaScript propio no arma código con datos del usuario; los datos viajan en atributos `data-*` escapados. No hay `eval`, `innerHTML` ni `document.write` en `public/assets/sigccr`. | revisión de vistas (19/11/2026) |
| V1.2.4 | Cumple | Todo el acceso a datos usa Query Builder con parámetros enlazados o `query()` con marcadores `?`; no se concatenan datos del usuario en SQL (D-63). | IN-* (cada servicio) |
| V1.2.5 | Cumple | La única llamada al sistema operativo con datos variables es la restauración de copias, que arma el comando con `escapeshellarg` y con rutas generadas por el sistema (`MotorCopias`). | IN de copias |
| V1.3.1 | No aplica | No hay editores de texto enriquecido ni HTML ingresado por el usuario. | — |
| V1.3.2 | Cumple | No se usa `eval()` ni ejecución dinámica de código en `app/`. | revisión estática (Sonar) |
| V1.5.1 | No aplica | No se procesa XML (la interfaz con la pasarela y el BCRA usa JSON). | — |

## V2 · Validación y lógica de negocio

| Req. | Estado | Evidencia | Prueba |
|---|---|---|---|
| V2.1.1 | Cumple | Las reglas de validación de cada dato están definidas en el modelo de datos (`docs/modelo-datos`), en el catálogo de reglas de negocio (RN-01 a RN-82) y en los casos de uso. | revisión documental |
| V2.2.1 | Cumple | Validación positiva (listas permitidas, rangos, formatos) en los asistentes `Campos::*` y en `Reglas*` de cada módulo (D-51). | IN por campo y clase de dato |
| V2.2.2 | Cumple | La validación se hace en la capa de servicios; la del navegador es solo ayuda de uso. | IN por campo |
| V2.3.1 | Cumple | Los flujos tienen secuencia obligatoria verificada en el servidor: solicitud → evaluación → aprobación → desembolso; el servicio rechaza saltos de estado (RN-14, RN-30). | IN de flujos, R-153 a R-182 |

## V3 · Seguridad del navegador

| Req. | Estado | Evidencia | Prueba |
|---|---|---|---|
| V3.2.1 | Cumple (corregido) | `X-Content-Type-Options: nosniff` en todo el sitio; las descargas del legajo salen con `Content-Disposition` y, si son imágenes, con `Content-Security-Policy: sandbox` (PR #200). | S-11, S-12 |
| V3.2.2 | Cumple | Los textos se muestran escapados; no hay inserción de HTML desde el cliente. | revisión de vistas (19/11/2026) |
| V3.3.1 | Cumple (corregido) | Cookie de sesión `__Host-sigccr_sesion` con `Secure`, `HttpOnly`, `SameSite=Lax` y sin atributo `Domain` (PR #200). | S-01 |
| V3.4.1 | Cumple | `Strict-Transport-Security: max-age=31536000; includeSubDomains` en todas las respuestas (nginx, `docker/nginx/default.conf`). | S-11 |
| V3.4.2 | No aplica | La aplicación no habilita CORS (`allowedOrigins` vacío): no responde `Access-Control-Allow-Origin`. | S-10, S-12 |
| V3.5.1 | Cumple | Protección CSRF de CodeIgniter en todos los formularios que modifican datos; no hay API pública. | IN (rutas) |
| V3.5.2 | No aplica | No se usa CORS para proteger funciones sensibles. | — |
| V3.5.3 | Cumple | Las operaciones que modifican datos son `POST`; los `GET` «confirmar…» solo muestran una pantalla de confirmación, sin efectos. | S-08 |

## V4 · API y servicios web

| Req. | Estado | Evidencia | Prueba |
|---|---|---|---|
| V4.1.1 | Cumple | Toda respuesta con cuerpo declara `Content-Type` con `charset=UTF-8` (CodeIgniter y nginx). | S-12 |
| V4.4.1 | No aplica | No se usan WebSockets. | — |

## V5 · Archivos

| Req. | Estado | Evidencia | Prueba |
|---|---|---|---|
| V5.2.1 | Cumple | Límite de 6 MB por archivo (`upload_max_filesize`) y de 64 MB por solicitud; el servicio lo vuelve a controlar. | IN de legajo |
| V5.2.2 | Cumple | `LegajoService::validarArchivos()` detecta el tipo con `finfo` sobre el contenido y lo compara con la lista permitida (PDF, JPG, PNG); no confía en la extensión ni en el tipo declarado. | IN de legajo |
| V5.3.1 | Cumple | Los archivos se guardan en `writable/legajos/`, fuera de la carpeta pública (`public/`); se sirven solo por el controlador con permiso. | IN de legajo |
| V5.3.2 | Cumple | El nombre en disco es aleatorio (`ArchivoLegajo::nombreAleatorio`); el nombre original se sanea y solo se guarda como dato (`nombreSeguro`). | IN de legajo |

## V6 · Autenticación

| Req. | Estado | Evidencia | Prueba |
|---|---|---|---|
| V6.1.1 | Cumple | Documentado en RN-62 y en D-63: 5 intentos fallidos por cuenta en 15 minutos bloquean 15 minutos; 100 intentos por dirección de origen cada 15 minutos (`LimiteIntentosFilter`, también en recuperación, activación y preinscripción). | IN de acceso, S-09 |
| V6.2.1 | Cumple | Mínimo 15 caracteres (`PoliticaContrasena::MINIMO`), máximo 128. | unit de política |
| V6.2.2 | Cumple (corregido) | Nuevo CU-107 «Cambiar contraseña», en el panel (`cuenta/contrasena`) y en el portal (`perfil/contrasena`) (PR #203). | IN-76, I-94 |
| V6.2.3 | Cumple (corregido) | El cambio exige la contraseña actual y la nueva; la nueva debe diferir de la actual y se cierran las demás sesiones (PR #203). | IN-76 |
| V6.2.4 | Cumple (corregido) | La política rechaza las contraseñas de una lista de 3.062 entradas: las 3.000 más frecuentes de 15 o más caracteres de SecLists (licencia MIT) y algunas propias, en el alta, la activación, la recuperación y el cambio (PR #202). | S-07 |
| V6.2.5 | Cumple | Sin reglas de composición (mayúsculas, números, símbolos); solo largo y lista de comprometidas. | unit de política |
| V6.2.6 | Cumple | Todos los campos de contraseña son `type=password`. | revisión de vistas |
| V6.2.7 | Cumple | No se bloquea el pegado ni se desactiva el autocompletado (`autocomplete="current-password"` / `new-password`). | revisión de vistas |
| V6.2.8 | Cumple | La contraseña se verifica tal cual se recibe (`password_verify`, Argon2id); no se recorta ni se cambia la capitalización. | unit de política |
| V6.3.1 | Cumple | Bloqueo por cuenta y por origen (V6.1.1), mensaje genérico ante usuario inexistente y registro en auditoría. | IN de acceso |
| V6.3.2 | Cumple | No existen cuentas por defecto: el administrador inicial se crea con `AdministradorInicialSeeder` indicando usuario y contraseña propios (variables `SIGCCR_ADMIN_*`); la cuenta `sa` de SQL Server no la usa la aplicación (la aplicación usa el usuario propio de mínimo privilegio del rol `rol_sigccr_app`). | revisión de despliegue |
| V6.4.1 | Cumple | Códigos de activación y recuperación de 32 bytes aleatorios (`random_bytes`), de un solo uso y con vencimiento. | IN de acceso |
| V6.4.2 | Cumple | No hay preguntas secretas ni pistas. | — |

## V7 · Sesiones

| Req. | Estado | Evidencia | Prueba |
|---|---|---|---|
| V7.2.1 | Cumple | La sesión se verifica en el servidor (`AuthFilter`) contra la cuenta vigente en cada solicitud. | IN de acceso |
| V7.2.2 | Cumple | Identificador de sesión generado por PHP, dinámico; no hay tokens estáticos. | — |
| V7.2.3 | Cumple | Identificadores de PHP (CSPRNG, `session.use_strict_mode = 1`). | — |
| V7.2.4 | Cumple | `session()->regenerate(true)` al iniciar sesión, al cambiar la contraseña y al finalizar la activación. | IN de acceso, IN-76 |
| V7.4.1 | Cumple | Al cerrar sesión se destruye la sesión del servidor y el navegador borra la caché (`Clear-Site-Data`). | S-04 |
| V7.4.2 | Cumple | La versión de credenciales de la cuenta invalida todas las sesiones abiertas al desactivarla o restablecerla (RN-04, RN-06). | IN de usuarios |

## V8 · Autorización

| Req. | Estado | Evidencia | Prueba |
|---|---|---|---|
| V8.1.1 | Cumple | Matriz de roles y permisos documentada (`CatalogoPermisos`, Trabajo Final 3.6.2, RN-01). | `AutorizacionRutasTest` |
| V8.2.1 | Cumple | Cada ruta del panel declara el permiso (`permiso:`) y una prueba recorre todas las rutas para verificar que ninguna queda sin filtro. | `AutorizacionRutasTest` |
| V8.2.2 | Cumple | Los datos del portal se consultan siempre con el cliente de la sesión; no se acepta un identificador de otro cliente. | IN de portal |
| V8.3.1 | Cumple | La autorización se aplica en filtros y en los servicios, no en el navegador. | IN |

## V9 y V10 · Tokens autocontenidos y OAuth/OIDC

| Req. | Estado | Motivo |
|---|---|---|
| V9.1.1, V9.1.2, V9.1.3, V9.2.1 | No aplica | No se emiten ni aceptan tokens autocontenidos (JWT); la sesión es por referencia en el servidor. |
| V10.4.1 a V10.4.5 | No aplica | La aplicación no es servidor de autorización ni usa OAuth/OIDC. La pasarela de pagos se consulta con credenciales propias, sin delegar identidad. |

## V11 · Criptografía

| Req. | Estado | Evidencia | Prueba |
|---|---|---|---|
| V11.3.1 | Cumple | No se usa ECB ni relleno débil. | S-06 |
| V11.3.2 | Cumple (corregido) | Los datos cifrados (cuerpo de comunicaciones y copias de seguridad) usan AES-256-GCM (`CifradoDatos`, `CifradoCopia`); antes las comunicaciones usaban AES-CTR, no aprobado por el estándar (PR #201). Lectura compatible con lo ya guardado. | S-06 |
| V11.4.1 | Cumple | Argon2id para contraseñas, SHA-256 para huellas y firmas HMAC-SHA-256; sin MD5 ni SHA-1 (corregido en D-53). | revisión estática (Sonar) |

## V12 · Comunicaciones seguras

| Req. | Estado | Evidencia | Prueba |
|---|---|---|---|
| V12.1.1 | Cumple | `ssl_protocols TLSv1.2 TLSv1.3` en nginx. | S-11 |
| V12.2.1 | Cumple | El puerto 80 solo redirige a HTTPS (301) y HSTS evita volver a HTTP. | S-11 |
| V12.2.2 | Cumple | En producción el certificado es de una autoridad pública (se indica en la guía de despliegue); en desarrollo se usa uno autofirmado, fuera del alcance del requisito. | revisión de despliegue |

## V13 · Configuración

| Req. | Estado | Evidencia | Prueba |
|---|---|---|---|
| V13.4.1 | Cumple | nginx niega todo archivo oculto (`location ~ /\.`), por lo que `.git` no se sirve; además la raíz web es `public/`, que no contiene metadatos de control de versiones. | S-11 |

## V14 · Protección de datos

| Req. | Estado | Evidencia | Prueba |
|---|---|---|---|
| V14.2.1 | **Desvío documentado** | Las búsquedas de clientes y solicitudes usan `GET` con el DNI o CUIL como parámetro de la URL, por lo que el dato queda en los registros de acceso y en el historial del navegador. Las contraseñas, códigos y tokens nunca viajan en la URL. Ver D-63 (punto 5): se acepta el riesgo en este nivel porque el DNI es un dato de identificación que el operador ya ve en pantalla, y se propone pasar la búsqueda a `POST` en una iteración posterior. | — |
| V14.3.1 | Cumple (corregido) | Las páginas autenticadas llevan `Cache-Control: no-store` y el cierre de sesión envía `Clear-Site-Data: "cache"` (PR #200). | S-02 a S-04 |

## V15 · Componentes y diseño seguro

| Req. | Estado | Evidencia | Prueba |
|---|---|---|---|
| V15.1.1 | Cumple (corregido) | `docs/seguridad/politica_dependencias.md` fija los plazos de corrección según gravedad y Dependabot propone las actualizaciones (PR #204). | revisión documental |
| V15.2.1 | Cumple (corregido) | El CI ejecuta `composer audit --locked` y falla ante un aviso de seguridad conocido (PR #204). | CI |
| V15.3.1 | Cumple | Las consultas seleccionan columnas explícitas; el portal y los listados no devuelven registros completos. | IN |
