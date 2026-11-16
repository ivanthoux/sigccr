/* ==========================================================================
   SIG-CCR - Migración 26: portal de autogestión (iteración 4, SS-07; D-42).
   Pre-registro de una persona que todavía no es cliente (CU-104): sus datos y la verificación de su correo, hasta
   que el Analista completa el alta con CU-11. El cambio de correo por autogestión (CU-75) y las preferencias de
   comunicación (CU-98) usan las tablas existentes (autorizaciones_temporales con propósito CambioCorreo y
   consentimientos con medio Portal).
   ========================================================================== */

-- pre_registros: Datos que una persona que todavía no es cliente registra en el portal para que el Analista complete su alta (CU-104, D-42). No crea un cliente ni habilita el portal.
CREATE TABLE dbo.pre_registros (
    id INT IDENTITY(1,1) NOT NULL,
    tipo_documento VARCHAR(10) NOT NULL,
    nro_documento VARCHAR(15) NOT NULL,
    cuit_cuil CHAR(11) NOT NULL,
    nombre NVARCHAR(80) NOT NULL,
    apellido NVARCHAR(80) NOT NULL,
    correo NVARCHAR(254) NOT NULL,
    telefono VARCHAR(30) NULL,
    hash_codigo CHAR(64) NULL,
    vence DATETIMEOFFSET(3) NOT NULL,
    fecha_verificacion DATETIMEOFFSET(3) NULL,
    fecha_politica DATETIMEOFFSET(3) NOT NULL,
    estado VARCHAR(25) NOT NULL CONSTRAINT DF_pre_registros_estado DEFAULT ('PendienteVerificacion'),
    cliente_id INT NULL,
    usuario_id INT NULL,
    motivo_descarte NVARCHAR(500) NULL,
    ip_origen VARCHAR(45) NULL,
    creado_en DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_pre_registros_creado_en DEFAULT (SYSDATETIMEOFFSET()),
    actualizado_en DATETIMEOFFSET(3) NULL,
    version_fila INT NOT NULL CONSTRAINT DF_pre_registros_version_fila DEFAULT (1),
    CONSTRAINT PK_pre_registros PRIMARY KEY (id),
    CONSTRAINT CK_pre_registros_tipo_documento CHECK (tipo_documento IN ('DNI', 'LC', 'LE', 'Pasaporte')),
    CONSTRAINT CK_pre_registros_estado CHECK (estado IN ('PendienteVerificacion', 'PendienteValidacion', 'Completado', 'Descartado')),
    CONSTRAINT CK_pre_registros_completado CHECK (estado <> 'Completado' OR cliente_id IS NOT NULL),
    CONSTRAINT CK_pre_registros_descarte CHECK (estado <> 'Descartado' OR motivo_descarte IS NOT NULL)
);
GO
ALTER TABLE dbo.pre_registros ADD CONSTRAINT FK_pre_registros_cliente_id FOREIGN KEY (cliente_id) REFERENCES dbo.clientes (id);
ALTER TABLE dbo.pre_registros ADD CONSTRAINT FK_pre_registros_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
CREATE UNIQUE INDEX UQ_pre_registros_hash_codigo ON dbo.pre_registros (hash_codigo) WHERE hash_codigo IS NOT NULL;
CREATE INDEX IX_pre_registros_documento ON dbo.pre_registros (tipo_documento, nro_documento) INCLUDE (estado);
CREATE INDEX IX_pre_registros_estado ON dbo.pre_registros (estado, creado_en);
CREATE INDEX IX_pre_registros_cliente_id ON dbo.pre_registros (cliente_id);
CREATE INDEX IX_pre_registros_usuario_id ON dbo.pre_registros (usuario_id);
GO
