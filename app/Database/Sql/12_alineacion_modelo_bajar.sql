DROP INDEX IX_simulaciones_comparativas_version_parametros_id ON dbo.simulaciones_comparativas;
DROP INDEX IX_simulaciones_comparativas_usuario_id ON dbo.simulaciones_comparativas;
DROP INDEX IX_movimientos_caja_cobro_id ON dbo.movimientos_caja;
GO
ALTER TABLE dbo.alertas_vistas ADD CONSTRAINT FK_alertas_vistas_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
GO
