/* ==========================================================================
   SIG-CCR - Migración 36: solicitudes de derechos del titular de los datos (CU-108, D-64; Ley 25.326, arts. 14, 16 y 20; RN-85, RN-86).
   Acceso, rectificación, actualización, supresión y revisión de una valoración automatizada, con el plazo legal de respuesta.
   ========================================================================== */

-- solicitud_id no lleva clave foránea: SS-04 ya depende de SS-02 y la referencia se valida en el servicio.
-- solicitudes_derechos: Pedido de un cliente para ejercer un derecho sobre sus datos personales, recibido por el portal o en la sucursal, con su plazo legal y su respuesta (CU-108, D-64).
CREATE TABLE dbo.solicitudes_derechos (
    id INT IDENTITY(1,1) NOT NULL,
    cliente_id INT NOT NULL,
    tipo VARCHAR(25) NOT NULL,
    detalle NVARCHAR(1000) NOT NULL,
    solicitud_id INT NULL,
    canal VARCHAR(10) NOT NULL,
    estado VARCHAR(10) NOT NULL CONSTRAINT DF_solicitudes_derechos_estado DEFAULT ('Pendiente'),
    vence DATE NOT NULL,
    fecha_resolucion DATETIMEOFFSET(3) NULL,
    respuesta NVARCHAR(1000) NULL,
    usuario_id INT NULL,
    creado_en DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_solicitudes_derechos_creado_en DEFAULT (SYSDATETIMEOFFSET()),
    actualizado_en DATETIMEOFFSET(3) NULL,
    version_fila INT NOT NULL CONSTRAINT DF_solicitudes_derechos_version_fila DEFAULT (1),
    CONSTRAINT PK_solicitudes_derechos PRIMARY KEY (id),
    CONSTRAINT CK_solicitudes_derechos_tipo CHECK (tipo IN ('Acceso', 'Rectificacion', 'Actualizacion', 'Supresion', 'RevisionAutomatizada')),
    CONSTRAINT CK_solicitudes_derechos_canal CHECK (canal IN ('Portal', 'Sucursal')),
    CONSTRAINT CK_solicitudes_derechos_estado CHECK (estado IN ('Pendiente', 'Atendida', 'Denegada')),
    CONSTRAINT CK_solicitudes_derechos_revision CHECK (tipo <> 'RevisionAutomatizada' OR solicitud_id IS NOT NULL),
    CONSTRAINT CK_solicitudes_derechos_respuesta CHECK (estado = 'Pendiente' OR (respuesta IS NOT NULL AND fecha_resolucion IS NOT NULL AND usuario_id IS NOT NULL))
);
GO
ALTER TABLE dbo.solicitudes_derechos ADD CONSTRAINT FK_solicitudes_derechos_cliente_id FOREIGN KEY (cliente_id) REFERENCES dbo.clientes (id);
ALTER TABLE dbo.solicitudes_derechos ADD CONSTRAINT FK_solicitudes_derechos_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
CREATE INDEX IX_solicitudes_derechos_cliente ON dbo.solicitudes_derechos (cliente_id, estado);
CREATE INDEX IX_solicitudes_derechos_estado ON dbo.solicitudes_derechos (estado, vence);
CREATE INDEX IX_solicitudes_derechos_solicitud_id ON dbo.solicitudes_derechos (solicitud_id);
CREATE INDEX IX_solicitudes_derechos_usuario_id ON dbo.solicitudes_derechos (usuario_id);
GO
