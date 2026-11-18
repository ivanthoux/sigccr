/* SIG-CCR - Reversión de la migración 34 (se pierden las versiones del modelo de contrato). */
DROP INDEX IX_contratos_prestamo_version_contrato_id ON dbo.contratos_prestamo;
ALTER TABLE dbo.contratos_prestamo DROP CONSTRAINT FK_contratos_prestamo_version_contrato_id;
ALTER TABLE dbo.contratos_prestamo DROP COLUMN version_contrato_id;
GO
DELETE FROM dbo.versiones_parametros WHERE tipo = 'Contrato';
ALTER TABLE dbo.versiones_parametros DROP CONSTRAINT CK_versiones_parametros_tipo;
ALTER TABLE dbo.versiones_parametros ADD CONSTRAINT CK_versiones_parametros_tipo CHECK (tipo IN ('Evaluacion', 'Estructuracion', 'Morosidad', 'Campanas'));
GO
