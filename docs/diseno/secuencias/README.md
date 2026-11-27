# Diagramas de secuencia de diseño (UML 2)

Fuentes en PlantUML de los diagramas de secuencia de las secciones 4.4.7 y 4.5 del documento del proyecto. Reemplazan a las fuentes Mermaid de `iteracion-1` a `iteracion-3`, que quedan como antecedente. El estilo sigue el modelo de trabajo indicado por el director (26/11/2026).

## Convenciones

| Elemento | Cómo se dibuja |
|---|---|
| Título | Nombre del caso de uso, por ejemplo `CU-22 Registrar Solicitud de Crédito`. Un diagrama por página. |
| Actor | Figura de palo con su barra de activación durante toda la interacción. |
| Líneas de vida | Clases reales del diseño (4.4.2), con su estereotipo: «control» para controladores, filtros y comandos (`:Solicitudes`), «service» para los servicios (`:SolicitudService`) y «utility» para las bibliotecas de métodos estáticos, que van sin dos puntos (`Elegibilidad`). `db : BaseConnection` representa el acceso a la base. Una clase por línea de vida. |
| Mensaje sincrónico | Línea continua con punta llena, con el método real y sus parámetros: `1.1: registrar(simulacionId, archivos, usuarioId)`. Si devuelve un valor, se escribe como asignación (`motivo = motivoNoAdmitida(version, datos, hoy)`) y no se dibuja la respuesta. |
| Respuesta | Línea punteada con punta abierta y el valor devuelto: `1.1.12: [id, numero]`. |
| Excepción | Respuesta punteada al llamador con la excepción y la regla: `ReglaNegocioException(motivo, "RN-13")`. |
| Numeración | Jerárquica y manual: `1`, `1.1`, `1.1.1`… como en el modelo del director. |
| Autollamada | Método propio del objeto (`bloquear("clientes", id)`, `registrarTransicion(…)`). |
| Base de datos | Si el servicio tiene un método propio para la operación (`bloquear`, `exigidos`, `actualizarConVersion`), se dibuja ese método como autollamada. Si la consulta está escrita en el código, se dibuja hacia `db` como `query(<qué obtiene>)`, `insert("tabla", …)` o `update(<qué cambia>)`; todas las consultas son parametrizadas (RNF-12). |
| Fragmentos | `alt` con guardas `[Si (…)]` y una rama por condición; `opt` para una sola rama; `loop` con `[Mientras (…)]`; `critical [transacción]` para lo que ocurre dentro de una transacción. |
| Interacción referenciada | `ref` cuando el diagrama no entra legible en una página; la interacción se dibuja aparte con el título `sd Nombre` y continúa la numeración del mensaje que la referencia (1.1.3 → 1.1.3.1, 1.1.3.2…). Si dos escenarios referencian la misma interacción (CU-105), esta tiene numeración propia y lo aclara en una nota. Un caso de uso con dos escenarios del actor (por ejemplo, pedir el enlace y definir la contraseña) se dibuja en dos diagramas numerados. |
| Tamaño | Hasta 6 líneas de vida además del actor y unos 15 mensajes por diagrama, en hoja vertical a todo el ancho (17,1 cm): la letra impresa queda entre 5 y 7,5 pt (el modelo del director, unos 5 pt); en el PDF el SVG se amplía sin perder nitidez. Los diagramas son más altos que anchos, así que la hoja apaisada los achica (la altura útil es de 15,5 cm). |
| Líneas de vida | Punteadas (trazo 9-6), de 1,8 px y gris oscuro, para que se vean impresas. |
| Notas | Solo para aclaraciones que no son mensajes (variables locales, decisiones). |

## Generar las imágenes

```bash
docs/diseno/secuencias/generar.sh            # todos los .puml
docs/diseno/secuencias/generar.sh cu22_1.puml
```

Quedan en `svg/` (vectoriales, las que se insertan en el documento) y en `png/` a 200 ppp para vistas previas. Si cambia el código de un caso de uso diagramado, se actualiza su `.puml` en el mismo *pull request*.
