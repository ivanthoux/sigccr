# Diagramas de estados (4.2)

Fuentes Graphviz de las once máquinas de estados del documento. Son las que generan las figuras; el archivo de estados de draw.io de octubre quedó desactualizado y no se usa.

| Archivo | Máquina de estados |
|---|---|
| `cuenta_acceso.dot` | Cuenta de acceso (el bloqueo por intentos incluye CU-107, RN-62) |
| `linea_credito.dot` | Línea de crédito |
| `solicitud.dot` | Solicitud de crédito |
| `revision_documental.dot` | Revisión documental |
| `evaluacion.dot` | Evaluación crediticia |
| `cuota.dot` | Cuota |
| `credito.dot` | Crédito |
| `propuesta_refinanciacion.dot` | Propuesta de refinanciación |
| `orden_pago.dot` | Orden de pago electrónico |
| `autorizacion_devolucion.dot` | Autorización de devolución de saldo a favor |
| `campana_oferta.dot` | Campaña crediticia y oferta |

```bash
for f in *.dot; do dot -Tpng -Gdpi=200 "$f" -o "${f%.dot}.png"; dot -Tsvg "$f" -o "${f%.dot}.svg"; done
```
