/* SS-11 Mantenimiento y procesos programados: generado desde la especificación del modelo de datos (4.3) */
GO
-- copias_seguridad: Respaldos de la base de datos, cifrados y con código de verificación (RNF-13).
CREATE TABLE dbo.copias_seguridad (
    id INT IDENTITY(1,1) NOT NULL,
    archivo VARCHAR(260) NOT NULL,
    fecha_generacion DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_copias_seguridad_fecha_generacion DEFAULT (SYSDATETIMEOFFSET()),
    tamano_bytes BIGINT NULL,
    origen VARCHAR(10) NOT NULL,
    hash_verificacion CHAR(64) NULL,
    estado VARCHAR(10) NOT NULL CONSTRAINT DF_copias_seguridad_estado DEFAULT ('EnCurso'),
    usuario_id INT NULL,
    CONSTRAINT PK_copias_seguridad PRIMARY KEY (id),
    CONSTRAINT UQ_copias_seguridad_archivo UNIQUE (archivo),
    CONSTRAINT CK_copias_seguridad_origen CHECK (origen IN ('Manual', 'Automatica')),
    CONSTRAINT CK_copias_seguridad_estado CHECK (estado IN ('EnCurso', 'Verificada', 'Fallida', 'Corrupta'))
);
GO
-- restauraciones: Restauraciones de una copia. Se registran al finalizar, en la base ya restaurada, y además en el registro técnico del servidor (RN-45).
CREATE TABLE dbo.restauraciones (
    id INT IDENTITY(1,1) NOT NULL,
    copia_seguridad_id INT NOT NULL,
    fecha DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_restauraciones_fecha DEFAULT (SYSDATETIMEOFFSET()),
    resultado NVARCHAR(300) NOT NULL,
    usuario_id INT NOT NULL,
    CONSTRAINT PK_restauraciones PRIMARY KEY (id)
);
GO
ALTER TABLE dbo.copias_seguridad ADD CONSTRAINT FK_copias_seguridad_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.restauraciones ADD CONSTRAINT FK_restauraciones_copia_seguridad_id FOREIGN KEY (copia_seguridad_id) REFERENCES dbo.copias_seguridad (id);
ALTER TABLE dbo.restauraciones ADD CONSTRAINT FK_restauraciones_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
CREATE INDEX IX_copias_seguridad_usuario_id ON dbo.copias_seguridad (usuario_id);
CREATE INDEX IX_restauraciones_copia_seguridad_id ON dbo.restauraciones (copia_seguridad_id);
CREATE INDEX IX_restauraciones_usuario_id ON dbo.restauraciones (usuario_id);
GO
IF DATABASE_PRINCIPAL_ID('rol_sigccr_app') IS NULL CREATE ROLE rol_sigccr_app;
GRANT SELECT, INSERT, UPDATE ON SCHEMA::dbo TO rol_sigccr_app;
GRANT DELETE ON dbo.roles_permisos TO rol_sigccr_app;
GRANT DELETE ON dbo.dias_no_habiles TO rol_sigccr_app;
DENY UPDATE ON dbo.registros_auditoria TO rol_sigccr_app;
IF USER_ID('sigccr_app') IS NOT NULL ALTER ROLE rol_sigccr_app ADD MEMBER sigccr_app;
