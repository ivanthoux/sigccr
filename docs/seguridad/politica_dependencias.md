# Política de actualización de componentes de terceros

Referencia: OWASP ASVS 5.0, requisitos V15.1.1 (plazos de corrección definidos por riesgo) y V15.2.1 (la aplicación no
contiene componentes que superen esos plazos). Decisión D-63.

## Alcance

Todo componente que forma parte del sistema o de su entrega:

| Componente | Dónde se declara | Cómo se vigila |
|---|---|---|
| Bibliotecas PHP (CodeIgniter 4 y dependencias) | `composer.json` y `composer.lock` | `composer audit --locked` en cada ejecución del CI; Dependabot (composer) |
| Imagen de PHP y paquetes del sistema | `docker/php/Dockerfile` | Dependabot (docker); reconstrucción de la imagen en cada versión |
| Imagen de SQL Server | `docker-compose.yml` (versión fija, D-62) | Revisión mensual de las actualizaciones acumulativas |
| Acciones de GitHub | `.github/workflows` (fijadas por SHA) | Dependabot (github-actions) |
| Herramientas de las pruebas E2E | `tests/e2e/package.json` | Dependabot (npm); no se distribuyen con el sistema |
| Bootstrap, Bootstrap Icons, jQuery y DataTables | `public/vendor` | Revisión mensual de sus avisos de seguridad |

## Plazos de corrección

El plazo cuenta desde la publicación del aviso, según la severidad que informa la base de avisos (CVSS), ajustada por
la exposición real del componente en el sistema:

| Severidad | Plazo para actualizar o mitigar |
|---|---|
| Crítica | 7 días corridos |
| Alta | 30 días corridos |
| Media | 90 días corridos |
| Baja | en la siguiente versión del sistema |

Un componente con un aviso que no se puede corregir en el plazo (por ejemplo, sin versión corregida) requiere una
mitigación documentada en el registro de decisiones y una nueva fecha de revisión; no se acepta superar el plazo sin
ese registro.

## Cómo se cumple

1. **Cada cambio.** El CI ejecuta `composer audit --locked`: un aviso conocido en cualquier dependencia de PHP hace
   fallar la integración y no se publica una versión con él.
2. **Cada semana.** Dependabot abre un pull request con las actualizaciones; los avisos de seguridad llegan de inmediato.
   Los pull requests de seguridad se atienden dentro del plazo de su severidad; los demás, dentro del mes.
3. **Cada versión del sistema.** Antes de publicarla se ejecuta `composer audit --locked` y se revisan los componentes de
   `public/vendor`; el resultado figura en el CHANGELOG de la versión.
4. **Cada mes.** Se revisa la actualización acumulativa de SQL Server (D-62) y los avisos de las bibliotecas de la
   interfaz.

## Responsable

El responsable del proyecto (el alumno, durante el Trabajo Final) atiende los pull requests de Dependabot y registra las
excepciones. En una entidad que adopte el sistema, el rol equivalente es el del responsable de seguridad de la
información.
