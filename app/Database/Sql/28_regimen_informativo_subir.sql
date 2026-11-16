/* ==========================================================================
   SIG-CCR - Migración 28: régimen informativo de deudores (iteración 4, SS-06; D-44).
   Archivo mensual de la Central de Deudores del BCRA y clasificación de cada deudor al cierre del mes (CU-105,
   RN-84). Una rectificación agrega una versión y deja la anterior como Rectificado.
   ========================================================================== */

-- regimenes_informativos: Archivo mensual del régimen informativo de la Central de Deudores del BCRA con la clasificación de los deudores al cierre del mes (CU-105, RN-84, D-44). Una rectificación agrega una versión y conserva la anterior.
CREATE TABLE dbo.regimenes_informativos (
    id INT IDENTITY(1,1) NOT NULL,
    periodo CHAR(7) NOT NULL,
    version SMALLINT NOT NULL CONSTRAINT DF_regimenes_informativos_version DEFAULT (1),
    fecha_cierre DATE NOT NULL,
    fecha_generacion DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_regimenes_informativos_fecha_generacion DEFAULT (SYSDATETIMEOFFSET()),
    cantidad_deudores INT NOT NULL,
    saldo_total DECIMAL(19,2) NOT NULL,
    archivo VARBINARY(MAX) NOT NULL,
    hash_archivo CHAR(64) NOT NULL,
    estado VARCHAR(15) NOT NULL CONSTRAINT DF_regimenes_informativos_estado DEFAULT ('Vigente'),
    usuario_id INT NULL,
    proceso VARCHAR(60) NULL,
    motivo NVARCHAR(500) NULL,
    CONSTRAINT PK_regimenes_informativos PRIMARY KEY (id),
    CONSTRAINT UQ_regimenes_periodo_version UNIQUE (periodo, version),
    CONSTRAINT CK_regimenes_informativos_estado CHECK (estado IN ('Vigente', 'Rectificado')),
    CONSTRAINT CK_regimenes_responsable CHECK (usuario_id IS NOT NULL OR proceso IS NOT NULL),
    CONSTRAINT CK_regimenes_rectificacion CHECK (version = 1 OR motivo IS NOT NULL),
    CONSTRAINT CK_regimenes_totales CHECK (cantidad_deudores >= 0 AND saldo_total >= 0)
);

-- situaciones_deudor: Clasificación de cada deudor con financiaciones vigentes al cierre del período informado (RN-84).
CREATE TABLE dbo.situaciones_deudor (
    id INT IDENTITY(1,1) NOT NULL,
    regimen_informativo_id INT NOT NULL,
    cliente_id INT NOT NULL,
    cuit_cuil CHAR(11) NOT NULL,
    denominacion NVARCHAR(200) NOT NULL,
    dias_atraso INT NOT NULL,
    situacion TINYINT NOT NULL,
    saldo DECIMAL(19,2) NOT NULL,
    creditos SMALLINT NOT NULL,
    CONSTRAINT PK_situaciones_deudor PRIMARY KEY (id),
    CONSTRAINT UQ_situaciones_deudor UNIQUE (regimen_informativo_id, cliente_id),
    CONSTRAINT CK_situaciones_deudor_situacion CHECK (situacion BETWEEN 1 AND 5),
    CONSTRAINT CK_situaciones_deudor_atraso CHECK (dias_atraso >= 0)
);
GO
ALTER TABLE dbo.regimenes_informativos ADD CONSTRAINT FK_regimenes_informativos_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.situaciones_deudor ADD CONSTRAINT FK_situaciones_deudor_regimen_informativo_id FOREIGN KEY (regimen_informativo_id) REFERENCES dbo.regimenes_informativos (id);
ALTER TABLE dbo.situaciones_deudor ADD CONSTRAINT FK_situaciones_deudor_cliente_id FOREIGN KEY (cliente_id) REFERENCES dbo.clientes (id);
CREATE UNIQUE INDEX UQ_regimenes_vigente ON dbo.regimenes_informativos (periodo) WHERE estado = 'Vigente';
CREATE INDEX IX_regimenes_informativos_usuario_id ON dbo.regimenes_informativos (usuario_id);
CREATE INDEX IX_situaciones_deudor_cliente_id ON dbo.situaciones_deudor (cliente_id);
GO
