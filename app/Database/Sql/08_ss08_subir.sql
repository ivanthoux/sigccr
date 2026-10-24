/* SS-08 Campañas crediticias: generado desde la especificación del modelo de datos (4.3) */
GO
-- campanas: Campañas crediticias; como máximo una activa (RN-66), garantizado por un índice único filtrado.
CREATE TABLE dbo.campanas (
    id INT IDENTITY(1,1) NOT NULL,
    numero INT NOT NULL CONSTRAINT DF_campanas_numero DEFAULT (NEXT VALUE FOR seq_campanas),
    proyeccion_fondos_id INT NOT NULL,
    version_condiciones_id INT NOT NULL,
    version_parametros_id INT NOT NULL,
    activacion DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_campanas_activacion DEFAULT (SYSDATETIMEOFFSET()),
    vence DATETIMEOFFSET(3) NOT NULL,
    fecha_cierre DATETIMEOFFSET(3) NULL,
    causa_cierre VARCHAR(15) NULL,
    capital_asignado DECIMAL(19,2) NOT NULL,
    capital_comprometido DECIMAL(19,2) NOT NULL CONSTRAINT DF_campanas_capital_comprometido DEFAULT (0),
    estado VARCHAR(10) NOT NULL CONSTRAINT DF_campanas_estado DEFAULT ('Activa'),
    version_fila INT NOT NULL CONSTRAINT DF_campanas_version_fila DEFAULT (1),
    CONSTRAINT PK_campanas PRIMARY KEY (id),
    CONSTRAINT UQ_campanas_numero UNIQUE (numero),
    CONSTRAINT CK_campanas_causa_cierre CHECK (causa_cierre IS NULL OR causa_cierre IN ('Vigencia', 'Cupo', 'Disponibilidad')),
    CONSTRAINT CK_campanas_estado CHECK (estado IN ('Activa', 'Finalizada')),
    CONSTRAINT CK_campanas_capital CHECK (capital_asignado > 0 AND capital_comprometido BETWEEN 0 AND capital_asignado),
    CONSTRAINT CK_campanas_cierre CHECK ((estado = 'Activa' AND fecha_cierre IS NULL) OR (estado = 'Finalizada' AND fecha_cierre IS NOT NULL AND causa_cierre IS NOT NULL))
);
GO
-- ofertas: Propuestas de financiación dirigidas a un cliente dentro de una campaña (RN-42).
CREATE TABLE dbo.ofertas (
    id INT IDENTITY(1,1) NOT NULL,
    campana_id INT NOT NULL,
    cliente_id INT NOT NULL,
    importe_maximo DECIMAL(19,2) NOT NULL,
    cantidad_cuotas SMALLINT NOT NULL,
    tna DECIMAL(18,12) NOT NULL,
    tea DECIMAL(18,12) NOT NULL,
    cftea DECIMAL(18,12) NOT NULL,
    vence DATETIMEOFFSET(3) NOT NULL,
    estado VARCHAR(10) NOT NULL CONSTRAINT DF_ofertas_estado DEFAULT ('Vigente'),
    fecha_respuesta DATETIMEOFFSET(3) NULL,
    solicitud_id INT NULL,
    version_fila INT NOT NULL CONSTRAINT DF_ofertas_version_fila DEFAULT (1),
    CONSTRAINT PK_ofertas PRIMARY KEY (id),
    CONSTRAINT UQ_ofertas_cliente UNIQUE (campana_id, cliente_id),
    CONSTRAINT CK_ofertas_estado CHECK (estado IN ('Vigente', 'Aceptada', 'Rechazada', 'Vencida')),
    CONSTRAINT CK_ofertas_respuesta CHECK (estado NOT IN ('Aceptada', 'Rechazada') OR fecha_respuesta IS NOT NULL),
    CONSTRAINT CK_ofertas_aceptada CHECK (estado <> 'Aceptada' OR solicitud_id IS NOT NULL)
);
GO
ALTER TABLE dbo.campanas ADD CONSTRAINT FK_campanas_proyeccion_fondos_id FOREIGN KEY (proyeccion_fondos_id) REFERENCES dbo.proyecciones_fondos (id);
ALTER TABLE dbo.campanas ADD CONSTRAINT FK_campanas_version_condiciones_id FOREIGN KEY (version_condiciones_id) REFERENCES dbo.versiones_condiciones (id);
ALTER TABLE dbo.campanas ADD CONSTRAINT FK_campanas_version_parametros_id FOREIGN KEY (version_parametros_id) REFERENCES dbo.versiones_parametros (id);
ALTER TABLE dbo.ofertas ADD CONSTRAINT FK_ofertas_campana_id FOREIGN KEY (campana_id) REFERENCES dbo.campanas (id);
ALTER TABLE dbo.ofertas ADD CONSTRAINT FK_ofertas_cliente_id FOREIGN KEY (cliente_id) REFERENCES dbo.clientes (id);
ALTER TABLE dbo.ofertas ADD CONSTRAINT FK_ofertas_solicitud_id FOREIGN KEY (solicitud_id) REFERENCES dbo.solicitudes_credito (id);
CREATE UNIQUE INDEX UQ_campanas_activa ON dbo.campanas (estado) WHERE estado = 'Activa';
CREATE INDEX IX_campanas_proyeccion_fondos_id ON dbo.campanas (proyeccion_fondos_id);
CREATE INDEX IX_campanas_version_condiciones_id ON dbo.campanas (version_condiciones_id);
CREATE INDEX IX_campanas_version_parametros_id ON dbo.campanas (version_parametros_id);
CREATE UNIQUE INDEX UQ_ofertas_solicitud ON dbo.ofertas (solicitud_id) WHERE solicitud_id IS NOT NULL;
CREATE INDEX IX_ofertas_cliente ON dbo.ofertas (cliente_id, estado);
