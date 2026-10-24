ALTER TABLE dbo.ofertas DROP CONSTRAINT FK_ofertas_campana_id;
ALTER TABLE dbo.ofertas DROP CONSTRAINT FK_ofertas_cliente_id;
ALTER TABLE dbo.ofertas DROP CONSTRAINT FK_ofertas_solicitud_id;
ALTER TABLE dbo.campanas DROP CONSTRAINT FK_campanas_proyeccion_fondos_id;
ALTER TABLE dbo.campanas DROP CONSTRAINT FK_campanas_version_condiciones_id;
ALTER TABLE dbo.campanas DROP CONSTRAINT FK_campanas_version_parametros_id;
GO
DROP TABLE dbo.ofertas;
DROP TABLE dbo.campanas;
