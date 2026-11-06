/* Revierte 17_identificacion_uif_subir.sql. */
ALTER TABLE dbo.clientes DROP CONSTRAINT CK_clientes_declaracion_pep;
GO
ALTER TABLE dbo.clientes DROP COLUMN persona_expuesta, fecha_declaracion_pep;
ALTER TABLE dbo.personas_humanas DROP COLUMN nacionalidad;
GO
