ALTER TABLE dbo.intentos_envio DROP CONSTRAINT FK_intentos_envio_comunicacion_id;
GO
DROP TRIGGER dbo.TR_registros_auditoria_inmutable;
DROP TABLE dbo.intercambios_externos;
DROP TABLE dbo.ejecuciones_proceso;
DROP TABLE dbo.intentos_envio;
DROP TABLE dbo.comunicaciones;
DROP TABLE dbo.alertas;
DROP TABLE dbo.registros_auditoria;
DROP TABLE dbo.versiones_parametros;
DROP TABLE dbo.tipos_documentacion;
DROP SEQUENCE dbo.seq_solicitudes;
DROP SEQUENCE dbo.seq_cobros;
DROP SEQUENCE dbo.seq_comprobantes;
DROP SEQUENCE dbo.seq_ordenes;
DROP SEQUENCE dbo.seq_creditos;
DROP SEQUENCE dbo.seq_campanas;
DROP SEQUENCE dbo.seq_propuestas;
