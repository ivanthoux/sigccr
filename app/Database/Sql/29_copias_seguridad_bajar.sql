/* SIG-CCR - Reversión de la migración 29 (falla si hay copias con los estados u orígenes nuevos). */
DROP INDEX IX_restauraciones_copia_previa_id ON dbo.restauraciones;
ALTER TABLE dbo.restauraciones DROP CONSTRAINT FK_restauraciones_copia_previa_id;
ALTER TABLE dbo.restauraciones DROP CONSTRAINT DF_restauraciones_exitosa;
GO
ALTER TABLE dbo.restauraciones DROP COLUMN exitosa;
ALTER TABLE dbo.restauraciones DROP COLUMN inicio;
ALTER TABLE dbo.restauraciones DROP COLUMN copia_previa_id;
ALTER TABLE dbo.restauraciones DROP COLUMN responsable;
DROP INDEX IX_restauraciones_usuario_id ON dbo.restauraciones;
ALTER TABLE dbo.restauraciones DROP CONSTRAINT FK_restauraciones_usuario_id;
GO
ALTER TABLE dbo.restauraciones ALTER COLUMN usuario_id INT NOT NULL;
GO
ALTER TABLE dbo.restauraciones ADD CONSTRAINT FK_restauraciones_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
CREATE INDEX IX_restauraciones_usuario_id ON dbo.restauraciones (usuario_id);
DROP INDEX IX_copias_seguridad_estado ON dbo.copias_seguridad;
ALTER TABLE dbo.copias_seguridad DROP CONSTRAINT DF_copias_seguridad_incluye_adjuntos;
GO
ALTER TABLE dbo.copias_seguridad DROP COLUMN detalle;
ALTER TABLE dbo.copias_seguridad DROP COLUMN fecha_fin;
ALTER TABLE dbo.copias_seguridad DROP COLUMN incluye_adjuntos;
ALTER TABLE dbo.copias_seguridad DROP COLUMN responsable;
ALTER TABLE dbo.copias_seguridad DROP COLUMN descripcion;
ALTER TABLE dbo.copias_seguridad DROP CONSTRAINT CK_copias_seguridad_estado;
ALTER TABLE dbo.copias_seguridad DROP CONSTRAINT CK_copias_seguridad_origen;
GO
ALTER TABLE dbo.copias_seguridad ADD CONSTRAINT CK_copias_seguridad_origen CHECK (origen IN ('Manual', 'Automatica'));
ALTER TABLE dbo.copias_seguridad ADD CONSTRAINT CK_copias_seguridad_estado CHECK (estado IN ('EnCurso', 'Verificada', 'Fallida', 'Corrupta'));
GO
