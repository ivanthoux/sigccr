DROP INDEX IX_alertas_objeto ON dbo.alertas;
ALTER TABLE dbo.alertas DROP CONSTRAINT CK_alertas_resolucion;
GO
ALTER TABLE dbo.alertas DROP COLUMN resuelta_por;
DROP TABLE dbo.alertas_vistas;
GO
