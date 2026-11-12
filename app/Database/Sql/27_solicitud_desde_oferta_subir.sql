/* ==========================================================================
   SIG-CCR - Migración 27: solicitudes originadas por el cliente al aceptar una oferta (CU-52, D-43).
   La solicitud no tiene un Analista que la registre: usuario_id admite nulo solo con origen Oferta.
   ========================================================================== */
DROP INDEX IX_solicitudes_credito_usuario_id ON dbo.solicitudes_credito;
ALTER TABLE dbo.solicitudes_credito DROP CONSTRAINT FK_solicitudes_credito_usuario_id;
GO
ALTER TABLE dbo.solicitudes_credito ALTER COLUMN usuario_id INT NULL;
GO
ALTER TABLE dbo.solicitudes_credito ADD CONSTRAINT FK_solicitudes_credito_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.solicitudes_credito ADD CONSTRAINT CK_solicitudes_responsable CHECK (origen = 'Oferta' OR usuario_id IS NOT NULL);
CREATE INDEX IX_solicitudes_credito_usuario_id ON dbo.solicitudes_credito (usuario_id);
GO
