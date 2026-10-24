ALTER TABLE dbo.consentimientos DROP CONSTRAINT FK_consentimientos_cliente_id;
ALTER TABLE dbo.documentos_legajo DROP CONSTRAINT FK_documentos_legajo_cliente_id;
ALTER TABLE dbo.documentos_legajo DROP CONSTRAINT FK_documentos_legajo_tipo_documentacion_id;
ALTER TABLE dbo.documentos_legajo DROP CONSTRAINT FK_documentos_legajo_reemplaza_documento_id;
ALTER TABLE dbo.documentos_legajo DROP CONSTRAINT FK_documentos_legajo_usuario_id;
ALTER TABLE dbo.personas_juridicas DROP CONSTRAINT FK_personas_juridicas_cliente;
ALTER TABLE dbo.personas_humanas DROP CONSTRAINT FK_personas_humanas_cliente;
ALTER TABLE dbo.clientes DROP CONSTRAINT FK_clientes_cuenta_cliente_id;
GO
DROP TABLE dbo.consentimientos;
DROP TABLE dbo.documentos_legajo;
DROP TABLE dbo.personas_juridicas;
DROP TABLE dbo.personas_humanas;
DROP TABLE dbo.clientes;
