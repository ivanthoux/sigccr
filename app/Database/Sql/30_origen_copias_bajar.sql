/* SIG-CCR - Reversión de la migración 30 (falla si hay copias previas a una restauración). */
ALTER TABLE dbo.copias_seguridad DROP CONSTRAINT CK_copias_seguridad_origen;
GO
ALTER TABLE dbo.copias_seguridad ALTER COLUMN origen VARCHAR(10) NOT NULL;
GO
ALTER TABLE dbo.copias_seguridad ADD CONSTRAINT CK_copias_seguridad_origen CHECK (origen IN ('Manual', 'Automatica', 'PreviaRestauracion'));
