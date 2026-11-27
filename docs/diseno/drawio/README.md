# Diagramas en draw.io

Fuentes de los diagramas de casos de uso, actores y subsistemas y del modelo de dominio (capítulos 3 y 4). Los diagramas de estados están en Graphviz, en `../estados/`. Se editan con draw.io (escritorio o web) y se exportan con `generar.sh`, que no necesita draw.io: dibuja cada página con mxGraph 4.2.2 (la biblioteca de draw.io) en Chromium sin interfaz.

| Archivo | Contenido | Cambios del 26/11/2026 (J08) |
|---|---|---|
| `SIG-CCR_Diagramas_v14.drawio` | Actores, casos de uso SS-01 a SS-11, subsistemas | SS-09: CU-56 incluye CU-32, como en la v18 del documento. CU-107 en SS-01; CU-108 con el actor Cliente y el actor «Servicio externo de información crediticia» (Central de Deudores del BCRA, D-60) en lugar del de ARCA en SS-02; CU-106 en SS-04; se quita el actor de ARCA de la jerarquía y las etiquetas de los actores abstractos pasan al costado para que la generalización no las tape. |
| `SIG-CCR_Modelo_de_Dominio_v14.drawio` | Vista general y dominio por subsistema | SS-02: `fechaDeclaracionPep`, PreRegistro y SolicitudDerechos. SS-04: el contrato «se redacta con» la versión del modelo de contrato (CU-106, D-56). SS-11: `hashVerificacion` y `estado: EstadoCopia`. |

## Exportar

```bash
docs/diseno/drawio/generar.sh
```

Deja `svg/<archivo>/<página>.svg` (vectorial, con las etiquetas HTML de draw.io) y `png/<archivo>/<página>.png` a escala 4 (unos 400 ppp al ancho de la página), que es la que se inserta en el documento porque Word no dibuja las etiquetas HTML de un SVG. `png/` no se versiona.

`python3 herramientas/paginas.py fuentes/<archivo>.drawio | python3 herramientas/celdas.py SS-02` lista las celdas de una página (identificador, estilo, texto, geometría y extremos) para ubicar lo que se quiere cambiar.
