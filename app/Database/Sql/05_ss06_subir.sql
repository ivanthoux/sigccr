/* SS-06 Cartera, morosidad y refinanciaciones: generado desde la especificación del modelo de datos (4.3) */
GO
-- creditos: Créditos activos, originados en una liquidación o en una refinanciación aplicada.
CREATE TABLE dbo.creditos (
    id INT IDENTITY(1,1) NOT NULL,
    numero INT NOT NULL CONSTRAINT DF_creditos_numero DEFAULT (NEXT VALUE FOR seq_creditos),
    cliente_id INT NOT NULL,
    version_condiciones_id INT NOT NULL,
    propuesta_origen_id INT NULL,
    version_morosidad_id INT NOT NULL,
    origen VARCHAR(15) NOT NULL,
    capital DECIMAL(19,2) NOT NULL,
    tna DECIMAL(18,12) NOT NULL,
    cantidad_cuotas SMALLINT NOT NULL,
    fecha_liquidacion DATETIMEOFFSET(3) NOT NULL,
    tolerancia_dias TINYINT NOT NULL,
    estado VARCHAR(15) NOT NULL CONSTRAINT DF_creditos_estado DEFAULT ('Activo'),
    version_fila INT NOT NULL CONSTRAINT DF_creditos_version_fila DEFAULT (1),
    CONSTRAINT PK_creditos PRIMARY KEY (id),
    CONSTRAINT UQ_creditos_numero UNIQUE (numero),
    CONSTRAINT CK_creditos_origen CHECK (origen IN ('Liquidacion', 'Refinanciacion')),
    CONSTRAINT CK_creditos_estado CHECK (estado IN ('Activo', 'EnMora', 'Cancelado', 'Refinanciado')),
    CONSTRAINT CK_creditos_origen_propuesta CHECK ((origen = 'Liquidacion' AND propuesta_origen_id IS NULL) OR (origen = 'Refinanciacion' AND propuesta_origen_id IS NOT NULL)),
    CONSTRAINT CK_creditos_valores CHECK (capital > 0 AND tna >= 0 AND cantidad_cuotas > 0)
);
GO
-- cuotas: Obligaciones del plan con sus conceptos discriminados (RN-50 a RN-52). Los saldos pendientes por concepto se actualizan en la misma transacción que las imputaciones y los punitorios.
CREATE TABLE dbo.cuotas (
    id BIGINT IDENTITY(1,1) NOT NULL,
    credito_id INT NOT NULL,
    numero SMALLINT NOT NULL,
    vencimiento DATE NOT NULL,
    capital DECIMAL(19,2) NOT NULL,
    interes DECIMAL(19,2) NOT NULL,
    gastos DECIMAL(19,2) NOT NULL,
    iva DECIMAL(19,2) NOT NULL,
    concepto_no_capitalizable DECIMAL(19,2) NOT NULL CONSTRAINT DF_cuotas_concepto_no_capitalizable DEFAULT (0),
    capital_pendiente DECIMAL(19,2) NOT NULL,
    interes_pendiente DECIMAL(19,2) NOT NULL,
    gastos_pendiente DECIMAL(19,2) NOT NULL,
    iva_pendiente DECIMAL(19,2) NOT NULL,
    no_capitalizable_pendiente DECIMAL(19,2) NOT NULL CONSTRAINT DF_cuotas_no_capitalizable_pendiente DEFAULT (0),
    punitorio_pendiente DECIMAL(19,4) NOT NULL CONSTRAINT DF_cuotas_punitorio_pendiente DEFAULT (0),
    iva_punitorio_pendiente DECIMAL(19,4) NOT NULL CONSTRAINT DF_cuotas_iva_punitorio_pendiente DEFAULT (0),
    estado VARCHAR(10) NOT NULL CONSTRAINT DF_cuotas_estado DEFAULT ('Pendiente'),
    version_fila INT NOT NULL CONSTRAINT DF_cuotas_version_fila DEFAULT (1),
    CONSTRAINT PK_cuotas PRIMARY KEY (id),
    CONSTRAINT UQ_cuotas_numero UNIQUE (credito_id, numero),
    CONSTRAINT CK_cuotas_estado CHECK (estado IN ('Pendiente', 'Vencida', 'Pagada', 'Sustituida')),
    CONSTRAINT CK_cuotas_no_negativos CHECK (capital >= 0 AND interes >= 0 AND gastos >= 0 AND iva >= 0 AND concepto_no_capitalizable >= 0),
    CONSTRAINT CK_cuotas_pendientes CHECK (capital_pendiente BETWEEN 0 AND capital AND interes_pendiente BETWEEN 0 AND interes AND gastos_pendiente BETWEEN 0 AND gastos AND iva_pendiente BETWEEN 0 AND iva AND no_capitalizable_pendiente BETWEEN 0 AND concepto_no_capitalizable AND punitorio_pendiente >= 0 AND iva_punitorio_pendiente >= 0)
);
GO
-- tramos_punitorios: Interés punitorio de un día sobre el capital vencido de una cuota. La clave única (cuota, fecha) hace idempotente el proceso diario (RN-34, RN-35, RNF-10).
CREATE TABLE dbo.tramos_punitorios (
    id BIGINT IDENTITY(1,1) NOT NULL,
    cuota_id BIGINT NOT NULL,
    fecha DATE NOT NULL,
    tasa_diaria DECIMAL(18,12) NOT NULL,
    capital_base DECIMAL(19,4) NOT NULL,
    importe DECIMAL(19,4) NOT NULL,
    iva DECIMAL(19,4) NOT NULL,
    exigible BIT NOT NULL CONSTRAINT DF_tramos_punitorios_exigible DEFAULT (0),
    version_parametros_id INT NOT NULL,
    CONSTRAINT PK_tramos_punitorios PRIMARY KEY (id),
    CONSTRAINT UQ_tramos_punitorios_dia UNIQUE (cuota_id, fecha),
    CONSTRAINT CK_tramos_importes CHECK (capital_base > 0 AND importe >= 0 AND iva >= 0 AND tasa_diaria >= 0)
);
GO
-- condonaciones: Reducciones autorizadas de punitorios pendientes (RN-38).
CREATE TABLE dbo.condonaciones (
    id INT IDENTITY(1,1) NOT NULL,
    fecha DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_condonaciones_fecha DEFAULT (SYSDATETIMEOFFSET()),
    importe DECIMAL(19,2) NOT NULL,
    fundamento NVARCHAR(1000) NOT NULL,
    usuario_id INT NOT NULL,
    CONSTRAINT PK_condonaciones PRIMARY KEY (id),
    CONSTRAINT CK_condonaciones_importe CHECK (importe > 0)
);
GO
-- condonaciones_cuotas: Distribución del importe condonado entre las cuotas alcanzadas.
CREATE TABLE dbo.condonaciones_cuotas (
    condonacion_id INT NOT NULL,
    cuota_id BIGINT NOT NULL,
    importe DECIMAL(19,4) NOT NULL,
    CONSTRAINT PK_condonaciones_cuotas PRIMARY KEY (condonacion_id, cuota_id),
    CONSTRAINT CK_condonaciones_cuotas_importe CHECK (importe > 0)
);
GO
-- simulaciones_refinanciacion: Cálculos preliminares de un nuevo plan; no modifican la deuda (RN-36).
CREATE TABLE dbo.simulaciones_refinanciacion (
    id INT IDENTITY(1,1) NOT NULL,
    cliente_id INT NOT NULL,
    fecha DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_simulaciones_refinanciacion_fecha DEFAULT (SYSDATETIMEOFFSET()),
    deuda_consolidada DECIMAL(19,2) NOT NULL,
    cantidad_cuotas SMALLINT NOT NULL,
    tasa DECIMAL(18,12) NOT NULL,
    valor_cuota DECIMAL(19,2) NOT NULL,
    vence DATETIMEOFFSET(3) NOT NULL,
    usuario_id INT NOT NULL,
    CONSTRAINT PK_simulaciones_refinanciacion PRIMARY KEY (id)
);
GO
-- propuestas_refinanciacion: Planes presentados para resolución gerencial y aceptación del cliente. Cada corrección es una nueva versión vinculada con la anterior (RN-36).
CREATE TABLE dbo.propuestas_refinanciacion (
    id INT IDENTITY(1,1) NOT NULL,
    numero INT NOT NULL,
    version SMALLINT NOT NULL CONSTRAINT DF_propuestas_refinanciacion_version DEFAULT (1),
    cliente_id INT NOT NULL,
    propuesta_anterior_id INT NULL,
    simulacion_id INT NULL,
    version_parametros_id INT NOT NULL,
    fecha_registro DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_propuestas_refinanciacion_fecha_registro DEFAULT (SYSDATETIMEOFFSET()),
    vence DATETIMEOFFSET(3) NOT NULL,
    fecha_corte DATE NOT NULL,
    capital_refinanciado DECIMAL(19,2) NOT NULL,
    concepto_no_capitalizable DECIMAL(19,2) NOT NULL,
    cantidad_cuotas SMALLINT NOT NULL,
    tasa DECIMAL(18,12) NOT NULL,
    estado VARCHAR(30) NOT NULL CONSTRAINT DF_propuestas_refinanciacion_estado DEFAULT ('PendienteResolucion'),
    motivo_cancelacion NVARCHAR(500) NULL,
    usuario_id INT NOT NULL,
    version_fila INT NOT NULL CONSTRAINT DF_propuestas_refinanciacion_version_fila DEFAULT (1),
    CONSTRAINT PK_propuestas_refinanciacion PRIMARY KEY (id),
    CONSTRAINT UQ_propuestas_numero_version UNIQUE (numero, version),
    CONSTRAINT CK_propuestas_refinanciacion_estado CHECK (estado IN ('PendienteResolucion', 'AprobadaPendienteAceptacion', 'Observada', 'Rechazada', 'Aplicada', 'Vencida', 'Cancelada')),
    CONSTRAINT CK_propuestas_valores CHECK (capital_refinanciado > 0 AND concepto_no_capitalizable >= 0 AND cantidad_cuotas > 0 AND tasa >= 0),
    CONSTRAINT CK_propuestas_cancelacion CHECK (estado <> 'Cancelada' OR motivo_cancelacion IS NOT NULL)
);
GO
-- propuestas_cuotas: Cuotas comprendidas y composición de la deuda a la fecha de corte, para revalidarla antes de resolver y aplicar (RN-36).
CREATE TABLE dbo.propuestas_cuotas (
    propuesta_id INT NOT NULL,
    cuota_id BIGINT NOT NULL,
    capital_pendiente DECIMAL(19,2) NOT NULL,
    no_capitalizable DECIMAL(19,2) NOT NULL,
    CONSTRAINT PK_propuestas_cuotas PRIMARY KEY (propuesta_id, cuota_id)
);
GO
-- resoluciones_propuesta: Decisiones gerenciales sobre una propuesta (CU-41).
CREATE TABLE dbo.resoluciones_propuesta (
    id INT IDENTITY(1,1) NOT NULL,
    propuesta_id INT NOT NULL,
    decision VARCHAR(10) NOT NULL,
    fundamento NVARCHAR(1000) NOT NULL,
    fecha DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_resoluciones_propuesta_fecha DEFAULT (SYSDATETIMEOFFSET()),
    usuario_id INT NOT NULL,
    CONSTRAINT PK_resoluciones_propuesta PRIMARY KEY (id),
    CONSTRAINT CK_resoluciones_propuesta_decision CHECK (decision IN ('Aprobada', 'Rechazada', 'Observada'))
);
GO
-- aceptaciones_refinanciacion: Constancia de aceptación del cliente que permite aplicar la propuesta (RN-37).
CREATE TABLE dbo.aceptaciones_refinanciacion (
    id INT IDENTITY(1,1) NOT NULL,
    propuesta_id INT NOT NULL,
    fecha DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_aceptaciones_refinanciacion_fecha DEFAULT (SYSDATETIMEOFFSET()),
    medio NVARCHAR(100) NOT NULL,
    observaciones NVARCHAR(500) NULL,
    usuario_id INT NOT NULL,
    CONSTRAINT PK_aceptaciones_refinanciacion PRIMARY KEY (id),
    CONSTRAINT UQ_aceptaciones_refinanciacion_propuesta_id UNIQUE (propuesta_id)
);
GO
ALTER TABLE dbo.creditos ADD CONSTRAINT FK_creditos_cliente_id FOREIGN KEY (cliente_id) REFERENCES dbo.clientes (id);
ALTER TABLE dbo.creditos ADD CONSTRAINT FK_creditos_version_condiciones_id FOREIGN KEY (version_condiciones_id) REFERENCES dbo.versiones_condiciones (id);
ALTER TABLE dbo.creditos ADD CONSTRAINT FK_creditos_propuesta_origen_id FOREIGN KEY (propuesta_origen_id) REFERENCES dbo.propuestas_refinanciacion (id);
ALTER TABLE dbo.creditos ADD CONSTRAINT FK_creditos_version_morosidad_id FOREIGN KEY (version_morosidad_id) REFERENCES dbo.versiones_parametros (id);
ALTER TABLE dbo.cuotas ADD CONSTRAINT FK_cuotas_credito_id FOREIGN KEY (credito_id) REFERENCES dbo.creditos (id);
ALTER TABLE dbo.tramos_punitorios ADD CONSTRAINT FK_tramos_punitorios_cuota_id FOREIGN KEY (cuota_id) REFERENCES dbo.cuotas (id);
ALTER TABLE dbo.tramos_punitorios ADD CONSTRAINT FK_tramos_punitorios_version_parametros_id FOREIGN KEY (version_parametros_id) REFERENCES dbo.versiones_parametros (id);
ALTER TABLE dbo.condonaciones ADD CONSTRAINT FK_condonaciones_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.condonaciones_cuotas ADD CONSTRAINT FK_condonaciones_cuotas_condonacion_id FOREIGN KEY (condonacion_id) REFERENCES dbo.condonaciones (id);
ALTER TABLE dbo.condonaciones_cuotas ADD CONSTRAINT FK_condonaciones_cuotas_cuota_id FOREIGN KEY (cuota_id) REFERENCES dbo.cuotas (id);
ALTER TABLE dbo.simulaciones_refinanciacion ADD CONSTRAINT FK_simulaciones_refinanciacion_cliente_id FOREIGN KEY (cliente_id) REFERENCES dbo.clientes (id);
ALTER TABLE dbo.simulaciones_refinanciacion ADD CONSTRAINT FK_simulaciones_refinanciacion_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.propuestas_refinanciacion ADD CONSTRAINT FK_propuestas_refinanciacion_cliente_id FOREIGN KEY (cliente_id) REFERENCES dbo.clientes (id);
ALTER TABLE dbo.propuestas_refinanciacion ADD CONSTRAINT FK_propuestas_refinanciacion_propuesta_anterior_id FOREIGN KEY (propuesta_anterior_id) REFERENCES dbo.propuestas_refinanciacion (id);
ALTER TABLE dbo.propuestas_refinanciacion ADD CONSTRAINT FK_propuestas_refinanciacion_simulacion_id FOREIGN KEY (simulacion_id) REFERENCES dbo.simulaciones_refinanciacion (id);
ALTER TABLE dbo.propuestas_refinanciacion ADD CONSTRAINT FK_propuestas_refinanciacion_version_parametros_id FOREIGN KEY (version_parametros_id) REFERENCES dbo.versiones_parametros (id);
ALTER TABLE dbo.propuestas_refinanciacion ADD CONSTRAINT FK_propuestas_refinanciacion_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.propuestas_cuotas ADD CONSTRAINT FK_propuestas_cuotas_propuesta_id FOREIGN KEY (propuesta_id) REFERENCES dbo.propuestas_refinanciacion (id);
ALTER TABLE dbo.propuestas_cuotas ADD CONSTRAINT FK_propuestas_cuotas_cuota_id FOREIGN KEY (cuota_id) REFERENCES dbo.cuotas (id);
ALTER TABLE dbo.resoluciones_propuesta ADD CONSTRAINT FK_resoluciones_propuesta_propuesta_id FOREIGN KEY (propuesta_id) REFERENCES dbo.propuestas_refinanciacion (id);
ALTER TABLE dbo.resoluciones_propuesta ADD CONSTRAINT FK_resoluciones_propuesta_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.aceptaciones_refinanciacion ADD CONSTRAINT FK_aceptaciones_refinanciacion_propuesta_id FOREIGN KEY (propuesta_id) REFERENCES dbo.propuestas_refinanciacion (id);
ALTER TABLE dbo.aceptaciones_refinanciacion ADD CONSTRAINT FK_aceptaciones_refinanciacion_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
CREATE UNIQUE INDEX UQ_creditos_propuesta_origen_id ON dbo.creditos (propuesta_origen_id) WHERE propuesta_origen_id IS NOT NULL;
CREATE INDEX IX_creditos_cliente ON dbo.creditos (cliente_id, estado);
CREATE INDEX IX_creditos_version_condiciones_id ON dbo.creditos (version_condiciones_id);
CREATE INDEX IX_creditos_version_morosidad_id ON dbo.creditos (version_morosidad_id);
CREATE INDEX IX_cuotas_vencimiento ON dbo.cuotas (estado, vencimiento) INCLUDE (credito_id) WHERE estado IN ('Pendiente', 'Vencida');
CREATE INDEX IX_cuotas_credito_estado ON dbo.cuotas (credito_id, estado);
CREATE INDEX IX_tramos_punitorios_version_parametros_id ON dbo.tramos_punitorios (version_parametros_id);
CREATE INDEX IX_condonaciones_usuario_id ON dbo.condonaciones (usuario_id);
CREATE INDEX IX_condonaciones_cuotas_cuota_id ON dbo.condonaciones_cuotas (cuota_id);
CREATE INDEX IX_simulaciones_refinanciacion_cliente_id ON dbo.simulaciones_refinanciacion (cliente_id);
CREATE INDEX IX_simulaciones_refinanciacion_usuario_id ON dbo.simulaciones_refinanciacion (usuario_id);
CREATE UNIQUE INDEX UQ_propuestas_refinanciacion_propuesta_anterior_id ON dbo.propuestas_refinanciacion (propuesta_anterior_id) WHERE propuesta_anterior_id IS NOT NULL;
CREATE INDEX IX_propuestas_cliente ON dbo.propuestas_refinanciacion (cliente_id, estado);
CREATE INDEX IX_propuestas_vence ON dbo.propuestas_refinanciacion (vence) WHERE estado IN ('PendienteResolucion', 'AprobadaPendienteAceptacion');
CREATE INDEX IX_propuestas_refinanciacion_simulacion_id ON dbo.propuestas_refinanciacion (simulacion_id);
CREATE INDEX IX_propuestas_refinanciacion_version_parametros_id ON dbo.propuestas_refinanciacion (version_parametros_id);
CREATE INDEX IX_propuestas_refinanciacion_usuario_id ON dbo.propuestas_refinanciacion (usuario_id);
CREATE INDEX IX_propuestas_cuotas_cuota_id ON dbo.propuestas_cuotas (cuota_id);
CREATE INDEX IX_resoluciones_propuesta_propuesta_id ON dbo.resoluciones_propuesta (propuesta_id);
CREATE INDEX IX_resoluciones_propuesta_usuario_id ON dbo.resoluciones_propuesta (usuario_id);
CREATE INDEX IX_aceptaciones_refinanciacion_usuario_id ON dbo.aceptaciones_refinanciacion (usuario_id);
