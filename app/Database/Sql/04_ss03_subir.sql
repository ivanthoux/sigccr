/* SS-03 Gestión de líneas de crédito: generado desde la especificación del modelo de datos (4.3) */
GO
-- lineas_credito: Productos crediticios ofrecidos por la entidad.
CREATE TABLE dbo.lineas_credito (
    id INT IDENTITY(1,1) NOT NULL,
    codigo VARCHAR(20) NOT NULL,
    nombre NVARCHAR(100) NOT NULL,
    descripcion NVARCHAR(500) NULL,
    estado VARCHAR(10) NOT NULL CONSTRAINT DF_lineas_credito_estado DEFAULT ('Activa'),
    version_fila INT NOT NULL CONSTRAINT DF_lineas_credito_version_fila DEFAULT (1),
    creado_en DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_lineas_credito_creado_en DEFAULT (SYSDATETIMEOFFSET()),
    actualizado_en DATETIMEOFFSET(3) NULL,
    CONSTRAINT PK_lineas_credito PRIMARY KEY (id),
    CONSTRAINT UQ_lineas_credito_codigo UNIQUE (codigo),
    CONSTRAINT CK_lineas_credito_estado CHECK (estado IN ('Activa', 'Suspendida', 'Vencida'))
);
GO
-- versiones_condiciones: Condiciones versionadas de una línea. Una versión registrada no se modifica: los cambios generan una versión nueva (RN-15).
CREATE TABLE dbo.versiones_condiciones (
    id INT IDENTITY(1,1) NOT NULL,
    linea_credito_id INT NOT NULL,
    numero INT NOT NULL,
    vigente_desde DATETIMEOFFSET(3) NOT NULL,
    tna DECIMAL(18,12) NOT NULL,
    gasto_otorgamiento_pct DECIMAL(18,12) NOT NULL,
    gasto_administrativo_cuota DECIMAL(19,2) NOT NULL,
    monto_minimo DECIMAL(19,2) NOT NULL,
    monto_maximo DECIMAL(19,2) NOT NULL,
    cuotas_minimas SMALLINT NOT NULL,
    cuotas_maximas SMALLINT NOT NULL,
    edad_minima TINYINT NULL,
    edad_maxima TINYINT NULL,
    antiguedad_minima_meses SMALLINT NULL,
    admite_persona_humana BIT NOT NULL,
    admite_persona_juridica BIT NOT NULL,
    vigencia_inicio DATE NOT NULL,
    vigencia_fin DATE NOT NULL,
    CONSTRAINT PK_versiones_condiciones PRIMARY KEY (id),
    CONSTRAINT UQ_versiones_condiciones_numero UNIQUE (linea_credito_id, numero),
    CONSTRAINT CK_versiones_condiciones_montos CHECK (monto_minimo > 0 AND monto_minimo <= monto_maximo),
    CONSTRAINT CK_versiones_condiciones_cuotas CHECK (cuotas_minimas >= 1 AND cuotas_minimas <= cuotas_maximas),
    CONSTRAINT CK_versiones_condiciones_edades CHECK (edad_minima IS NULL OR edad_maxima IS NULL OR edad_minima <= edad_maxima),
    CONSTRAINT CK_versiones_condiciones_vigencia CHECK (vigencia_inicio <= vigencia_fin),
    CONSTRAINT CK_versiones_condiciones_tasas CHECK (tna >= 0 AND gasto_otorgamiento_pct >= 0 AND gasto_otorgamiento_pct < 1 AND gasto_administrativo_cuota >= 0),
    CONSTRAINT CK_versiones_condiciones_tipos CHECK (admite_persona_humana = 1 OR admite_persona_juridica = 1)
);
GO
-- condiciones_categorias: Categorías de ingresos admitidas por una versión; normaliza el atributo categoriasAdmitidas del dominio.
CREATE TABLE dbo.condiciones_categorias (
    version_condiciones_id INT NOT NULL,
    categoria_ingresos VARCHAR(20) NOT NULL,
    CONSTRAINT PK_condiciones_categorias PRIMARY KEY (version_condiciones_id, categoria_ingresos),
    CONSTRAINT CK_condiciones_categorias_categoria_ingresos CHECK (categoria_ingresos IN ('RelacionDependencia', 'JubiladoPensionado', 'Monotributista', 'ResponsableInscripto', 'Empresa'))
);
GO
-- requisitos_documentales: Documentación exigida por una versión según la categoría de ingresos (RN-11).
CREATE TABLE dbo.requisitos_documentales (
    id INT IDENTITY(1,1) NOT NULL,
    version_condiciones_id INT NOT NULL,
    tipo_documentacion_id INT NOT NULL,
    categoria_ingresos VARCHAR(20) NULL,
    obligatorio BIT NOT NULL CONSTRAINT DF_requisitos_documentales_obligatorio DEFAULT (1),
    CONSTRAINT PK_requisitos_documentales PRIMARY KEY (id),
    CONSTRAINT UQ_requisitos_documentales UNIQUE (version_condiciones_id, tipo_documentacion_id, categoria_ingresos),
    CONSTRAINT CK_requisitos_documentales_categoria_ingresos CHECK (categoria_ingresos IS NULL OR categoria_ingresos IN ('RelacionDependencia', 'JubiladoPensionado', 'Monotributista', 'ResponsableInscripto', 'Empresa'))
);
GO
ALTER TABLE dbo.versiones_condiciones ADD CONSTRAINT FK_versiones_condiciones_linea_credito_id FOREIGN KEY (linea_credito_id) REFERENCES dbo.lineas_credito (id);
ALTER TABLE dbo.condiciones_categorias ADD CONSTRAINT FK_condiciones_categorias_version_condiciones_id FOREIGN KEY (version_condiciones_id) REFERENCES dbo.versiones_condiciones (id);
ALTER TABLE dbo.requisitos_documentales ADD CONSTRAINT FK_requisitos_documentales_version_condiciones_id FOREIGN KEY (version_condiciones_id) REFERENCES dbo.versiones_condiciones (id);
ALTER TABLE dbo.requisitos_documentales ADD CONSTRAINT FK_requisitos_documentales_tipo_documentacion_id FOREIGN KEY (tipo_documentacion_id) REFERENCES dbo.tipos_documentacion (id);
CREATE INDEX IX_requisitos_documentales_tipo_documentacion_id ON dbo.requisitos_documentales (tipo_documentacion_id);
