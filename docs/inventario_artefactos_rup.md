# Inventario de artefactos del Proceso Unificado (J01)

Revisión del 26/11/2026 sobre el Trabajo Final v18, el Plan de pruebas v1.6 y el repositorio (v0.7.0). Para cada disciplina se indican los artefactos que el UP/RUP prevé para un proyecto de este tamaño, dónde están y qué falta. Los artefactos que el proceso considera opcionales para un equipo de una persona se marcan como tales.

Estado: **Completo**, **Parcial** (existe pero falta actualizar o completar), **Falta** u **Opcional**.

## Gestión del proyecto

| Artefacto | Ubicación | Estado | Pendiente |
|---|---|---|---|
| Plan de desarrollo de software | TF cap. 2 (2.1 organización, 2.2 programa, 2.2.3 Gantt) | Parcial | Actualizar el estado de las actividades 10 a 12 y el Gantt con las fechas reales (J06, J08). |
| Plan de iteración | TF 2.2.1 (contenido de cada iteración) | Completo | — |
| Lista de riesgos y planes | TF 2.3 y 2.4 (R1 a R9, preventivos y correctivos) | Parcial | Registrar qué riesgos ocurrieron y cómo se trataron (J05). |
| Evaluación de iteración | TF 2.2.2: «Cumplimiento del hito Fin de Elaboración» y «Cumplimiento de los controles 1 a 3» | Parcial | Falta el hito Fin de Construcción (iteración 4) y la Transición; falta, por iteración, lo planificado frente a lo real (fechas, casos de uso, defectos, desvíos) (J02). |
| Evaluación de estado del proyecto | Artefacto de pendientes y puntos de control | Opcional | Basta con las evaluaciones de iteración. |

## Modelado del negocio y requisitos

| Artefacto | Ubicación | Estado | Pendiente |
|---|---|---|---|
| Visión | TF cap. 1 (problema, oportunidad, solución, alcance, interesados, supuestos, marco normativo) | Completo | Revisar 1.5 contra lo construido (D-61 y desvíos) (J13). |
| Glosario | TF 3.4 | Completo | Revisar términos nuevos de la Construcción (PEP, derechos del titular, modelo de contrato) (J13). |
| Reglas de negocio | TF 3.5 (RN, parámetros, estados) | Completo | Verificar que reflejen D-62 a D-65 (H03). |
| Especificación de requisitos (RF) | TF 3.2 | Completo | — |
| Especificación complementaria (RNF) | TF 3.3 | Completo | — |
| Modelo de casos de uso | TF 3.6 (actores, matrices, diagramas, catálogo CU-01 a CU-108) | Parcial | Diagramas de SS-01, SS-04, SS-07 y SS-10 sin CU-106 a CU-108; texto de CU-21 y CU-22 sin D-65 (J06, H03). |
| Modelo de dominio | TF 4.1 | Parcial | Faltan modelo de contrato, declaración PEP y solicitud de derechos del titular (J06). |
| Prototipos de interfaz | — | Opcional | Las capturas del manual de usuario cumplen esa función. |

## Análisis y diseño

| Artefacto | Ubicación | Estado | Pendiente |
|---|---|---|---|
| Documento de arquitectura (4+1) | TF 4.4 | Parcial | 4.4.4 Vista de procesos sin diagrama; 4.4.3 tiene tabla de paquetes, sin diagrama de componentes (J07). |
| Diagrama de subsistemas | TF 3.7 | Parcial | Verificar ubicación de CU-106 a CU-108 (J06). |
| Modelo de diseño | TF 4.5 (clases y secuencias por iteración) | Parcial | Secuencias de CU-21 y CU-22 por D-65; fuentes de la iteración 4 fuera del repositorio (J06, H07). |
| Diagramas de estados | TF 4.2 (11 diagramas) | Parcial | Verificar cuenta de acceso (bloqueo RN-62) y si corresponde uno para la solicitud de derechos (J06). |
| Modelo de datos | TF 4.3 y `sigccr_esquema.sql` | Parcial | Diagramas entidad-relación anteriores a las tablas nuevas; **4.3.1 menciona un diccionario de datos en 4.3.6 que no existe en el documento** (J06, J12). |

## Implementación

| Artefacto | Ubicación | Estado | Pendiente |
|---|---|---|---|
| Modelo de implementación | TF 4.4.3 (paquetes) y repositorio | Parcial | Capítulo de Implementación (J03). |
| Builds y versiones | GitHub: v0.1.0 a v0.7.0, `CHANGELOG.md` | Parcial | Etiqueta v0.7.0 pendiente (H08) y v1.0.0 (H05). |
| Guías de programación | `CONTRIBUTING.md`, `phpcs.xml`, `phpstan.neon` | Completo | Resumir en el capítulo de Implementación (J03). |

## Pruebas

| Artefacto | Ubicación | Estado | Pendiente |
|---|---|---|---|
| Plan de pruebas | Plan de pruebas v1.6 (aparte) | Completo | Volcar la cobertura global (G14) si no está. |
| Casos y procedimientos de prueba | `docs/pruebas` (iteraciones 1 a 4, integral, rendimiento, usabilidad), casos del motor financiero, batería E2E | Completo | — |
| Resumen de evaluación de pruebas | — | Falta | Capítulo de Pruebas con resultados, cobertura y defectos (J04). |
| Listas de comprobación de seguridad y normativa | `docs/seguridad` (ASVS nivel 1, Ley 25.326, política de dependencias) | Completo | Resumir en el capítulo de Pruebas (J04). |

## Despliegue

| Artefacto | Ubicación | Estado | Pendiente |
|---|---|---|---|
| Plan de despliegue | — | Falta | Requisitos del servidor, pasos, verificación, vuelta atrás y puesta en producción (J02, con H02). |
| Manual técnico (instalación y operación) | `README.md` como base | Falta | H02; se valida con G05. |
| Manual de usuario | `Manual_de_usuario_SIG-CCR.docx` (16/11) | Parcial | H01. |
| Notas de versión | `CHANGELOG.md` | Parcial | Versión 1.0.0 (H05). |
| Material de capacitación | Guía de demostración del recorrido del crédito, guion del 25/11 | Opcional | — |

## Configuración y cambios

| Artefacto | Ubicación | Estado | Pendiente |
|---|---|---|---|
| Plan de gestión de configuración | `CONTRIBUTING.md` (ramas, commits, PR, etiquetas) y TF 2.4 (R2) | Parcial | Resumir en el capítulo de Implementación (J03). |
| Solicitudes de cambio y decisiones | `docs/decisiones.md` (D-01 a D-65) | Parcial | El TF llega hasta D-64; falta D-65 (H03). |

## Lo que falta en el documento principal

1. Evaluación de la iteración 4 y de la Transición, y comparación entre lo planificado y lo real por iteración (J02).
2. Plan de despliegue (J02) y manual técnico (H02).
3. Capítulos de Implementación (J03), Pruebas (J04), y Transición con conclusiones y bibliografía (J05).
4. Diagramas de la vista de procesos y de componentes (J07).
5. Diccionario de datos referido como 4.3.6: agregarlo o quitar la referencia y remitir a «Tablas de Base de Datos» como documento aparte (J12).
6. D-65 y la actualización de CU-21 y CU-22 (H03).
