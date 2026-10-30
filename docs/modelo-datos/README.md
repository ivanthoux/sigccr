# Modelo de datos

Especificación única del esquema de SQL Server (sección 4.3 del documento del proyecto). Describe el esquema **vigente**, es decir, el que resulta de aplicar todas las migraciones de `app/Database/Sql`.

| Archivo | Uso |
|---|---|
| `spec.py` | Tablas, columnas, claves, restricciones, índices y enumeraciones, con la descripción de cada elemento. |
| `gen.py` | Genera `out/sigccr_esquema.sql` (script completo) y `out/dic.json` (diccionario de datos), e informa los totales de 4.3 y las dependencias entre subsistemas. |
| `verificar.py` | Comprueba que las migraciones produzcan el mismo esquema que la especificación. Lo ejecuta la integración continua. |
| `er.py`, `er_drawio.py`, `er_full.py` | Diagramas entidad-relación por subsistema (Graphviz y draw.io) y del modelo completo (4.3.2). |

## Cambiar el esquema

1. Escribir una migración nueva: `app/Database/Sql/NN_nombre_subir.sql`, su `_bajar.sql` y la clase en `app/Database/Migrations` (las migraciones aplicadas no se modifican).
2. Reflejar el mismo cambio en `spec.py`.
3. Verificar y regenerar:
   ```bash
   cd docs/modelo-datos
   python3 gen.py        # totales y dependencias; debe terminar «sin errores»
   python3 verificar.py  # «Esquema alineado»
   python3 er.py         # diagramas del subsistema afectado (requiere Graphviz)
   ```
4. Actualizar en el documento los totales de 4.3, la tabla del subsistema y su diagrama, y registrar la decisión en `docs/decisiones.md` si corresponde.

Las tablas de SS-10 no tienen claves foráneas hacia otros subsistemas (3.7.1): las referencias a usuarios se validan en el servicio.
