# Prueba de rendimiento

Verificación de RNF-03 (tiempo de respuesta con volumen), RNF-06 (decisiones simultáneas) y RNF-07 (listados de volumen creciente), con los tiempos de los procesos automáticos como referencia de RNF-10. El diseño de la prueba está en la decisión D-46. Los resultados son los de la corrida del 17/11/2026 sobre el commit `db4ad60`, en el flujo *Rendimiento* de GitHub Actions.

**Resultado:** cumple todos los criterios. Ninguna operación con criterio superó los 210 ms en el percentil 95 (el límite es de 2.000 ms) y no hubo errores.

## Cómo se ejecuta

- **En GitHub Actions:** pestaña *Actions*, flujo *Rendimiento*, *Run workflow*. Se puede indicar la duración de la carga mixta en segundos (600 por omisión). También corre solo cuando cambian `tests/rendimiento/`, el flujo o `RendimientoSeeder`. Tarda unos 30 minutos y publica los resultados en la rama `rendimiento-resultados`: `resumen.md`, el informe HTML de JMeter (`informe-carga/`), el registro de la aplicación y el consumo de recursos.
- **En un equipo con Docker y JMeter 5.6:** desde la carpeta del proyecto, `bash tests/rendimiento/ejecutar.sh`. Usa los puertos 80 y 443, que deben estar libres.

La ejecución:

1. Levanta la pila de pruebas.
2. Carga los datos base con la batería de punta a punta (especificaciones 01 a 16, 149 pruebas).
3. Pasa la aplicación a modo producción.
4. Limita los contenedores al servidor de referencia.
5. Carga el volumen con `RendimientoSeeder`.
6. Corre los dos planes de JMeter (`carga.jmx` y `concurrencia.jmx`).
7. Mide los procesos automáticos.
8. Evalúa los resultados con `evaluar.py`, que termina con error si algún criterio no se cumple.

Los usuarios de la carga tienen una contraseña aleatoria, generada en cada ejecución. No queda registrada en ningún archivo.

## Volumen y entorno

| Elemento | Valor |
|---|---|
| Clientes | 20.022 (20.000 de la carga y 22 de los datos base) |
| Créditos | 30.015 (30.000 liquidados, de 10 cuotas cada uno) |
| Cuotas | 300.201, con 6.642 vencidas (uno de cada siete créditos en mora) |
| Tiempo de carga del volumen | 25 s |
| Base de datos (SQL Server 2022) | 2 vCPU y 4 GB |
| Aplicación (PHP-FPM) | 1,5 vCPU y 3 GB |
| Servidor web (Nginx) | 0,5 vCPU y 1 GB |
| Total | 4 vCPU y 8 GB, el servidor de referencia de RNF-03 |
| Equipo de prueba | Ejecutor de GitHub Actions de 4 CPU, compartido con JMeter |
| Modo de la aplicación | `production` |

JMeter corre en el mismo equipo y le quita CPU a la pila: en un servidor dedicado los tiempos serían iguales o menores.

## Carga mixta (RP-01 a RP-05)

10 usuarios simultáneos durante 10 minutos, con una pausa aleatoria de 2 a 7 segundos entre operaciones:

- **3 Analistas** (RP-01 y RP-05): buscan clientes por apellido, documento y CUIL, y consultan el listado de solicitudes.
- **6 Cajeros**, con su caja abierta (RP-02, RP-03 y RP-05):
  1. buscan al cliente por nombre;
  2. abren su deuda;
  3. registran el cobro de la cuota más antigua;
  4. consultan los comprobantes del día.
- **1 Gerente:** consulta el tablero y los reportes de cartera y de morosidad.

Criterio de aceptación: percentil 95 menor que 2.000 ms y a lo sumo 1 % de errores en las operaciones de RNF-03 y RNF-07. Tiempos en milisegundos.

| Caso | Operación | Requisito | Muestras | p50 | p90 | p95 | p99 | Máximo | Errores | Cumple |
|---|---|---|---|---|---|---|---|---|---|---|
| RP-01 | Búsqueda de clientes por apellido | RNF-03 | 312 | 63 | 112 | 151 | 193 | 270 | 0 % | Sí |
| RP-01 | Búsqueda de clientes por documento | RNF-03 | 312 | 21 | 48 | 83 | 90 | 137 | 0 % | Sí |
| RP-01 | Búsqueda de clientes por CUIL | RNF-03 | 312 | 20 | 46 | 84 | 99 | 104 | 0 % | Sí |
| RP-02 | Búsqueda del cliente en el cobro | RNF-03 | 471 | 57 | 94 | 125 | 171 | 194 | 0 % | Sí |
| RP-03 | Deuda del cliente | RNF-03 | 471 | 70 | 166 | 204 | 351 | 897 | 0 % | Sí |
| RP-03 | Registro del cobro | RNF-03 | 471 | 74 | 153 | 204 | 362 | 392 | 0 % | Sí |
| RP-05 | Listado de solicitudes | RNF-07 | 310 | 63 | 105 | 140 | 175 | 188 | 0 % | Sí |
| RP-05 | Comprobantes del día | RNF-07 | 471 | 26 | 54 | 82 | 109 | 150 | 0 % | Sí |
| RP-05 | Tablero gerencial | Informativo | 45 | 3.033 | 3.268 | 3.295 | 3.328 | 3.328 | 0 % | — |
| RP-05 | Reporte de cartera | Informativo | 44 | 2.260 | 2.385 | 2.436 | 2.519 | 2.519 | 0 % | — |
| RP-05 | Reporte de morosidad | Informativo | 44 | 502 | 626 | 684 | 712 | 712 | 0 % | — |

Se registraron 471 cobros en 10 minutos. El tablero y el reporte de cartera recorren toda la cartera (30.000 créditos) en cada consulta. Ningún requisito fija su tiempo, y con 2,4 y 3,3 segundos siguen siendo usables para un Gerente. Si la cartera crece, se los puede acelerar con un resumen precalculado por el proceso nocturno.

Consumo máximo durante la carga, muestreado cada 15 segundos:

| Contenedor | CPU | Memoria |
|---|---|---|
| Base de datos | 145 % de 200 % | 2,8 GB de 4 GB |
| Aplicación | 40 % de 150 % | 78 MB de 3 GB |
| Servidor web | 1,5 % de 50 % | 7 MB de 1 GB |

La base de datos es el componente más exigido y queda con margen.

## Decisiones simultáneas (RP-06, RNF-06)

Dos usuarios envían la misma operación en el mismo instante: JMeter los sincroniza con un temporizador de sincronización.

| Caso | Operación | Esperado | Obtenido | Cumple |
|---|---|---|---|---|
| RP-06a | Dos Cajeros cobran la misma cuota del mismo cliente | Un solo cobro confirmado | 1 cobro confirmado | Sí |
| RP-06b | Dos Gerentes anulan el mismo cobro | Una sola anulación y un solo saldo a favor | Cobro Anulado, 1 anulación, 1 saldo a favor | Sí |

En los dos casos las dos peticiones reciben respuesta (HTTP 200, entre 70 y 88 ms), y ninguna duplica un importe. `CobroService` bloquea el crédito, sus cuotas y el cobro con `UPDLOCK` dentro de la transacción. Por eso la segunda petición espera a que termine la primera y después ve el estado nuevo. En la anulación recibe el aviso «ya fue anulado» (RN-33). En el cobro, la cuota ya está saldada.

## Procesos automáticos con el volumen completo (RP-07, RNF-10)

| Proceso | Duración | Resultado |
|---|---|---|
| `cartera:punitorios` | 131,7 s | Correcto: punitorios devengados en 5.941 cuotas vencidas |
| `fondos:proyectar` | 0,3 s | Correcto |
| `vencimientos:controlar` | 0,2 s | Correcto |
| `evaluacion:ejecutar` | 0,2 s | Correcto |

El cálculo de punitorios es el proceso más largo: recorre una por una las 6.642 cuotas vencidas, y cada una tiene su propia transacción y su auditoría. Dura poco más de dos minutos y corre de madrugada, de modo que cabe en la ventana nocturna. No requiere cambios con este volumen.

## Hallazgos y correcciones

Las primeras corridas encontraron tres problemas, ya corregidos (D-46, punto 6):

| Corrida | Hallazgo | Corrección |
|---|---|---|
| 1 y 2 | El reporte de cartera y el tablero respondían con error 500: la consulta traía todos los créditos a PHP y superaba el búfer de 10 MB del controlador `sqlsrv`. | `ReporteService::cartera` agrupa en SQL Server y limita el detalle a 1.000 créditos. El búfer sube a 50 MB. |
| 3 | 0,42 % de errores en la deuda del cliente, con un máximo de 8 s: con seis Cajeros a la vez, SQL Server cortaba por bloqueo mutuo la actualización de los tramos punitorios. | `BaseService::transaccion` reintenta hasta tres veces la transacción más externa ante un bloqueo mutuo (error 1205). En la corrida final, 0 errores y ningún bloqueo registrado. |
| 3 | La prueba de punta a punta R-044 fallaba porque la fecha a 45 días caía en un día de la carga anual del calendario. | La prueba elige días libres. |

Además, la medición se hace en modo producción. En modo desarrollo, la barra de depuración y el registro detallado agregan tiempo y no representan el uso real.

## Trazabilidad

| Requisito | Casos | Evidencia |
|---|---|---|
| RNF-03 | RP-01, RP-02, RP-03 | Carga mixta, `resumen.md` e informe HTML de JMeter |
| RNF-06 | RP-06a, RP-06b | `concurrencia.txt` y `concurrencia.jtl` |
| RNF-07 | RP-05 | Carga mixta |
| RNF-10 | RP-07 | `procesos.txt` |
