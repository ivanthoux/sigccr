# Diseño por iteración

Fuentes de los diagramas de secuencia de la sección 4.5 del documento del proyecto (realizaciones de los casos de uso de cada iteración de Construcción).

| Iteración | Carpeta | Diagramas |
|---|---|---|
| 1 | `iteracion-1/` | CU-01, CU-02, CU-04, CU-11, CU-12, CU-13, CU-17, CU-83 y CU-95 (Imágenes 54 a 62) |
| 2 | `iteracion-2/` | CU-21, CU-22, CU-23, CU-86, CU-79, CU-26, CU-27 y CU-89 (Imágenes 63 a 70) |
| 3 | `iteracion-3/` | CU-33, CU-37, CU-88, CU-34, CU-90, CU-74, CU-78, CU-102, CU-97 y CU-103 (Imágenes 71 a 80) |

Cada diagrama está en Mermaid (`.mmd`, la fuente) y en draw.io (`.drawio`, editable con elementos UML; `Secuencias_iteracion_1.drawio` los reúne en un archivo con una página por diagrama). Las imágenes del documento se generan con la configuración de estilo `mermaid.json`:

```bash
mmdc -i docs/diseno/iteracion-1/i4_cu11.mmd -o i4_cu11.png -c docs/diseno/mermaid.json -s 2 -w 1500 -b white
```

Si cambia el código de un caso de uso diagramado, se actualiza su `.mmd` en el mismo *pull request*.
