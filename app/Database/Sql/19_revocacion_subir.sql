/* ==========================================================================
   Revocación del préstamo (D-31, RN-79, CU-102, arts. 1110 a 1112 del Código Civil y Comercial)
   - creditos.estado admite Revocado y cuotas.estado admite SinEfecto.
   - cobros.tipo admite Revocacion: la restitución del cliente entra como un cobro por transferencia, sin
     imputaciones a cuotas, para que integre la conciliación de la jornada (RN-32).
   - revocaciones: comunicación, importe a restituir y confirmación.
   ========================================================================== */

ALTER TABLE dbo.creditos DROP CONSTRAINT CK_creditos_estado;
ALTER TABLE dbo.creditos ADD CONSTRAINT CK_creditos_estado CHECK (estado IN ('Activo', 'EnMora', 'Cancelado', 'Refinanciado', 'Revocado'));
ALTER TABLE dbo.cuotas DROP CONSTRAINT CK_cuotas_estado;
ALTER TABLE dbo.cuotas ADD CONSTRAINT CK_cuotas_estado CHECK (estado IN ('Pendiente', 'Vencida', 'Pagada', 'Sustituida', 'SinEfecto'));
ALTER TABLE dbo.cobros DROP CONSTRAINT CK_cobros_tipo;
ALTER TABLE dbo.cobros ADD CONSTRAINT CK_cobros_tipo CHECK (tipo IN ('Ordinario', 'CancelacionAnticipada', 'Revocacion'));
GO

CREATE TABLE dbo.revocaciones (
    id INT IDENTITY(1,1) NOT NULL,
    credito_id INT NOT NULL,
    fecha_comunicacion DATE NOT NULL,
    medio_comunicacion NVARCHAR(60) NOT NULL,
    fecha_limite DATE NOT NULL,
    capital DECIMAL(19,2) NOT NULL,
    dias_transcurridos INT NOT NULL,
    interes_devengado DECIMAL(19,2) NOT NULL,
    iva_interes DECIMAL(19,2) NOT NULL,
    pagado_previo DECIMAL(19,2) NOT NULL,
    importe_restituir DECIMAL(19,2) NOT NULL,
    saldo_a_favor DECIMAL(19,2) NOT NULL CONSTRAINT DF_revocaciones_saldo_a_favor DEFAULT (0),
    estado VARCHAR(25) NOT NULL CONSTRAINT DF_revocaciones_estado DEFAULT ('PendienteRestitucion'),
    fecha_registro DATETIMEOFFSET(3) NOT NULL,
    usuario_id INT NOT NULL,
    cobro_id INT NULL,
    fecha_confirmacion DATETIMEOFFSET(3) NULL,
    usuario_confirmacion_id INT NULL,
    CONSTRAINT PK_revocaciones PRIMARY KEY (id),
    CONSTRAINT UQ_revocaciones_credito_id UNIQUE (credito_id),
    CONSTRAINT CK_revocaciones_estado CHECK (estado IN ('PendienteRestitucion', 'Confirmada')),
    CONSTRAINT CK_revocaciones_plazo CHECK (fecha_comunicacion <= fecha_limite),
    CONSTRAINT CK_revocaciones_importes CHECK (capital > 0 AND dias_transcurridos >= 0 AND interes_devengado >= 0 AND iva_interes >= 0 AND pagado_previo >= 0 AND importe_restituir >= 0 AND saldo_a_favor >= 0),
    CONSTRAINT CK_revocaciones_confirmacion CHECK ((estado = 'PendienteRestitucion' AND fecha_confirmacion IS NULL AND cobro_id IS NULL) OR (estado = 'Confirmada' AND fecha_confirmacion IS NOT NULL AND usuario_confirmacion_id IS NOT NULL AND (cobro_id IS NOT NULL OR importe_restituir = 0)))
);
GO
ALTER TABLE dbo.revocaciones ADD CONSTRAINT FK_revocaciones_credito_id FOREIGN KEY (credito_id) REFERENCES dbo.creditos (id);
ALTER TABLE dbo.revocaciones ADD CONSTRAINT FK_revocaciones_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.revocaciones ADD CONSTRAINT FK_revocaciones_cobro_id FOREIGN KEY (cobro_id) REFERENCES dbo.cobros (id);
ALTER TABLE dbo.revocaciones ADD CONSTRAINT FK_revocaciones_usuario_confirmacion_id FOREIGN KEY (usuario_confirmacion_id) REFERENCES dbo.usuarios_internos (id);
CREATE INDEX IX_revocaciones_usuario_id ON dbo.revocaciones (usuario_id);
CREATE INDEX IX_revocaciones_cobro_id ON dbo.revocaciones (cobro_id);
CREATE INDEX IX_revocaciones_usuario_confirmacion_id ON dbo.revocaciones (usuario_confirmacion_id);
GO
