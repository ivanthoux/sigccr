/* ==========================================================================
   SIG-CCR - Migración 34: modelo de contrato configurable (CU-106, D-56; RN-15, RN-78).
   Las cláusulas son una versión de parámetros del grupo «Contrato»; cada contrato conserva la versión vigente al
   generarse. Los contratos anteriores quedan sin versión: usan el modelo inicial, el texto con que se generaron.
   ========================================================================== */
ALTER TABLE dbo.versiones_parametros DROP CONSTRAINT CK_versiones_parametros_tipo;
ALTER TABLE dbo.versiones_parametros ADD CONSTRAINT CK_versiones_parametros_tipo CHECK (tipo IN ('Evaluacion', 'Estructuracion', 'Morosidad', 'Campanas', 'Contrato'));
ALTER TABLE dbo.contratos_prestamo ADD version_contrato_id INT NULL;
GO
ALTER TABLE dbo.contratos_prestamo ADD CONSTRAINT FK_contratos_prestamo_version_contrato_id FOREIGN KEY (version_contrato_id) REFERENCES dbo.versiones_parametros (id);
CREATE INDEX IX_contratos_prestamo_version_contrato_id ON dbo.contratos_prestamo (version_contrato_id);
GO
