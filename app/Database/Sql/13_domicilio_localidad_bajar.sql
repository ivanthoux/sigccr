DROP INDEX IX_clientes_localidad_id ON dbo.clientes;
ALTER TABLE dbo.clientes DROP CONSTRAINT FK_clientes_localidad_id;
GO
ALTER TABLE dbo.clientes DROP COLUMN localidad_id, codigo_postal;
GO
DROP TABLE dbo.localidades;
DROP TABLE dbo.provincias;
GO
