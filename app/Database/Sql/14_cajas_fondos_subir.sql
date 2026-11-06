/* ==========================================================================
   Cajas y movimientos de fondos (D05, D-21)
   - cajas.observaciones del alta (CU-80).
   - movimientos_fondos: corrección mediante un movimiento inverso vinculado y motivo de la corrección o de la
     anulación de un movimiento programado (CU-85 A2, A3; RN-44).
   ========================================================================== */

ALTER TABLE dbo.cajas ADD observaciones NVARCHAR(300) NULL;
GO
ALTER TABLE dbo.movimientos_fondos ADD movimiento_corregido_id INT NULL, motivo NVARCHAR(300) NULL;
GO
ALTER TABLE dbo.movimientos_fondos ADD CONSTRAINT FK_movimientos_fondos_movimiento_corregido_id FOREIGN KEY (movimiento_corregido_id) REFERENCES dbo.movimientos_fondos (id);
ALTER TABLE dbo.movimientos_fondos ADD CONSTRAINT CK_movimientos_fondos_motivo CHECK ((movimiento_corregido_id IS NULL AND estado <> 'Anulado') OR motivo IS NOT NULL);
CREATE UNIQUE INDEX UQ_movimientos_fondos_movimiento_corregido_id ON dbo.movimientos_fondos (movimiento_corregido_id) WHERE movimiento_corregido_id IS NOT NULL;
GO
-- Una asignación finalizada antes de comenzar queda con un período vacío (hasta = desde − 1)
ALTER TABLE dbo.asignaciones_caja DROP CONSTRAINT CK_asignaciones_caja_periodo;
GO
ALTER TABLE dbo.asignaciones_caja ADD CONSTRAINT CK_asignaciones_caja_periodo CHECK (hasta IS NULL OR hasta >= DATEADD(day, -1, desde));
GO
