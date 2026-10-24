/* SS-02 Gestión de clientes: generado desde la especificación del modelo de datos (4.3) */
GO
-- clientes: Superclase del padrón de clientes (herencia por tablas de clase). Conserva los datos comunes a personas humanas y jurídicas.
CREATE TABLE dbo.clientes (
    id INT IDENTITY(1,1) NOT NULL,
    tipo_cliente VARCHAR(15) NOT NULL,
    cuit_cuil CHAR(11) NULL,
    denominacion NVARCHAR(200) NOT NULL,
    correo NVARCHAR(254) NULL,
    telefono VARCHAR(30) NULL,
    domicilio NVARCHAR(200) NOT NULL,
    ingresos_declarados DECIMAL(19,2) NOT NULL,
    categoria_ingresos VARCHAR(20) NOT NULL,
    estado VARCHAR(15) NOT NULL CONSTRAINT DF_clientes_estado DEFAULT ('Activo'),
    cuenta_cliente_id INT NULL,
    fecha_alta DATE NOT NULL,
    version_fila INT NOT NULL CONSTRAINT DF_clientes_version_fila DEFAULT (1),
    creado_en DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_clientes_creado_en DEFAULT (SYSDATETIMEOFFSET()),
    actualizado_en DATETIMEOFFSET(3) NULL,
    CONSTRAINT PK_clientes PRIMARY KEY (id),
    CONSTRAINT UQ_clientes_id_tipo UNIQUE (id, tipo_cliente),
    CONSTRAINT CK_clientes_tipo_cliente CHECK (tipo_cliente IN ('PersonaHumana', 'PersonaJuridica')),
    CONSTRAINT CK_clientes_categoria_ingresos CHECK (categoria_ingresos IN ('RelacionDependencia', 'JubiladoPensionado', 'Monotributista', 'ResponsableInscripto', 'Empresa')),
    CONSTRAINT CK_clientes_estado CHECK (estado IN ('Activo', 'Desactivado')),
    CONSTRAINT CK_clientes_cuit_juridica CHECK (tipo_cliente = 'PersonaHumana' OR cuit_cuil IS NOT NULL),
    CONSTRAINT CK_clientes_cuit_formato CHECK (cuit_cuil IS NULL OR cuit_cuil NOT LIKE '%[^0-9]%'),
    CONSTRAINT CK_clientes_categoria CHECK ((tipo_cliente = 'PersonaJuridica' AND categoria_ingresos = 'Empresa') OR (tipo_cliente = 'PersonaHumana' AND categoria_ingresos <> 'Empresa')),
    CONSTRAINT CK_clientes_ingresos CHECK (ingresos_declarados >= 0)
);
GO
-- personas_humanas: Subclase de Cliente identificada por su documento.
CREATE TABLE dbo.personas_humanas (
    cliente_id INT NOT NULL,
    tipo_cliente AS CAST('PersonaHumana' AS VARCHAR(15)) PERSISTED NOT NULL,
    tipo_documento VARCHAR(10) NOT NULL,
    nro_documento VARCHAR(15) NOT NULL,
    nombre NVARCHAR(80) NOT NULL,
    apellido NVARCHAR(80) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    estado_civil VARCHAR(20) NULL,
    ocupacion NVARCHAR(100) NULL,
    empleador NVARCHAR(150) NULL,
    CONSTRAINT PK_personas_humanas PRIMARY KEY (cliente_id),
    CONSTRAINT UQ_personas_humanas_documento UNIQUE (tipo_documento, nro_documento),
    CONSTRAINT CK_personas_humanas_tipo_documento CHECK (tipo_documento IN ('DNI', 'LC', 'LE', 'Pasaporte'))
);
GO
-- personas_juridicas: Subclase de Cliente para pequeñas y medianas empresas locales (RN-75).
CREATE TABLE dbo.personas_juridicas (
    cliente_id INT NOT NULL,
    tipo_cliente AS CAST('PersonaJuridica' AS VARCHAR(15)) PERSISTED NOT NULL,
    razon_social NVARCHAR(200) NOT NULL,
    fecha_constitucion DATE NOT NULL,
    actividad_principal NVARCHAR(150) NOT NULL,
    representante_nombre NVARCHAR(150) NOT NULL,
    representante_documento VARCHAR(15) NOT NULL,
    representante_cargo NVARCHAR(80) NOT NULL,
    CONSTRAINT PK_personas_juridicas PRIMARY KEY (cliente_id)
);
GO
-- documentos_legajo: Documentos incorporados al legajo. El archivo se guarda fuera del directorio público con nombre aleatorio (RNF-12); un reemplazo crea un registro nuevo que referencia al anterior (RN-44).
CREATE TABLE dbo.documentos_legajo (
    id INT IDENTITY(1,1) NOT NULL,
    cliente_id INT NOT NULL,
    tipo_documentacion_id INT NOT NULL,
    nombre_original NVARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(260) NOT NULL,
    tipo_mime VARCHAR(20) NOT NULL,
    tamano_bytes INT NOT NULL,
    hash_sha256 CHAR(64) NOT NULL,
    fecha_incorporacion DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_documentos_legajo_fecha_incorporacion DEFAULT (SYSDATETIMEOFFSET()),
    vigencia_hasta DATE NULL,
    reemplaza_documento_id INT NULL,
    usuario_id INT NULL,
    CONSTRAINT PK_documentos_legajo PRIMARY KEY (id),
    CONSTRAINT UQ_documentos_legajo_ruta_archivo UNIQUE (ruta_archivo),
    CONSTRAINT CK_documentos_legajo_mime CHECK (tipo_mime IN ('application/pdf', 'image/jpeg', 'image/png')),
    CONSTRAINT CK_documentos_legajo_tamano CHECK (tamano_bytes BETWEEN 1 AND 5242880)
);
GO
-- consentimientos: Historial de aceptaciones y revocaciones; rige el último registro de cada tipo (RN-64, RNF-14).
CREATE TABLE dbo.consentimientos (
    id INT IDENTITY(1,1) NOT NULL,
    cliente_id INT NOT NULL,
    tipo VARCHAR(25) NOT NULL,
    otorgado BIT NOT NULL,
    fecha DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_consentimientos_fecha DEFAULT (SYSDATETIMEOFFSET()),
    medio VARCHAR(10) NOT NULL,
    CONSTRAINT PK_consentimientos PRIMARY KEY (id),
    CONSTRAINT CK_consentimientos_tipo CHECK (tipo IN ('PoliticaDatos', 'ComunicacionesComerciales')),
    CONSTRAINT CK_consentimientos_medio CHECK (medio IN ('Portal', 'Sucursal'))
);
GO
ALTER TABLE dbo.clientes ADD CONSTRAINT FK_clientes_cuenta_cliente_id FOREIGN KEY (cuenta_cliente_id) REFERENCES dbo.cuentas_cliente (id);
ALTER TABLE dbo.personas_humanas ADD CONSTRAINT FK_personas_humanas_cliente FOREIGN KEY (cliente_id, tipo_cliente) REFERENCES dbo.clientes (id, tipo_cliente);
ALTER TABLE dbo.personas_juridicas ADD CONSTRAINT FK_personas_juridicas_cliente FOREIGN KEY (cliente_id, tipo_cliente) REFERENCES dbo.clientes (id, tipo_cliente);
ALTER TABLE dbo.documentos_legajo ADD CONSTRAINT FK_documentos_legajo_cliente_id FOREIGN KEY (cliente_id) REFERENCES dbo.clientes (id);
ALTER TABLE dbo.documentos_legajo ADD CONSTRAINT FK_documentos_legajo_tipo_documentacion_id FOREIGN KEY (tipo_documentacion_id) REFERENCES dbo.tipos_documentacion (id);
ALTER TABLE dbo.documentos_legajo ADD CONSTRAINT FK_documentos_legajo_reemplaza_documento_id FOREIGN KEY (reemplaza_documento_id) REFERENCES dbo.documentos_legajo (id);
ALTER TABLE dbo.documentos_legajo ADD CONSTRAINT FK_documentos_legajo_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.consentimientos ADD CONSTRAINT FK_consentimientos_cliente_id FOREIGN KEY (cliente_id) REFERENCES dbo.clientes (id);
CREATE UNIQUE INDEX UQ_clientes_cuenta_cliente_id ON dbo.clientes (cuenta_cliente_id) WHERE cuenta_cliente_id IS NOT NULL;
CREATE UNIQUE INDEX UQ_clientes_cuit ON dbo.clientes (cuit_cuil) WHERE cuit_cuil IS NOT NULL;
CREATE INDEX IX_clientes_denominacion ON dbo.clientes (denominacion) INCLUDE (estado, tipo_cliente);
CREATE INDEX IX_personas_humanas_nro ON dbo.personas_humanas (nro_documento);
CREATE UNIQUE INDEX UQ_documentos_legajo_reemplaza_documento_id ON dbo.documentos_legajo (reemplaza_documento_id) WHERE reemplaza_documento_id IS NOT NULL;
CREATE INDEX IX_documentos_legajo_cliente ON dbo.documentos_legajo (cliente_id, tipo_documentacion_id);
CREATE INDEX IX_documentos_legajo_tipo_documentacion_id ON dbo.documentos_legajo (tipo_documentacion_id);
CREATE INDEX IX_documentos_legajo_usuario_id ON dbo.documentos_legajo (usuario_id);
CREATE INDEX IX_consentimientos_cliente ON dbo.consentimientos (cliente_id, tipo, fecha);
