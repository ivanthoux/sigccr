# Flujo de trabajo

El proyecto usa Git y GitHub para registrar cada cambio, revisarlo antes de integrarlo y vincularlo con el plan de iteraciones (2.2.1).

## Ramas

| Rama | Uso |
|---|---|
| `main` | Versiones entregables: cierre de cada hito y punto de control. Protegida: solo recibe *pull requests* con la integración continua en verde. |
| `develop` | Integración de la iteración en curso. |
| `feature/CU-xx-descripcion` | Un caso de uso o un grupo pequeño de casos relacionados (por ejemplo `feature/CU-33-cobro-ventanilla`). |
| `fix/descripcion` | Corrección de un defecto. |
| `docs/descripcion` | Cambios de documentación. |

Cada rama nace de `develop` y vuelve a `develop` por *pull request*. Al cerrar una iteración, `develop` se integra en `main` y se etiqueta la versión.

## Mensajes de commit

Formato *Conventional Commits*, en español, en modo imperativo:

```
<tipo>(<alcance>): <descripción breve>

<detalle opcional: qué y por qué, reglas de negocio involucradas>

Refs: CU-33, RN-29
```

Tipos: `feat` (funcionalidad), `fix` (corrección), `test` (pruebas), `docs` (documentación), `refactor` (cambio interno sin cambio de comportamiento), `chore` (configuración, dependencias), `ci` (integración continua), `db` (migraciones y datos semilla). Alcances habituales: el subsistema (`ss01` … `ss11`), `finanzas`, `docker`, `docs`.

Ejemplos: `feat(ss05): registrar cobro en ventanilla`, `test(finanzas): agregar caso de punitorios con cambio de tasa`, `db(ss02): agregar índice de búsqueda por denominación`.

## Pull requests

Cada *pull request* indica los casos de uso y las reglas que implementa, cómo se probó y si cambia el modelo de datos o la documentación (ver la plantilla). La integración continua ejecuta las pruebas del motor financiero, el cálculo independiente de referencia, PHP_CodeSniffer (PSR-12) y PHPStan.

## Versiones

Versionado semántico con etiquetas en `main`:

| Versión | Hito |
|---|---|
| v0.1.0 | Fin de Elaboración: arquitectura ejecutable (27/10) |
| v0.2.0 | Control 1: iteración 1 (03/11) |
| v0.3.0 | Control 2: iteración 2 (13/11) |
| v0.4.0 | Control 3: iteración 3 (23/11) |
| v0.5.0 | Fin de Construcción: iteración 4 (30/11) |
| v1.0.0 | Entrega final (09/12) |

Los cambios de cada versión se resumen en `CHANGELOG.md`. Las decisiones de diseño se registran en `docs/decisiones.md`.

## Seguimiento

Cada actividad de la lista de pendientes se refleja como *issue* de GitHub, agrupada en un *milestone* por iteración. Un *pull request* cierra su *issue* con `Closes #n` en la descripción.
