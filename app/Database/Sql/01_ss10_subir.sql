/* SS-10 Servicios comunes: generado desde la especificación del modelo de datos (4.3) */
CREATE SEQUENCE dbo.seq_solicitudes AS INT START WITH 1 INCREMENT BY 1 NO CYCLE CACHE 20;
CREATE SEQUENCE dbo.seq_cobros AS BIGINT START WITH 1 INCREMENT BY 1 NO CYCLE CACHE 20;
CREATE SEQUENCE dbo.seq_comprobantes AS BIGINT START WITH 1 INCREMENT BY 1 NO CYCLE CACHE 20;
CREATE SEQUENCE dbo.seq_ordenes AS BIGINT START WITH 1 INCREMENT BY 1 NO CYCLE CACHE 20;
CREATE SEQUENCE dbo.seq_creditos AS INT START WITH 1 INCREMENT BY 1 NO CYCLE CACHE 20;
CREATE SEQUENCE dbo.seq_campanas AS INT START WITH 1 INCREMENT BY 1 NO CYCLE CACHE 20;
CREATE SEQUENCE dbo.seq_propuestas AS INT START WITH 1 INCREMENT BY 1 NO CYCLE CACHE 20;
GO
-- tipos_documentacion: Catálogo de tipos de documentación respaldatoria exigibles en el legajo (RN-11). Se ubica en servicios comunes porque lo utilizan SS-02 y SS-03, que no dependen entre sí.
CREATE TABLE dbo.tipos_documentacion (
    id INT IDENTITY(1,1) NOT NULL,
    codigo VARCHAR(30) NOT NULL,
    nombre NVARCHAR(120) NOT NULL,
    aplica_a VARCHAR(15) NOT NULL,
    es_clave BIT NOT NULL CONSTRAINT DF_tipos_documentacion_es_clave DEFAULT (0),
    activo BIT NOT NULL CONSTRAINT DF_tipos_documentacion_activo DEFAULT (1),
    CONSTRAINT PK_tipos_documentacion PRIMARY KEY (id),
    CONSTRAINT UQ_tipos_documentacion_codigo UNIQUE (codigo),
    CONSTRAINT CK_tipos_documentacion_aplica_a CHECK (aplica_a IN ('PersonaHumana', 'PersonaJuridica', 'Ambos'))
);
GO
-- versiones_parametros: Versiones de cada grupo de parámetros de negocio; las operaciones referencian la versión aplicada (RN-15, RN-45).
CREATE TABLE dbo.versiones_parametros (
    id INT IDENTITY(1,1) NOT NULL,
    tipo VARCHAR(15) NOT NULL,
    numero INT NOT NULL,
    vigente_desde DATETIMEOFFSET(3) NOT NULL,
    valores NVARCHAR(MAX) NOT NULL,
    motivo NVARCHAR(500) NULL,
    usuario_id INT NULL,
    creado_en DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_versiones_parametros_creado_en DEFAULT (SYSDATETIMEOFFSET()),
    CONSTRAINT PK_versiones_parametros PRIMARY KEY (id),
    CONSTRAINT UQ_versiones_parametros_tipo_numero UNIQUE (tipo, numero),
    CONSTRAINT CK_versiones_parametros_tipo CHECK (tipo IN ('Evaluacion', 'Estructuracion', 'Morosidad', 'Campanas')),
    CONSTRAINT CK_versiones_parametros_valores CHECK (ISJSON(valores) = 1)
);
GO
-- registros_auditoria: Evidencia inalterable de operaciones y decisiones relevantes (RN-45). Solo admite inserciones: un disparador rechaza modificaciones y eliminaciones, y el usuario de la aplicación no tiene permiso UPDATE ni DELETE sobre ella.
CREATE TABLE dbo.registros_auditoria (
    id BIGINT IDENTITY(1,1) NOT NULL,
    fecha_hora DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_registros_auditoria_fecha_hora DEFAULT (SYSDATETIMEOFFSET()),
    cuenta_acceso_id INT NULL,
    responsable NVARCHAR(150) NOT NULL,
    proceso VARCHAR(60) NULL,
    accion VARCHAR(60) NOT NULL,
    objeto_tipo VARCHAR(60) NOT NULL,
    objeto_id VARCHAR(40) NULL,
    resultado VARCHAR(20) NOT NULL,
    motivo NVARCHAR(1000) NULL,
    detalle NVARCHAR(MAX) NULL,
    ip_origen VARCHAR(45) NULL,
    CONSTRAINT PK_registros_auditoria PRIMARY KEY (id),
    CONSTRAINT CK_registros_auditoria_detalle CHECK (detalle IS NULL OR ISJSON(detalle) = 1)
);
GO
-- alertas: Avisos que requieren la intervención de un rol o de un usuario (RF-11, CU-83).
CREATE TABLE dbo.alertas (
    id BIGINT IDENTITY(1,1) NOT NULL,
    tipo VARCHAR(15) NOT NULL,
    causa NVARCHAR(300) NOT NULL,
    accion_pendiente NVARCHAR(300) NULL,
    rol_destino VARCHAR(30) NULL,
    usuario_destino_id INT NULL,
    objeto_tipo VARCHAR(60) NULL,
    objeto_id VARCHAR(40) NULL,
    fecha_generacion DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_alertas_fecha_generacion DEFAULT (SYSDATETIMEOFFSET()),
    fecha_resolucion DATETIMEOFFSET(3) NULL,
    estado VARCHAR(10) NOT NULL CONSTRAINT DF_alertas_estado DEFAULT ('Pendiente'),
    CONSTRAINT PK_alertas PRIMARY KEY (id),
    CONSTRAINT CK_alertas_tipo CHECK (tipo IN ('Operativa', 'Vencimiento', 'Integracion', 'Proceso', 'Seguridad')),
    CONSTRAINT CK_alertas_estado CHECK (estado IN ('Pendiente', 'Resuelta')),
    CONSTRAINT CK_alertas_destino CHECK (rol_destino IS NOT NULL OR usuario_destino_id IS NOT NULL)
);
GO
-- comunicaciones: Cola de comunicaciones salientes con su política de reintentos (RNF-09, CU-95).
CREATE TABLE dbo.comunicaciones (
    id BIGINT IDENTITY(1,1) NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    canal VARCHAR(10) NOT NULL CONSTRAINT DF_comunicaciones_canal DEFAULT ('Correo'),
    destino NVARCHAR(254) NOT NULL,
    plantilla VARCHAR(60) NOT NULL,
    datos_cifrados VARBINARY(MAX) NULL,
    identificador_operacion UNIQUEIDENTIFIER NOT NULL CONSTRAINT DF_comunicaciones_identificador_operacion DEFAULT (NEWID()),
    origen_tipo VARCHAR(40) NOT NULL,
    origen_id BIGINT NOT NULL,
    programada_para DATETIMEOFFSET(3) NOT NULL,
    intentos TINYINT NOT NULL CONSTRAINT DF_comunicaciones_intentos DEFAULT (0),
    estado VARCHAR(20) NOT NULL CONSTRAINT DF_comunicaciones_estado DEFAULT ('Pendiente'),
    version_fila INT NOT NULL CONSTRAINT DF_comunicaciones_version_fila DEFAULT (1),
    creado_en DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_comunicaciones_creado_en DEFAULT (SYSDATETIMEOFFSET()),
    CONSTRAINT PK_comunicaciones PRIMARY KEY (id),
    CONSTRAINT UQ_comunicaciones_identificador_operacion UNIQUE (identificador_operacion),
    CONSTRAINT CK_comunicaciones_tipo CHECK (tipo IN ('Autorizacion', 'VerificacionCorreo', 'Oferta', 'AvisoObligacion', 'Notificacion')),
    CONSTRAINT CK_comunicaciones_estado CHECK (estado IN ('Pendiente', 'AceptadaProveedor', 'Entregada', 'ReintentoProgramado', 'Fallida', 'Cancelada', 'EnRevision')),
    CONSTRAINT CK_comunicaciones_intentos CHECK (intentos BETWEEN 0 AND 4)
);
GO
-- intentos_envio: Cada intento de envío de una comunicación y su resultado (RNF-09).
CREATE TABLE dbo.intentos_envio (
    id BIGINT IDENTITY(1,1) NOT NULL,
    comunicacion_id BIGINT NOT NULL,
    fecha_hora DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_intentos_envio_fecha_hora DEFAULT (SYSDATETIMEOFFSET()),
    resultado VARCHAR(20) NOT NULL,
    id_proveedor VARCHAR(100) NULL,
    detalle NVARCHAR(500) NULL,
    CONSTRAINT PK_intentos_envio PRIMARY KEY (id)
);
GO
-- ejecuciones_proceso: Registro de cada ejecución de un proceso automático: inicio, fin, elementos procesados y errores (RNF-10).
CREATE TABLE dbo.ejecuciones_proceso (
    id BIGINT IDENTITY(1,1) NOT NULL,
    proceso VARCHAR(60) NOT NULL,
    fecha_referencia DATE NULL,
    inicio DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_ejecuciones_proceso_inicio DEFAULT (SYSDATETIMEOFFSET()),
    fin DATETIMEOFFSET(3) NULL,
    elementos_procesados INT NOT NULL CONSTRAINT DF_ejecuciones_proceso_elementos_procesados DEFAULT (0),
    errores INT NOT NULL CONSTRAINT DF_ejecuciones_proceso_errores DEFAULT (0),
    estado VARCHAR(10) NOT NULL CONSTRAINT DF_ejecuciones_proceso_estado DEFAULT ('EnCurso'),
    detalle NVARCHAR(2000) NULL,
    CONSTRAINT PK_ejecuciones_proceso PRIMARY KEY (id),
    CONSTRAINT CK_ejecuciones_proceso_estado CHECK (estado IN ('EnCurso', 'Finalizada', 'ConErrores', 'Fallida'))
);
GO
-- intercambios_externos: Peticiones y respuestas intercambiadas con servicios externos, necesarias para la conciliación y el diagnóstico, sin datos sensibles (RNF-08, RNF-16).
CREATE TABLE dbo.intercambios_externos (
    id BIGINT IDENTITY(1,1) NOT NULL,
    servicio VARCHAR(25) NOT NULL,
    operacion VARCHAR(60) NOT NULL,
    referencia VARCHAR(100) NULL,
    fecha_hora DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_intercambios_externos_fecha_hora DEFAULT (SYSDATETIMEOFFSET()),
    duracion_ms INT NULL,
    codigo_respuesta VARCHAR(20) NULL,
    resumen NVARCHAR(2000) NULL,
    CONSTRAINT PK_intercambios_externos PRIMARY KEY (id),
    CONSTRAINT CK_intercambios_externos_servicio CHECK (servicio IN ('InformacionCrediticia', 'Pasarela', 'Comunicaciones'))
);
GO
ALTER TABLE dbo.intentos_envio ADD CONSTRAINT FK_intentos_envio_comunicacion_id FOREIGN KEY (comunicacion_id) REFERENCES dbo.comunicaciones (id);
CREATE INDEX IX_versiones_parametros_vigencia ON dbo.versiones_parametros (tipo, vigente_desde);
CREATE INDEX IX_registros_auditoria_fecha ON dbo.registros_auditoria (fecha_hora);
CREATE INDEX IX_registros_auditoria_objeto ON dbo.registros_auditoria (objeto_tipo, objeto_id);
CREATE INDEX IX_registros_auditoria_cuenta ON dbo.registros_auditoria (cuenta_acceso_id, fecha_hora);
CREATE INDEX IX_alertas_pendientes_rol ON dbo.alertas (rol_destino, estado) WHERE estado = 'Pendiente';
CREATE INDEX IX_alertas_pendientes_usuario ON dbo.alertas (usuario_destino_id, estado) WHERE estado = 'Pendiente';
CREATE INDEX IX_comunicaciones_origen ON dbo.comunicaciones (origen_tipo, origen_id);
CREATE INDEX IX_comunicaciones_cola ON dbo.comunicaciones (estado, programada_para) WHERE estado IN ('Pendiente', 'ReintentoProgramado');
CREATE INDEX IX_intentos_envio_comunicacion ON dbo.intentos_envio (comunicacion_id);
CREATE INDEX IX_ejecuciones_proceso ON dbo.ejecuciones_proceso (proceso, fecha_referencia);
CREATE INDEX IX_intercambios_externos_ref ON dbo.intercambios_externos (servicio, referencia);
GO
CREATE TRIGGER dbo.TR_registros_auditoria_inmutable ON dbo.registros_auditoria
INSTEAD OF UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    THROW 50001, 'El registro de auditoría no admite modificaciones ni eliminaciones (RN-45).', 1;
END;
