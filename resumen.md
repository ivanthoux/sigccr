# Resultados de las pruebas de rendimiento del SIG-CCR

**Resultado general:** cumple los criterios del plan.

## Volumen y entorno

```
CodeIgniter v4.7.5 Command Line Tool - Server Time: 2026-11-19 19:23:27 UTC+00:00

Volumen de rendimiento cargado en 15.9 s: 20000 clientes, 30000 créditos, 300000 cuotas (6642 vencidas).
Seeded: App\Database\Seeds\RendimientoSeeder
Carga del volumen: 17 s
Totales de la base (clientes, créditos, cuotas): 20022,30015,300201

/sigccr_e2e-app-1: 1500000000 nanoCPU, 3221225472 bytes
/sigccr_e2e-db-1: 2000000000 nanoCPU, 4294967296 bytes
/sigccr_e2e-web-1: 500000000 nanoCPU, 1073741824 bytes
CPU del equipo de prueba: 4
CI_ENVIRONMENT = production
```

## Carga mixta (RP-01 a RP-05): 10 usuarios durante 9.9 minutos

Criterio: percentil 95 menor que 2000 ms y errores ≤ 1% en las operaciones de RNF-03 y RNF-07. Tiempos en milisegundos.

| Operación | Requisito | Muestras | Por minuto | p50 | p90 | p95 | p99 | Máximo | Errores | Cumple |
|---|---|---|---|---|---|---|---|---|---|---|
| RP-01 Búsqueda de clientes por CUIL | RNF-03 | 315 | 31.7 | 13 | 26 | 36 | 64 | 80 | 0.0 % | Sí |
| RP-01 Búsqueda de clientes por apellido | RNF-03 | 315 | 31.7 | 39 | 59 | 81 | 127 | 156 | 0.0 % | Sí |
| RP-01 Búsqueda de clientes por documento | RNF-03 | 315 | 31.7 | 14 | 26 | 39 | 79 | 90 | 0.0 % | Sí |
| RP-02 Búsqueda del cliente en el cobro | RNF-03 | 474 | 47.7 | 38 | 59 | 74 | 128 | 136 | 0.0 % | Sí |
| RP-03 Deuda del cliente | RNF-03 | 474 | 47.7 | 45 | 105 | 122 | 205 | 558 | 0.0 % | Sí |
| RP-03 Registro del cobro | RNF-03 | 474 | 47.7 | 50 | 83 | 109 | 182 | 257 | 0.0 % | Sí |
| RP-05 Comprobantes del día | RNF-07 | 473 | 47.6 | 17 | 27 | 38 | 71 | 88 | 0.0 % | Sí |
| RP-05 Listado de solicitudes | RNF-07 | 315 | 31.7 | 35 | 59 | 80 | 116 | 126 | 0.0 % | Sí |
| RP-05 Reporte de cartera | Informativo | 57 | 5.7 | 1187 | 1292 | 1310 | 1397 | 1397 | 0.0 % | — |
| RP-05 Reporte de morosidad | Informativo | 57 | 5.7 | 258 | 308 | 364 | 387 | 387 | 0.0 % | — |
| RP-05 Tablero gerencial | Informativo | 57 | 5.7 | 1627 | 1850 | 1909 | 1961 | 1961 | 0.0 % | — |

## Decisiones simultáneas (RP-06, RNF-06)

| Caso | Esperado | Obtenido | Cumple |
|---|---|---|---|
| RP-06a Dos cobros simultáneos de la misma cuota | un solo cobro confirmado | 1 cobro(s) confirmado(s) | Sí |
| RP-06b Dos anulaciones simultáneas del mismo cobro | una sola anulación y un solo saldo a favor | estado Anulado, 1 anulación(es), 1 saldo(s) a favor | Sí |

- RP-06a Cobro simultáneo (RP-06a Cobro simultáneo de la misma cuota 1-1): 62 ms, HTTP 200
- RP-06a Cobro simultáneo (RP-06a Cobro simultáneo de la misma cuota 1-2): 63 ms, HTTP 200
- RP-06b Anulación simultánea (RP-06b Anulación simultánea del mismo cobro 2-1): 39 ms, HTTP 200
- RP-06b Anulación simultánea (RP-06b Anulación simultánea del mismo cobro 2-2): 38 ms, HTTP 200

## Procesos automáticos con el volumen completo (RP-07, RNF-10)

| Proceso | Duración (s) | Código de salida |
|---|---|---|
| cartera:punitorios | 72.2 | 0 |
| fondos:proyectar | 0.2 | 0 |
| vencimientos:controlar | 0.2 | 0 |
| evaluacion:ejecutar | 0.1 | 0 |
