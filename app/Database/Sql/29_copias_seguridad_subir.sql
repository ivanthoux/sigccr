/* ==========================================================================
   SIG-CCR - Migración 29: copias de seguridad y restauración (iteración 4, SS-11; D-45).
   El panel solicita la copia (estado Solicitada) y el contenedor de procesos la genera. Se agregan la descripción,
   el responsable, los adjuntos, el fin y la causa de una falla; la copia previa a una restauración tiene su origen
   propio y la depuración por antigüedad deja la copia como Depurada. La restauración conserva el nombre de quien la
   pidió, porque su cuenta puede no existir en la copia restaurada, y la copia previa que la respalda (CU-63 paso 7).
   ========================================================================== */
ALTER TABLE dbo.copias_seguridad DROP CONSTRAINT CK_copias_seguridad_origen;
ALTER TABLE dbo.copias_seguridad DROP CONSTRAINT CK_copias_seguridad_estado;
GO
ALTER TABLE dbo.copias_seguridad ADD CONSTRAINT CK_copias_seguridad_origen CHECK (origen IN ('Manual', 'Automatica', 'PreviaRestauracion'));
ALTER TABLE dbo.copias_seguridad ADD CONSTRAINT CK_copias_seguridad_estado CHECK (estado IN ('Solicitada', 'EnCurso', 'Verificada', 'Fallida', 'Corrupta', 'Depurada'));
ALTER TABLE dbo.copias_seguridad ADD descripcion NVARCHAR(300) NULL;
ALTER TABLE dbo.copias_seguridad ADD responsable NVARCHAR(150) NULL;
ALTER TABLE dbo.copias_seguridad ADD incluye_adjuntos BIT NOT NULL CONSTRAINT DF_copias_seguridad_incluye_adjuntos DEFAULT (0);
ALTER TABLE dbo.copias_seguridad ADD fecha_fin DATETIMEOFFSET(3) NULL;
ALTER TABLE dbo.copias_seguridad ADD detalle NVARCHAR(1000) NULL;
CREATE INDEX IX_copias_seguridad_estado ON dbo.copias_seguridad (estado, fecha_generacion);
GO
DROP INDEX IX_restauraciones_usuario_id ON dbo.restauraciones;
ALTER TABLE dbo.restauraciones DROP CONSTRAINT FK_restauraciones_usuario_id;
GO
ALTER TABLE dbo.restauraciones ALTER COLUMN usuario_id INT NULL;
GO
ALTER TABLE dbo.restauraciones ADD CONSTRAINT FK_restauraciones_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
CREATE INDEX IX_restauraciones_usuario_id ON dbo.restauraciones (usuario_id);
ALTER TABLE dbo.restauraciones ADD responsable NVARCHAR(150) NULL;
ALTER TABLE dbo.restauraciones ADD copia_previa_id INT NULL;
ALTER TABLE dbo.restauraciones ADD inicio DATETIMEOFFSET(3) NULL;
ALTER TABLE dbo.restauraciones ADD exitosa BIT NOT NULL CONSTRAINT DF_restauraciones_exitosa DEFAULT (1);
GO
ALTER TABLE dbo.restauraciones ADD CONSTRAINT FK_restauraciones_copia_previa_id FOREIGN KEY (copia_previa_id) REFERENCES dbo.copias_seguridad (id);
CREATE INDEX IX_restauraciones_copia_previa_id ON dbo.restauraciones (copia_previa_id);
GO
