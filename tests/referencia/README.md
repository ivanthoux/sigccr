# Casos de prueba de referencia del motor financiero

Valores esperados calculados de forma independiente (`casos_referencia.py`, módulo `decimal` de Python con 40 dígitos y redondeo a la mitad hacia arriba). Resultado completo: `resultados_referencia.json`. Cada caso está implementado en `tests/unit/Finanzas`.

| ID | Regla | Datos | Resultado esperado | Prueba |
|---|---|---|---|---|
| CP-F01 | RN-50, RN-68 | Capital 100.000, TNA 60 %, 6 cuotas, liquidación 10/01/2026, gasto adm. 500, IVA 21 % | Cuota ordinaria 19.701,75; 1.ª cuota: capital 14.701,75, interés 5.000,00, gasto 500,00, IVA 1.155,00, total 21.356,75 | AmortizacionFrancesaTest |
| CP-F02 | RN-51 | Mismo plan | Suma de capitales 100.000,00 exacta; última cuota: capital 18.763,55 (ajuste final); total a pagar 125.664,67 | AmortizacionFrancesaTest |
| CP-F03 | RN-50 | Capital 1.000, tasa 0 %, 3 cuotas | 333,33 · 333,33 · 333,34 | AmortizacionFrancesaTest |
| CP-F04 | RN-25, RN-68 | Capital 100.000, gasto de otorgamiento 2 %, IVA 21 % | Gasto 2.000,00; IVA 420,00; neto 97.580,00 | TasasTest |
| CP-F05 | RN-63 | TNA 60 % | TEA 79,59 % | TasasTest |
| CP-F06 | RN-63 | Plan CP-F01 y neto 97.580,00 | CFTEA 146,33 % | TasasTest |
| CP-F07 | RN-52 | Liquidación 01/01/2026 | Vencimientos 31/01, 28/02, 31/03, 30/04 (ancla 31) | CalendarioCuotasTest |
| CP-F08 | RN-52 | Liquidación 31/12/2027 | 30/01/2028 y 29/02/2028 (bisiesto) | CalendarioCuotasTest |
| CP-F09 | RN-34 | Tasa configurada 0,12 % diaria, tope 50 % de TNA 60 % | Tasa aplicada 0,000821917808 | PunitoriosTest |
| CP-F10 | RN-53 | Capital vencido 15.436,84, vencimiento 09/03/2026, tolerancia 3, pago el 12/03 | 3 días: tramos condicionales, nada exigible | PunitoriosTest |
| CP-F11 | RN-53 | Ídem, pago el 13/03 | 4 días exigibles: tramo diario 12,6878 (IVA 2,6644); total 50,75 + IVA 10,66 | PunitoriosTest |
| CP-F12 | RN-35 | Ídem, pago el 19/03; 0,08 % hasta el 14/03 y 0,05 % desde el 15/03 | 10 días: 100,34 + IVA 21,07 | PunitoriosTest |
| CP-F13 | RN-49 | Aprobación vie 23/10/2026 10:00 | Vence mié 28/10/2026 10:00 | CalendarioHabilTest |
| CP-F14 | RN-49 | Aprobación sáb 24/10/2026 15:00 | Vence jue 29/10/2026 00:00 | CalendarioHabilTest |
| CP-F15 | RN-49 | Aprobación vie 23/10 10:00 con feriado el lun 26/10 | Vence jue 29/10/2026 10:00 | CalendarioHabilTest |
| CP-F16 | CU-87 | Ingresos 500.000, afectación 30 %, obligaciones 50.000 | Capacidad 100.000,00 | CapacidadPagoTest |
| CP-F17 | CU-87 | Capacidad 100.000, TNA 60 %, 12 cuotas, gasto 500, IVA 21 %, paso 1.000 | Capital máximo 805.000 (1.ª cuota 99.881,96); 806.000 supera la capacidad (100.005,28) | CapacidadPagoTest |
| CP-F18 | RN-29 | Cuotas 2 y 3 con punitorios, cobro de 42.304,08 | Imputación: punitorio, interés, gastos, IVA, capital; ambas canceladas | ImputadorTest |
| CP-F19 | RN-29 | Cobro ordinario por 20.000 | Rechazado: no se admiten fracciones | ImputadorTest |
| CP-F20 | RN-56 | Pago excepcional de 6.000 | Cubre punitorio, interés, gasto e IVA de la cuota 2 y 173,05 de capital; la cuota no queda pagada | ImputadorTest |
| CP-F21 | RN-57 | Pago de 42.400 | Excedente 95,92 como saldo a favor | ImputadorTest |
| CP-F22 | RN-67 | Plan CP-F01, cuota 2 vencida con punitorios 120,50 + IVA 25,31, pago 20/03/2026 | Vencidas 21.348,19; capital no vencido 69.861,41; interés en curso 11 días 1.280,79 + IVA 268,97; total 92.759,36 | CancelacionAnticipadaTest |
| CP-F23 | RN-70 | Cuotas 2 a 6 de CP-F01, corte 20/03/2026 | Capital 85.298,25; no capitalizable 5.911,35 | RefinanciacionTest |
| CP-F24 | RN-70 | Ídem + 35,40 de punitorios posteriores al corte; TNA 48 %, 12 cuotas, aplicación 25/03/2026 | Cuota 9.088,71; 1.ª cuota 24/04/2026 total 10.300,78 (no cap. 495,56); última 24/03/2027 total 9.657,75 (no cap. 495,59) | RefinanciacionTest |
| CP-F25 | RN-69 | Situación 1, sin atraso, endeudamiento 15 %, 8 meses | Puntaje 86,50, categoría A, Favorable | ModeloEvaluacionTest |
| CP-F26 | RN-69 | Situación 5 vigente | Desfavorable, cualquiera sea el puntaje | ModeloEvaluacionTest |
| CP-F27 | RN-69 | Sin deudas, sin antecedentes, endeudamiento 25 %, 12 meses | 57,50, categoría C, Requiere análisis gerencial | ModeloEvaluacionTest |
