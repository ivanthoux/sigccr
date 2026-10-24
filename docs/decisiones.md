# Registro de decisiones

Decisiones de análisis y diseño adoptadas durante el proyecto. Cada una indica su contexto, la decisión, sus consecuencias y su estado de validación con el director. Un cambio posterior se registra como una decisión nueva que reemplaza a la anterior.

| ID | Fecha | Estado |
|---|---|---|
| D-01 | 23/10/2026 | Adoptada, pendiente de validación |
| D-02 | 23/10/2026 | Adoptada, pendiente de validación |
| D-03 | 23/10/2026 | Adoptada, pendiente de validación |
| D-04 | 23/10/2026 | Adoptada, pendiente de validación |
| D-05 | 23/10/2026 | Adoptada, pendiente de validación |
| D-06 | 23/10/2026 | Adoptada, pendiente de validación |
| D-07 | 23/10/2026 | Adoptada, pendiente de validación |
| D-08 | 23/10/2026 | Adoptada, pendiente de validación |

## D-01 · Capital máximo financiable con el IVA del interés (CU-87)

**Contexto.** CU-87 paso 3 calcula el capital máximo descontando de la capacidad de pago solo el gasto administrativo y su IVA. En un plan francés la primera cuota incluye además el IVA del interés, por lo que la alternativa del monto máximo superaría siempre la capacidad y se descartaría en el paso 6.

**Decisión.** El capital máximo para n cuotas es C ≤ (K − g · (1 + IVA)) / (1 / a(n, i) + IVA · i), con a(n, i) = [1 − (1 + i)⁻ⁿ] / i. El resultado se redondea hacia abajo al paso configurado y se verifica con el plan real.

**Consecuencias.** Implementado en `CapacidadPago::capitalMaximo` (caso CP-F17). Corregir el texto de CU-87 paso 3.

## D-02 · Valores de ejemplo para los parámetros «definidos por la entidad» (3.5.1)

**Decisión.** Tasa punitoria diaria 0,12 %; refinanciación con TNA entre 30 % y 80 % y hasta 36 cuotas; campañas: umbral de disponibilidad $ 5.000.000, proyección a 30 días, factor de cobrabilidad 0,90, reserva mínima $ 1.000.000, capital máximo $ 3.000.000 y 15 días de duración.

**Consecuencias.** Cargados por `ParametrosInicialesSeeder` con la marca `_pendientes_de_definir`. Se modifican con CU-51 y CU-60 sin cambiar código.

## D-03 · Conexión con SQL Server sin cifrar dentro de la red interna de Docker

**Contexto.** El controlador ODBC 18 exige un certificado de confianza para cifrar la conexión.

**Decisión.** En desarrollo, `encrypt = false` en la red interna (el puerto 1433 no se publica). En producción, certificado TLS en SQL Server, su autoridad agregada a la imagen `app` y `encrypt = true`.

**Consecuencias.** Ajustar la vista de despliegue (4.4.5), que indica una conexión cifrada.

## D-04 · Prelación del concepto no capitalizable (RN-29, RN-70)

**Decisión.** En las cuotas de una refinanciación, el concepto no capitalizable se imputa después del IVA y antes del capital.

**Consecuencias.** `Imputador::PRELACION`; valor `NoCapitalizable` agregado a `ConceptoDeuda` (4.1.1).

## D-05 · Valores de las enumeraciones complementarias (4.1.1)

**Decisión.** Se adoptan los valores de las 27 enumeraciones agregadas en la v6 (por ejemplo `DecisionResolucion`: Aprobada · Rechazada · DevueltaRevision).

**Consecuencias.** Restricciones CHECK del esquema (4.3).

## D-06 · Dependencias SS-06 → SS-03 y SS-08 → SS-02 (3.7.1)

**Decisión.** Se agregan a la descripción de subsistemas: el crédito conserva la versión de condiciones de su línea y las ofertas se dirigen a clientes.

## D-07 · Claves foráneas reubicadas y referencias genéricas (4.3.1)

**Decisión.** Las claves foráneas siguen el sentido de las dependencias entre subsistemas. Cuando una asociación del dominio va en sentido contrario, la clave se ubica en el subsistema dependiente (`liquidaciones.credito_id`, `ofertas.solicitud_id`, `clientes.cuenta_cliente_id`) o se reemplaza por una referencia genérica validada por el servicio (`afectaciones_fondos`, `comunicaciones`, identificadores de usuario en SS-10).

## D-08 · Redondeo directo de los importes exigibles (RN-50)

**Decisión.** Los importes exigibles (interés de cada cuota, IVA, gastos de otorgamiento) se redondean a dos decimales desde el valor exacto, sin pasar por cuatro decimales, para evitar el doble redondeo. Los cuatro decimales se usan en los resultados que se acumulan antes de exigirse (tramos de punitorios).

**Consecuencias.** `AmortizacionFrancesa`, `Punitorios`.
