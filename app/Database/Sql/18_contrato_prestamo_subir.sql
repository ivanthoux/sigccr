/* ==========================================================================
   Contrato de préstamo y constancia firmada (D-31, RN-78, CU-103)
   - contratos_prestamo: contrato generado para cada aprobación vigente (resolución). La copia escaneada de la
     constancia firmada se incorpora al legajo y la fecha en que el cliente recibió el contrato inicia el plazo de
     revocación (RN-79). Sin constancia no se liquida (RN-25).
   - Tipo de documento CONTRATO_FIRMADO, de uso interno: inactivo para que no se ofrezca como documentación exigible.
   ========================================================================== */

CREATE TABLE dbo.contratos_prestamo (
    id INT IDENTITY(1,1) NOT NULL,
    solicitud_id INT NOT NULL,
    resolucion_id INT NOT NULL,
    fecha_generacion DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_contratos_prestamo_fecha_generacion DEFAULT (SYSDATETIMEOFFSET()),
    documento_legajo_id INT NULL,
    fecha_recepcion DATE NULL,
    fecha_registro DATETIMEOFFSET(3) NULL,
    usuario_id INT NULL,
    CONSTRAINT PK_contratos_prestamo PRIMARY KEY (id),
    CONSTRAINT UQ_contratos_prestamo_resolucion_id UNIQUE (resolucion_id),
    CONSTRAINT CK_contratos_prestamo_constancia CHECK ((documento_legajo_id IS NULL AND fecha_recepcion IS NULL AND fecha_registro IS NULL AND usuario_id IS NULL) OR (documento_legajo_id IS NOT NULL AND fecha_recepcion IS NOT NULL AND fecha_registro IS NOT NULL AND usuario_id IS NOT NULL))
);
GO
ALTER TABLE dbo.contratos_prestamo ADD CONSTRAINT FK_contratos_prestamo_solicitud_id FOREIGN KEY (solicitud_id) REFERENCES dbo.solicitudes_credito (id);
ALTER TABLE dbo.contratos_prestamo ADD CONSTRAINT FK_contratos_prestamo_resolucion_id FOREIGN KEY (resolucion_id) REFERENCES dbo.resoluciones (id);
ALTER TABLE dbo.contratos_prestamo ADD CONSTRAINT FK_contratos_prestamo_documento_legajo_id FOREIGN KEY (documento_legajo_id) REFERENCES dbo.documentos_legajo (id);
ALTER TABLE dbo.contratos_prestamo ADD CONSTRAINT FK_contratos_prestamo_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
CREATE INDEX IX_contratos_prestamo_solicitud_id ON dbo.contratos_prestamo (solicitud_id);
CREATE INDEX IX_contratos_prestamo_documento_legajo_id ON dbo.contratos_prestamo (documento_legajo_id);
CREATE INDEX IX_contratos_prestamo_usuario_id ON dbo.contratos_prestamo (usuario_id);
GO
IF NOT EXISTS (SELECT 1 FROM dbo.tipos_documentacion WHERE codigo = 'CONTRATO_FIRMADO')
    INSERT INTO dbo.tipos_documentacion (codigo, nombre, aplica_a, es_clave, activo) VALUES ('CONTRATO_FIRMADO', 'Constancia firmada del contrato de préstamo', 'Ambos', 0, 0);
GO
