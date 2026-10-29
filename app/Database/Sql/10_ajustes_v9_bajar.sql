/* Revierte los ajustes de la v9 (D-10, D-11, D-12). Los cobros con más de un medio no pueden volver a la estructura
   anterior sin perder información: la reversión toma el medio de mayor importe de cada cobro. */

/* D-10 */
DROP INDEX IX_simulaciones_simulacion_comparativa_id ON dbo.simulaciones;
ALTER TABLE dbo.simulaciones DROP CONSTRAINT CK_simulaciones_comparativa;
ALTER TABLE dbo.simulaciones DROP CONSTRAINT FK_simulaciones_simulacion_comparativa_id;
GO
ALTER TABLE dbo.simulaciones DROP COLUMN simulacion_comparativa_id, posicion;
DROP TABLE dbo.simulaciones_comparativas;
GO

/* D-12 */
EXEC sp_rename 'dbo.DF_imputaciones_parcial', 'DF_imputaciones_parcial_excepcional', 'OBJECT';
EXEC sp_rename 'dbo.imputaciones.parcial', 'parcial_excepcional', 'COLUMN';
GO

/* D-11 */
DROP INDEX UQ_movimientos_caja_cobro_medio_id ON dbo.movimientos_caja;
ALTER TABLE dbo.movimientos_caja DROP CONSTRAINT CK_movimientos_caja_cobro;
ALTER TABLE dbo.movimientos_caja DROP CONSTRAINT FK_movimientos_caja_cobro_medio_id;
GO
ALTER TABLE dbo.movimientos_caja DROP COLUMN cobro_medio_id;
GO
ALTER TABLE dbo.movimientos_caja ADD CONSTRAINT CK_movimientos_caja_cobro CHECK ((tipo = 'Cobro' AND cobro_id IS NOT NULL) OR (tipo <> 'Cobro' AND cobro_id IS NULL));
CREATE UNIQUE INDEX UQ_movimientos_caja_cobro_id ON dbo.movimientos_caja (cobro_id) WHERE cobro_id IS NOT NULL;
GO

ALTER TABLE dbo.cobros DROP CONSTRAINT CK_cobros_cambio;
ALTER TABLE dbo.cobros DROP CONSTRAINT CK_cobros_canal_datos;
DROP INDEX IX_cobros_jornada ON dbo.cobros;
GO
ALTER TABLE dbo.cobros ADD medio VARCHAR(15) NULL, numero_operacion VARCHAR(60) NULL;
GO
UPDATE c SET medio = m.medio, numero_operacion = m.numero_operacion
FROM dbo.cobros c
CROSS APPLY (SELECT TOP (1) medio, numero_operacion FROM dbo.cobros_medios WHERE cobro_id = c.id ORDER BY importe DESC, id) m;
UPDATE dbo.cobros SET medio = CASE canal WHEN 'Electronico' THEN 'PagoElectronico' ELSE 'Efectivo' END WHERE medio IS NULL;
GO
ALTER TABLE dbo.cobros ALTER COLUMN medio VARCHAR(15) NOT NULL;
ALTER TABLE dbo.cobros DROP CONSTRAINT DF_cobros_cambio;
GO
ALTER TABLE dbo.cobros DROP COLUMN cambio;
GO
ALTER TABLE dbo.cobros ADD CONSTRAINT CK_cobros_medio CHECK (medio IN ('Efectivo', 'TarjetaDebito', 'TarjetaCredito', 'Transferencia', 'PagoElectronico'));
ALTER TABLE dbo.cobros ADD CONSTRAINT CK_cobros_canal_datos CHECK ((canal = 'Ventanilla' AND jornada_caja_id IS NOT NULL AND usuario_id IS NOT NULL AND medio <> 'PagoElectronico') OR (canal = 'Electronico' AND transaccion_pasarela_id IS NOT NULL AND medio = 'PagoElectronico'));
ALTER TABLE dbo.cobros ADD CONSTRAINT CK_cobros_operacion CHECK (medio = 'Efectivo' OR numero_operacion IS NOT NULL OR canal = 'Electronico');
CREATE INDEX IX_cobros_jornada ON dbo.cobros (jornada_caja_id) INCLUDE (medio, importe, estado);
GO

DROP TABLE dbo.cobros_medios;
GO
