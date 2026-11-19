/* ==========================================================================
   SIG-CCR · Consultas de apoyo para la prueba funcional integral (iteraciones 1 a 4)
   Solo para el entorno de prueba. Reemplazar los valores marcados con «…» y ejecutar
   cada bloque por separado desde el cliente SQL habitual (SQL Server Management Studio o
   Azure Data Studio) conectado a localhost, base sigccr.
   ========================================================================== */

-- C.1 Llevar un crédito a mora: adelanta todos sus vencimientos @dias días.
--     Después: docker compose exec app php spark cartera:punitorios
DECLARE @credito INT = …;      -- número del crédito
DECLARE @dias INT = 40;        -- 32 = dentro de la tolerancia (R-101); 8 más = superada (R-102); 40 = mora directa
UPDATE q SET q.vencimiento = DATEADD(day, -@dias, q.vencimiento)
FROM dbo.cuotas q JOIN dbo.creditos c ON c.id = q.credito_id
WHERE c.numero = @credito;
GO

-- C.2 Vencer una orden de pago electrónico. Después: php spark vencimientos:controlar
UPDATE dbo.ordenes_pago_electronico SET vence = DATEADD(minute, -1, SYSDATETIMEOFFSET()) WHERE numero = …;
GO

-- C.3 Vencer una propuesta de refinanciación (todas sus versiones vigentes)
UPDATE dbo.propuestas_refinanciacion SET vence = DATEADD(minute, -1, SYSDATETIMEOFFSET())
WHERE numero = … AND estado IN ('PendienteResolucion', 'AprobadaPendienteAceptacion');
GO

-- C.4 Sacar un crédito del plazo de revocación: recepción del contrato y liquidación 20 días atrás
DECLARE @credito INT = …;
UPDATE k SET k.fecha_recepcion = DATEADD(day, -20, k.fecha_recepcion)
FROM dbo.contratos_prestamo k JOIN dbo.liquidaciones q ON q.solicitud_id = k.solicitud_id JOIN dbo.creditos c ON c.id = q.credito_id
WHERE c.numero = @credito AND k.fecha_recepcion IS NOT NULL;
UPDATE dbo.creditos SET fecha_liquidacion = DATEADD(day, -20, fecha_liquidacion) WHERE numero = @credito;
GO

-- C.5 Vencimientos de R-139. Después: php spark vencimientos:controlar (dos veces)
-- Simulaciones vigentes de un cliente (CUIL sin guiones)
UPDATE s SET s.vence = DATEADD(minute, -1, SYSDATETIMEOFFSET())
FROM dbo.simulaciones s JOIN dbo.clientes cl ON cl.id = s.cliente_id
WHERE cl.cuit_cuil = '20272224030' AND s.estado = 'Vigente';
-- Alternativas de una solicitud
UPDATE a SET a.vence = DATEADD(minute, -1, SYSDATETIMEOFFSET())
FROM dbo.alternativas_financiacion a JOIN dbo.estructuraciones e ON e.id = a.estructuracion_id JOIN dbo.solicitudes_credito s ON s.id = e.solicitud_id
WHERE s.numero = …;
-- Aprobación sin liquidar de una solicitud
UPDATE r SET r.vence_aprobacion = DATEADD(minute, -1, SYSDATETIMEOFFSET())
FROM dbo.resoluciones r JOIN dbo.solicitudes_credito s ON s.id = r.solicitud_id
WHERE s.numero = … AND r.decision = 'Aprobada' AND r.vigente = 1;
-- Autorización de devolución pendiente de ejecución
UPDATE dbo.autorizaciones_devolucion SET vence = DATEADD(minute, -1, SYSDATETIMEOFFSET()) WHERE id = … AND estado = 'PendienteEjecucion';
GO

-- C.6 Créditos, cuotas y saldos de un cliente, para anotar los números de la prueba
DECLARE @cuil CHAR(11) = '27301112330';
SELECT c.numero AS credito, c.estado, c.origen, c.capital, c.fecha_liquidacion
FROM dbo.creditos c JOIN dbo.clientes cl ON cl.id = c.cliente_id WHERE cl.cuit_cuil = @cuil ORDER BY c.numero;
SELECT c.numero AS credito, q.numero AS cuota, q.vencimiento, q.estado, q.capital_pendiente, q.interes_pendiente,
       q.punitorio_pendiente, q.iva_punitorio_pendiente
FROM dbo.cuotas q JOIN dbo.creditos c ON c.id = q.credito_id JOIN dbo.clientes cl ON cl.id = c.cliente_id
WHERE cl.cuit_cuil = @cuil ORDER BY c.numero, q.numero;
SELECT s.id, s.fecha, s.importe_original, s.importe_disponible, s.importe_reservado
FROM dbo.saldos_a_favor s JOIN dbo.clientes cl ON cl.id = s.cliente_id WHERE cl.cuit_cuil = @cuil ORDER BY s.fecha;
GO

-- C.7 Totales para la copia (R-179, R-181) y para el reporte de cobranzas (R-175)
SELECT
  (SELECT COUNT(*) FROM dbo.clientes) AS clientes,
  (SELECT COUNT(*) FROM dbo.creditos) AS creditos,
  (SELECT COUNT(*) FROM dbo.cuotas WHERE estado = 'Pagada') AS cuotas_pagadas,
  (SELECT COUNT(*) FROM dbo.cobros) AS cobros,
  (SELECT COALESCE(SUM(importe), 0) FROM dbo.cobros WHERE estado = 'Confirmado') AS importe_cobros,
  (SELECT COALESCE(SUM(importe_disponible), 0) FROM dbo.saldos_a_favor) AS saldos_a_favor,
  (SELECT COUNT(*) FROM dbo.propuestas_refinanciacion) AS propuestas,
  (SELECT COUNT(*) FROM dbo.ofertas) AS ofertas,
  (SELECT COUNT(*) FROM dbo.movimientos_fondos) AS movimientos_fondos;
-- Cobros confirmados del mes en curso (hora de Argentina)
SELECT COALESCE(SUM(importe), 0) AS total_cobrado_del_mes
FROM dbo.cobros
WHERE estado = 'Confirmado'
  AND CAST(fecha_hora AT TIME ZONE 'Argentina Standard Time' AS date) >= DATEFROMPARTS(YEAR(SYSDATETIMEOFFSET() AT TIME ZONE 'Argentina Standard Time'), MONTH(SYSDATETIMEOFFSET() AT TIME ZONE 'Argentina Standard Time'), 1);
GO
