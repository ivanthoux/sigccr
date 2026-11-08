/* Revierte 22_devoluciones_subir.sql. */
ALTER TABLE dbo.ejecuciones_devolucion DROP COLUMN detalle, fecha_transferencia, cbu_destino, importe;
GO
DROP INDEX IX_autorizaciones_devolucion_usuario_anulacion_id ON dbo.autorizaciones_devolucion;
ALTER TABLE dbo.autorizaciones_devolucion DROP CONSTRAINT FK_autorizaciones_devolucion_usuario_anulacion_id, CK_autorizaciones_devolucion_anulacion, CK_autorizaciones_devolucion_titularidad;
ALTER TABLE dbo.autorizaciones_devolucion DROP COLUMN usuario_anulacion_id, fecha_anulacion, motivo_anulacion, evidencia_titularidad, entidad_bancaria, medio_solicitud, fecha_solicitud;
GO
