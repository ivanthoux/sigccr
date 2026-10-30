ALTER TABLE dbo.asignaciones_caja DROP CONSTRAINT CK_asignaciones_caja_periodo;
GO
ALTER TABLE dbo.asignaciones_caja ADD CONSTRAINT CK_asignaciones_caja_periodo CHECK (hasta IS NULL OR hasta >= desde);
GO
DROP INDEX UQ_movimientos_fondos_movimiento_corregido_id ON dbo.movimientos_fondos;
ALTER TABLE dbo.movimientos_fondos DROP CONSTRAINT CK_movimientos_fondos_motivo;
ALTER TABLE dbo.movimientos_fondos DROP CONSTRAINT FK_movimientos_fondos_movimiento_corregido_id;
GO
ALTER TABLE dbo.movimientos_fondos DROP COLUMN movimiento_corregido_id, motivo;
GO
ALTER TABLE dbo.cajas DROP COLUMN observaciones;
GO
