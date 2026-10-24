IF DATABASE_PRINCIPAL_ID('rol_sigccr_app') IS NOT NULL BEGIN IF USER_ID('sigccr_app') IS NOT NULL ALTER ROLE rol_sigccr_app DROP MEMBER sigccr_app; DROP ROLE rol_sigccr_app; END;
GO
ALTER TABLE dbo.restauraciones DROP CONSTRAINT FK_restauraciones_copia_seguridad_id;
ALTER TABLE dbo.restauraciones DROP CONSTRAINT FK_restauraciones_usuario_id;
ALTER TABLE dbo.copias_seguridad DROP CONSTRAINT FK_copias_seguridad_usuario_id;
GO
DROP TABLE dbo.restauraciones;
DROP TABLE dbo.copias_seguridad;
