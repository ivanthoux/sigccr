/* SS-01 Acceso y administración de usuarios: generado desde la especificación del modelo de datos (4.3) */
GO
-- roles: Perfiles de usuario interno (RN-01, RN-06).
CREATE TABLE dbo.roles (
    id INT IDENTITY(1,1) NOT NULL,
    codigo VARCHAR(30) NOT NULL,
    nombre NVARCHAR(60) NOT NULL,
    descripcion NVARCHAR(255) NULL,
    CONSTRAINT PK_roles PRIMARY KEY (id),
    CONSTRAINT UQ_roles_codigo UNIQUE (codigo)
);
GO
-- permisos: Operaciones del sistema que pueden habilitarse para un rol (RNF-01).
CREATE TABLE dbo.permisos (
    id INT IDENTITY(1,1) NOT NULL,
    codigo VARCHAR(80) NOT NULL,
    descripcion NVARCHAR(255) NOT NULL,
    CONSTRAINT PK_permisos PRIMARY KEY (id),
    CONSTRAINT UQ_permisos_codigo UNIQUE (codigo)
);
GO
-- roles_permisos: Resuelve la asociación muchos a muchos «habilita» entre roles y permisos.
CREATE TABLE dbo.roles_permisos (
    rol_id INT NOT NULL,
    permiso_id INT NOT NULL,
    CONSTRAINT PK_roles_permisos PRIMARY KEY (rol_id, permiso_id)
);
GO
-- cuentas_acceso: Superclase de las cuentas de acceso (herencia por tablas de clase). El discriminador tipo_cuenta separa las cuentas del panel y del portal (RN-08).
CREATE TABLE dbo.cuentas_acceso (
    id INT IDENTITY(1,1) NOT NULL,
    tipo_cuenta VARCHAR(15) NOT NULL,
    correo NVARCHAR(254) NOT NULL,
    hash_contrasena VARCHAR(255) NULL,
    correo_verificado BIT NOT NULL CONSTRAINT DF_cuentas_acceso_correo_verificado DEFAULT (0),
    estado VARCHAR(25) NOT NULL CONSTRAINT DF_cuentas_acceso_estado DEFAULT ('PendienteActivacion'),
    intentos_fallidos TINYINT NOT NULL CONSTRAINT DF_cuentas_acceso_intentos_fallidos DEFAULT (0),
    inicio_ventana_intentos DATETIMEOFFSET(3) NULL,
    bloqueada_hasta DATETIMEOFFSET(3) NULL,
    ultimo_acceso DATETIMEOFFSET(3) NULL,
    version_seguridad INT NOT NULL CONSTRAINT DF_cuentas_acceso_version_seguridad DEFAULT (1),
    version_fila INT NOT NULL CONSTRAINT DF_cuentas_acceso_version_fila DEFAULT (1),
    creado_en DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_cuentas_acceso_creado_en DEFAULT (SYSDATETIMEOFFSET()),
    actualizado_en DATETIMEOFFSET(3) NULL,
    CONSTRAINT PK_cuentas_acceso PRIMARY KEY (id),
    CONSTRAINT UQ_cuentas_acceso_correo UNIQUE (tipo_cuenta, correo),
    CONSTRAINT UQ_cuentas_acceso_id_tipo UNIQUE (id, tipo_cuenta),
    CONSTRAINT CK_cuentas_acceso_tipo_cuenta CHECK (tipo_cuenta IN ('UsuarioInterno', 'Cliente')),
    CONSTRAINT CK_cuentas_acceso_estado CHECK (estado IN ('PendienteActivacion', 'Habilitada', 'BloqueadaTemporalmente', 'Desactivada')),
    CONSTRAINT CK_cuentas_acceso_contrasena CHECK (estado = 'PendienteActivacion' OR hash_contrasena IS NOT NULL),
    CONSTRAINT CK_cuentas_acceso_intentos CHECK (intentos_fallidos BETWEEN 0 AND 5)
);
GO
-- usuarios_internos: Subclase de CuentaAcceso para el personal de la entidad. Comparte la clave primaria con cuentas_acceso.
CREATE TABLE dbo.usuarios_internos (
    id INT NOT NULL,
    tipo_cuenta AS CAST('UsuarioInterno' AS VARCHAR(15)) PERSISTED NOT NULL,
    rol_id INT NOT NULL,
    nombre NVARCHAR(80) NOT NULL,
    apellido NVARCHAR(80) NOT NULL,
    tipo_documento VARCHAR(10) NOT NULL,
    nro_documento VARCHAR(15) NOT NULL,
    CONSTRAINT PK_usuarios_internos PRIMARY KEY (id),
    CONSTRAINT UQ_usuarios_internos_documento UNIQUE (tipo_documento, nro_documento),
    CONSTRAINT CK_usuarios_internos_tipo_documento CHECK (tipo_documento IN ('DNI', 'LC', 'LE', 'Pasaporte'))
);
GO
-- cuentas_cliente: Subclase de CuentaAcceso para el acceso al portal. El vínculo con el cliente se registra en clientes.cuenta_cliente_id; la rehabilitación reutiliza la cuenta existente (RN-08, RN-10).
CREATE TABLE dbo.cuentas_cliente (
    id INT NOT NULL,
    tipo_cuenta AS CAST('Cliente' AS VARCHAR(15)) PERSISTED NOT NULL,
    fecha_activacion DATETIMEOFFSET(3) NULL,
    CONSTRAINT PK_cuentas_cliente PRIMARY KEY (id)
);
GO
-- autorizaciones_temporales: Códigos o enlaces de un solo uso. Se guarda solo el hash SHA-256 del valor enviado (RN-03, RNF-14).
CREATE TABLE dbo.autorizaciones_temporales (
    id BIGINT IDENTITY(1,1) NOT NULL,
    cuenta_acceso_id INT NOT NULL,
    proposito VARCHAR(20) NOT NULL,
    hash_codigo CHAR(64) NOT NULL,
    correo_destino NVARCHAR(254) NULL,
    emitida DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_autorizaciones_temporales_emitida DEFAULT (SYSDATETIMEOFFSET()),
    vence DATETIMEOFFSET(3) NOT NULL,
    utilizada DATETIMEOFFSET(3) NULL,
    estado VARCHAR(10) NOT NULL CONSTRAINT DF_autorizaciones_temporales_estado DEFAULT ('Vigente'),
    CONSTRAINT PK_autorizaciones_temporales PRIMARY KEY (id),
    CONSTRAINT UQ_autorizaciones_temporales_hash_codigo UNIQUE (hash_codigo),
    CONSTRAINT CK_autorizaciones_temporales_proposito CHECK (proposito IN ('Activacion', 'Rehabilitacion', 'DefinicionInicial', 'Recuperacion', 'Restablecimiento', 'CambioCorreo', 'VerificacionCorreo')),
    CONSTRAINT CK_autorizaciones_temporales_estado CHECK (estado IN ('Vigente', 'Utilizada', 'Vencida', 'Sustituida')),
    CONSTRAINT CK_autorizaciones_vence CHECK (vence > emitida)
);
GO
ALTER TABLE dbo.roles_permisos ADD CONSTRAINT FK_roles_permisos_rol_id FOREIGN KEY (rol_id) REFERENCES dbo.roles (id);
ALTER TABLE dbo.roles_permisos ADD CONSTRAINT FK_roles_permisos_permiso_id FOREIGN KEY (permiso_id) REFERENCES dbo.permisos (id);
ALTER TABLE dbo.usuarios_internos ADD CONSTRAINT FK_usuarios_internos_rol_id FOREIGN KEY (rol_id) REFERENCES dbo.roles (id);
ALTER TABLE dbo.usuarios_internos ADD CONSTRAINT FK_usuarios_internos_cuenta FOREIGN KEY (id, tipo_cuenta) REFERENCES dbo.cuentas_acceso (id, tipo_cuenta);
ALTER TABLE dbo.cuentas_cliente ADD CONSTRAINT FK_cuentas_cliente_cuenta FOREIGN KEY (id, tipo_cuenta) REFERENCES dbo.cuentas_acceso (id, tipo_cuenta);
ALTER TABLE dbo.autorizaciones_temporales ADD CONSTRAINT FK_autorizaciones_temporales_cuenta_acceso_id FOREIGN KEY (cuenta_acceso_id) REFERENCES dbo.cuentas_acceso (id);
CREATE INDEX IX_roles_permisos_permiso_id ON dbo.roles_permisos (permiso_id);
CREATE INDEX IX_usuarios_internos_rol_id ON dbo.usuarios_internos (rol_id);
CREATE UNIQUE INDEX UQ_autorizaciones_vigente ON dbo.autorizaciones_temporales (cuenta_acceso_id, proposito) WHERE estado = 'Vigente';
