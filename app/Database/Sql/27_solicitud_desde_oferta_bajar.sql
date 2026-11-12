/* SIG-CCR - Reversión de la migración 27 (falla si hay solicitudes originadas en ofertas sin Analista). */
ALTER TABLE dbo.solicitudes_credito DROP CONSTRAINT CK_solicitudes_responsable;
DROP INDEX IX_solicitudes_credito_usuario_id ON dbo.solicitudes_credito;
ALTER TABLE dbo.solicitudes_credito DROP CONSTRAINT FK_solicitudes_credito_usuario_id;
GO
ALTER TABLE dbo.solicitudes_credito ALTER COLUMN usuario_id INT NOT NULL;
GO
ALTER TABLE dbo.solicitudes_credito ADD CONSTRAINT FK_solicitudes_credito_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
CREATE INDEX IX_solicitudes_credito_usuario_id ON dbo.solicitudes_credito (usuario_id);
GO
