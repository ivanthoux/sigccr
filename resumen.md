# Resultados de las pruebas de rendimiento del SIG-CCR

**Resultado general:** cumple los criterios del plan.

## Volumen y entorno

```
CodeIgniter v4.7.5 Command Line Tool - Server Time: 2026-11-24 15:29:08 UTC+00:00

Volumen de rendimiento cargado en 25.2 s: 20000 clientes, 30000 créditos, 300000 cuotas (6642 vencidas).
Seeded: App\Database\Seeds\RendimientoSeeder
Carga del volumen: 26 s
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
| RP-01 Búsqueda de clientes por CUIL | RNF-03 | 313 | 31.6 | 22 | 51 | 84 | 108 | 135 | 0.0 % | Sí |
| RP-01 Búsqueda de clientes por apellido | RNF-03 | 313 | 31.6 | 65 | 123 | 164 | 173 | 198 | 0.0 % | Sí |
| RP-01 Búsqueda de clientes por documento | RNF-03 | 313 | 31.6 | 22 | 57 | 87 | 102 | 159 | 0.0 % | Sí |
| RP-02 Búsqueda del cliente en el cobro | RNF-03 | 468 | 47.2 | 58 | 95 | 125 | 192 | 230 | 0.0 % | Sí |
| RP-03 Deuda del cliente | RNF-03 | 468 | 47.2 | 78 | 175 | 214 | 327 | 978 | 0.0 % | Sí |
| RP-03 Registro del cobro | RNF-03 | 467 | 47.1 | 80 | 143 | 188 | 302 | 396 | 0.0 % | Sí |
| RP-05 Comprobantes del día | RNF-07 | 465 | 46.9 | 28 | 53 | 75 | 98 | 114 | 0.0 % | Sí |
| RP-05 Listado de solicitudes | RNF-07 | 311 | 31.4 | 68 | 106 | 122 | 162 | 180 | 0.0 % | Sí |
| RP-05 Reporte de cartera | Informativo | 43 | 4.3 | 2268 | 2424 | 2482 | 2570 | 2570 | 0.0 % | — |
| RP-05 Reporte de morosidad | Informativo | 43 | 4.3 | 512 | 598 | 616 | 722 | 722 | 0.0 % | — |
| RP-05 Tablero gerencial | Informativo | 44 | 4.4 | 3183 | 3335 | 3434 | 3522 | 3522 | 0.0 % | — |

## Decisiones simultáneas (RP-06, RNF-06)

| Caso | Esperado | Obtenido | Cumple |
|---|---|---|---|
| RP-06a Dos cobros simultáneos de la misma cuota | un solo cobro confirmado | 1 cobro(s) confirmado(s) | Sí |
| RP-06b Dos anulaciones simultáneas del mismo cobro | una sola anulación y un solo saldo a favor | estado Anulado, 1 anulación(es), 1 saldo(s) a favor | Sí |

- RP-06a Cobro simultáneo (RP-06a Cobro simultáneo de la misma cuota 1-1): 83 ms, HTTP 200
- RP-06a Cobro simultáneo (RP-06a Cobro simultáneo de la misma cuota 1-2): 97 ms, HTTP 200
- RP-06b Anulación simultánea (RP-06b Anulación simultánea del mismo cobro 2-1): 59 ms, HTTP 200
- RP-06b Anulación simultánea (RP-06b Anulación simultánea del mismo cobro 2-2): 59 ms, HTTP 200

## Procesos automáticos con el volumen completo (RP-07, RNF-10)

| Proceso | Duración (s) | Código de salida |
|---|---|---|
| cartera:punitorios | 138.2 | 0 |
| fondos:proyectar | 0.3 | 0 |
| vencimientos:controlar | 0.2 | 0 |
| evaluacion:ejecutar | 0.2 | 0 |
