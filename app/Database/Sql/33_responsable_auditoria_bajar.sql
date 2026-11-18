/* SIG-CCR - Reversión de la migración 33 (falla si hay responsables de más de 150 caracteres). */
ALTER TABLE dbo.registros_auditoria ALTER COLUMN responsable NVARCHAR(150) NOT NULL;
