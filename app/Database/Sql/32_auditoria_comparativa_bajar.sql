/* SIG-CCR - Reversión de la migración 32 (falla si hay posiciones mayores que 255 o motivos de más de 1000 caracteres). */
ALTER TABLE dbo.registros_auditoria ALTER COLUMN motivo NVARCHAR(1000) NULL;
GO
ALTER TABLE dbo.simulaciones DROP CONSTRAINT CK_simulaciones_comparativa;
GO
ALTER TABLE dbo.simulaciones ALTER COLUMN posicion TINYINT NULL;
GO
ALTER TABLE dbo.simulaciones ADD CONSTRAINT CK_simulaciones_comparativa CHECK ((simulacion_comparativa_id IS NULL AND posicion IS NULL) OR (simulacion_comparativa_id IS NOT NULL AND posicion >= 1));
