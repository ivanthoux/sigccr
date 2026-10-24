/* SS-04 Solicitudes, evaluación y otorgamiento de créditos: generado desde la especificación del modelo de datos (4.3) */
GO
-- dias_no_habiles: Calendario operativo para el cómputo de horas hábiles de las aprobaciones (RN-49, CU-82).
CREATE TABLE dbo.dias_no_habiles (
    fecha DATE NOT NULL,
    descripcion NVARCHAR(150) NOT NULL,
    tipo VARCHAR(15) NOT NULL,
    CONSTRAINT PK_dias_no_habiles PRIMARY KEY (fecha),
    CONSTRAINT CK_dias_no_habiles_tipo CHECK (tipo IN ('Feriado', 'NoLaborable', 'CierreEntidad'))
);
GO
-- simulaciones: Cálculos preliminares; no generan solicitud ni deuda (RN-17).
CREATE TABLE dbo.simulaciones (
    id INT IDENTITY(1,1) NOT NULL,
    cliente_id INT NOT NULL,
    version_condiciones_id INT NOT NULL,
    fecha_generacion DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_simulaciones_fecha_generacion DEFAULT (SYSDATETIMEOFFSET()),
    importe DECIMAL(19,2) NOT NULL,
    cantidad_cuotas SMALLINT NOT NULL,
    valor_cuota DECIMAL(19,2) NOT NULL,
    tea DECIMAL(18,12) NOT NULL,
    cftea DECIMAL(18,12) NOT NULL,
    vence DATETIMEOFFSET(3) NOT NULL,
    estado VARCHAR(10) NOT NULL CONSTRAINT DF_simulaciones_estado DEFAULT ('Vigente'),
    CONSTRAINT PK_simulaciones PRIMARY KEY (id),
    CONSTRAINT CK_simulaciones_estado CHECK (estado IN ('Vigente', 'Vencida'))
);
GO
-- solicitudes_credito: Peticiones formales de financiación; su ciclo de vida se describe en 4.2.3.
CREATE TABLE dbo.solicitudes_credito (
    id INT IDENTITY(1,1) NOT NULL,
    numero INT NOT NULL CONSTRAINT DF_solicitudes_credito_numero DEFAULT (NEXT VALUE FOR seq_solicitudes),
    cliente_id INT NOT NULL,
    version_condiciones_id INT NOT NULL,
    simulacion_id INT NULL,
    origen VARCHAR(15) NOT NULL,
    fecha_registro DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_solicitudes_credito_fecha_registro DEFAULT (SYSDATETIMEOFFSET()),
    importe DECIMAL(19,2) NOT NULL,
    cantidad_cuotas SMALLINT NOT NULL,
    estado VARCHAR(30) NOT NULL CONSTRAINT DF_solicitudes_credito_estado DEFAULT ('PendienteVerificacion'),
    tipo_rechazo VARCHAR(10) NULL,
    motivo_cancelacion NVARCHAR(500) NULL,
    ultimo_movimiento DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_solicitudes_credito_ultimo_movimiento DEFAULT (SYSDATETIMEOFFSET()),
    usuario_id INT NOT NULL,
    version_fila INT NOT NULL CONSTRAINT DF_solicitudes_credito_version_fila DEFAULT (1),
    CONSTRAINT PK_solicitudes_credito PRIMARY KEY (id),
    CONSTRAINT UQ_solicitudes_credito_numero UNIQUE (numero),
    CONSTRAINT CK_solicitudes_credito_origen CHECK (origen IN ('Independiente', 'Oferta')),
    CONSTRAINT CK_solicitudes_credito_estado CHECK (estado IN ('PendienteVerificacion', 'PendienteDocumentacion', 'PendienteEvaluacion', 'EnEvaluacion', 'PendienteContingencia', 'PendienteSeleccionPlan', 'PendienteResolucion', 'DevueltaRevision', 'AprobadaPendienteLiquidacion', 'Liquidada', 'Rechazada', 'Cancelada', 'Vencida')),
    CONSTRAINT CK_solicitudes_credito_tipo_rechazo CHECK (tipo_rechazo IS NULL OR tipo_rechazo IN ('Documental', 'Crediticio')),
    CONSTRAINT CK_solicitudes_rechazo CHECK ((estado = 'Rechazada' AND tipo_rechazo IS NOT NULL) OR (estado <> 'Rechazada' AND tipo_rechazo IS NULL)),
    CONSTRAINT CK_solicitudes_cancelacion CHECK (estado <> 'Cancelada' OR motivo_cancelacion IS NOT NULL),
    CONSTRAINT CK_solicitudes_importe CHECK (importe > 0 AND cantidad_cuotas > 0)
);
GO
-- transiciones_solicitud: Historial de cambios de estado de la solicitud: origen, destino, instante, responsable y motivo (3.5.3).
CREATE TABLE dbo.transiciones_solicitud (
    id BIGINT IDENTITY(1,1) NOT NULL,
    solicitud_id INT NOT NULL,
    estado_origen VARCHAR(30) NULL,
    estado_destino VARCHAR(30) NOT NULL,
    fecha_hora DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_transiciones_solicitud_fecha_hora DEFAULT (SYSDATETIMEOFFSET()),
    usuario_id INT NULL,
    proceso VARCHAR(60) NULL,
    motivo NVARCHAR(500) NULL,
    CONSTRAINT PK_transiciones_solicitud PRIMARY KEY (id),
    CONSTRAINT CK_transiciones_solicitud_estado_origen CHECK (estado_origen IS NULL OR estado_origen IN ('PendienteVerificacion', 'PendienteDocumentacion', 'PendienteEvaluacion', 'EnEvaluacion', 'PendienteContingencia', 'PendienteSeleccionPlan', 'PendienteResolucion', 'DevueltaRevision', 'AprobadaPendienteLiquidacion', 'Liquidada', 'Rechazada', 'Cancelada', 'Vencida')),
    CONSTRAINT CK_transiciones_solicitud_estado_destino CHECK (estado_destino IN ('PendienteVerificacion', 'PendienteDocumentacion', 'PendienteEvaluacion', 'EnEvaluacion', 'PendienteContingencia', 'PendienteSeleccionPlan', 'PendienteResolucion', 'DevueltaRevision', 'AprobadaPendienteLiquidacion', 'Liquidada', 'Rechazada', 'Cancelada', 'Vencida')),
    CONSTRAINT CK_transiciones_responsable CHECK (usuario_id IS NOT NULL OR proceso IS NOT NULL)
);
GO
-- revisiones_documentales: Verificaciones de la documentación de una solicitud para su versión de condiciones (3.5.4).
CREATE TABLE dbo.revisiones_documentales (
    id INT IDENTITY(1,1) NOT NULL,
    solicitud_id INT NOT NULL,
    version_condiciones_id INT NOT NULL,
    fecha DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_revisiones_documentales_fecha DEFAULT (SYSDATETIMEOFFSET()),
    estado VARCHAR(20) NOT NULL CONSTRAINT DF_revisiones_documentales_estado DEFAULT ('PendienteRevision'),
    observaciones NVARCHAR(1000) NULL,
    motivo_rechazo NVARCHAR(500) NULL,
    usuario_id INT NULL,
    CONSTRAINT PK_revisiones_documentales PRIMARY KEY (id),
    CONSTRAINT CK_revisiones_documentales_estado CHECK (estado IN ('PendienteRevision', 'Observada', 'Verificada', 'Rechazada')),
    CONSTRAINT CK_revisiones_rechazo CHECK (estado <> 'Rechazada' OR motivo_rechazo IS NOT NULL)
);
GO
-- verificaciones_documento: Resultado de la verificación de cada documento del legajo dentro de una revisión.
CREATE TABLE dbo.verificaciones_documento (
    id INT IDENTITY(1,1) NOT NULL,
    revision_documental_id INT NOT NULL,
    documento_legajo_id INT NOT NULL,
    resultado VARCHAR(10) NOT NULL,
    motivo_observacion VARCHAR(15) NULL,
    observacion NVARCHAR(500) NULL,
    fecha_verificacion DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_verificaciones_documento_fecha_verificacion DEFAULT (SYSDATETIMEOFFSET()),
    CONSTRAINT PK_verificaciones_documento PRIMARY KEY (id),
    CONSTRAINT UQ_verificaciones_documento UNIQUE (revision_documental_id, documento_legajo_id),
    CONSTRAINT CK_verificaciones_documento_resultado CHECK (resultado IN ('Conforme', 'Observado', 'Rechazado')),
    CONSTRAINT CK_verificaciones_documento_motivo_observacion CHECK (motivo_observacion IS NULL OR motivo_observacion IN ('Faltante', 'Ilegible', 'Vencido', 'Inconsistente', 'NoCorresponde')),
    CONSTRAINT CK_verificaciones_motivo CHECK (resultado = 'Conforme' OR motivo_observacion IS NOT NULL)
);
GO
-- evaluaciones_crediticias: Análisis de riesgo con sus dimensiones independientes (3.5.5). La contingencia es una nueva fila vinculada con el intento no completado (RN-21).
CREATE TABLE dbo.evaluaciones_crediticias (
    id INT IDENTITY(1,1) NOT NULL,
    solicitud_id INT NOT NULL,
    version_parametros_id INT NOT NULL,
    evaluacion_origen_id INT NULL,
    modalidad VARCHAR(15) NOT NULL,
    procesamiento VARCHAR(15) NOT NULL CONSTRAINT DF_evaluaciones_crediticias_procesamiento DEFAULT ('Pendiente'),
    resultado VARCHAR(25) NULL,
    puntaje DECIMAL(5,2) NULL,
    categoria_riesgo CHAR(1) NULL,
    indicadores NVARCHAR(MAX) NULL,
    fecha_inicio DATETIMEOFFSET(3) NULL,
    fecha_finalizacion DATETIMEOFFSET(3) NULL,
    vence DATETIMEOFFSET(3) NULL,
    vigencia VARCHAR(10) NULL,
    motivo_no_completada NVARCHAR(500) NULL,
    recomendacion NVARCHAR(1000) NULL,
    justificacion NVARCHAR(2000) NULL,
    usuario_id INT NULL,
    version_fila INT NOT NULL CONSTRAINT DF_evaluaciones_crediticias_version_fila DEFAULT (1),
    CONSTRAINT PK_evaluaciones_crediticias PRIMARY KEY (id),
    CONSTRAINT CK_evaluaciones_crediticias_modalidad CHECK (modalidad IN ('Automatica', 'Contingencia')),
    CONSTRAINT CK_evaluaciones_crediticias_procesamiento CHECK (procesamiento IN ('Pendiente', 'EnProceso', 'Finalizada', 'NoCompletada')),
    CONSTRAINT CK_evaluaciones_crediticias_resultado CHECK (resultado IS NULL OR resultado IN ('Favorable', 'Desfavorable', 'RequiereAnalisisGerencial')),
    CONSTRAINT CK_evaluaciones_crediticias_categoria_riesgo CHECK (categoria_riesgo IS NULL OR categoria_riesgo IN ('A', 'B', 'C', 'D', 'E')),
    CONSTRAINT CK_evaluaciones_crediticias_vigencia CHECK (vigencia IS NULL OR vigencia IN ('Vigente', 'Vencida', 'Invalidada', 'Sustituida')),
    CONSTRAINT CK_evaluaciones_finalizada CHECK ((procesamiento = 'Finalizada' AND resultado IS NOT NULL AND vigencia IS NOT NULL AND vence IS NOT NULL) OR (procesamiento <> 'Finalizada' AND resultado IS NULL AND vigencia IS NULL)),
    CONSTRAINT CK_evaluaciones_contingencia CHECK (modalidad = 'Automatica' OR (evaluacion_origen_id IS NOT NULL AND recomendacion IS NOT NULL AND justificacion IS NOT NULL AND usuario_id IS NOT NULL AND (resultado IS NULL OR resultado = 'RequiereAnalisisGerencial'))),
    CONSTRAINT CK_evaluaciones_puntaje CHECK (puntaje IS NULL OR puntaje BETWEEN 0 AND 100),
    CONSTRAINT CK_evaluaciones_indicadores CHECK (indicadores IS NULL OR ISJSON(indicadores) = 1)
);
GO
-- antecedentes_externos: Información obtenida de la Central de Deudores para una evaluación.
CREATE TABLE dbo.antecedentes_externos (
    id BIGINT IDENTITY(1,1) NOT NULL,
    evaluacion_id INT NOT NULL,
    fuente VARCHAR(30) NOT NULL,
    entidad NVARCHAR(150) NOT NULL,
    situacion TINYINT NOT NULL,
    monto DECIMAL(19,2) NOT NULL,
    periodo CHAR(6) NOT NULL,
    CONSTRAINT PK_antecedentes_externos PRIMARY KEY (id),
    CONSTRAINT CK_antecedentes_situacion CHECK (situacion BETWEEN 1 AND 6)
);
GO
-- estructuraciones: Determinación de la capacidad de pago y del monto máximo financiable (RF-14, CU-87).
CREATE TABLE dbo.estructuraciones (
    id INT IDENTITY(1,1) NOT NULL,
    solicitud_id INT NOT NULL,
    version_parametros_id INT NOT NULL,
    fecha DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_estructuraciones_fecha DEFAULT (SYSDATETIMEOFFSET()),
    ingresos_considerados DECIMAL(19,2) NOT NULL,
    obligaciones_vigentes DECIMAL(19,2) NOT NULL,
    capacidad_pago DECIMAL(19,2) NOT NULL,
    monto_maximo_financiable DECIMAL(19,2) NOT NULL,
    CONSTRAINT PK_estructuraciones PRIMARY KEY (id)
);
GO
-- alternativas_financiacion: Combinaciones viables de importe y plazo (RN-23, RN-48).
CREATE TABLE dbo.alternativas_financiacion (
    id INT IDENTITY(1,1) NOT NULL,
    estructuracion_id INT NOT NULL,
    importe DECIMAL(19,2) NOT NULL,
    cantidad_cuotas SMALLINT NOT NULL,
    valor_cuota DECIMAL(19,2) NOT NULL,
    tea DECIMAL(18,12) NOT NULL,
    cftea DECIMAL(18,12) NOT NULL,
    total_a_pagar DECIMAL(19,2) NOT NULL,
    vence DATETIMEOFFSET(3) NOT NULL,
    estado VARCHAR(10) NOT NULL CONSTRAINT DF_alternativas_financiacion_estado DEFAULT ('Vigente'),
    motivo_descarte NVARCHAR(300) NULL,
    fecha_conformidad DATETIMEOFFSET(3) NULL,
    medio_conformidad NVARCHAR(100) NULL,
    CONSTRAINT PK_alternativas_financiacion PRIMARY KEY (id),
    CONSTRAINT CK_alternativas_financiacion_estado CHECK (estado IN ('Vigente', 'Confirmada', 'Descartada', 'Vencida')),
    CONSTRAINT CK_alternativas_conformidad CHECK (estado <> 'Confirmada' OR fecha_conformidad IS NOT NULL)
);
GO
-- resoluciones: Decisiones sobre una solicitud, automáticas o gerenciales. La aprobación conserva su vencimiento calculado (RN-49).
CREATE TABLE dbo.resoluciones (
    id INT IDENTITY(1,1) NOT NULL,
    solicitud_id INT NOT NULL,
    decision VARCHAR(20) NOT NULL,
    fundamento NVARCHAR(2000) NULL,
    fecha DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_resoluciones_fecha DEFAULT (SYSDATETIMEOFFSET()),
    automatica BIT NOT NULL CONSTRAINT DF_resoluciones_automatica DEFAULT (0),
    usuario_id INT NULL,
    alternativa_id INT NULL,
    importe_aprobado DECIMAL(19,2) NULL,
    cuotas_aprobadas SMALLINT NULL,
    vence_aprobacion DATETIMEOFFSET(3) NULL,
    vigente BIT NOT NULL CONSTRAINT DF_resoluciones_vigente DEFAULT (1),
    etapa_retorno VARCHAR(15) NULL,
    CONSTRAINT PK_resoluciones PRIMARY KEY (id),
    CONSTRAINT CK_resoluciones_decision CHECK (decision IN ('Aprobada', 'Rechazada', 'DevueltaRevision')),
    CONSTRAINT CK_resoluciones_etapa_retorno CHECK (etapa_retorno IS NULL OR etapa_retorno IN ('Verificacion', 'Evaluacion', 'SeleccionPlan', 'Resolucion')),
    CONSTRAINT CK_resoluciones_responsable CHECK ((automatica = 1 AND usuario_id IS NULL) OR (automatica = 0 AND usuario_id IS NOT NULL AND fundamento IS NOT NULL)),
    CONSTRAINT CK_resoluciones_aprobacion CHECK (decision <> 'Aprobada' OR (vence_aprobacion IS NOT NULL AND importe_aprobado IS NOT NULL AND cuotas_aprobadas IS NOT NULL)),
    CONSTRAINT CK_resoluciones_devolucion CHECK (decision <> 'DevueltaRevision' OR etapa_retorno IS NOT NULL)
);
GO
-- liquidaciones: Registro del desembolso. Una sola liquidación por solicitud (RNF-06, RN-25).
CREATE TABLE dbo.liquidaciones (
    id INT IDENTITY(1,1) NOT NULL,
    solicitud_id INT NOT NULL,
    credito_id INT NOT NULL,
    fecha DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_liquidaciones_fecha DEFAULT (SYSDATETIMEOFFSET()),
    capital DECIMAL(19,2) NOT NULL,
    gasto_otorgamiento DECIMAL(19,2) NOT NULL,
    iva_gastos DECIMAL(19,2) NOT NULL,
    importe_neto DECIMAL(19,2) NOT NULL,
    medio_desembolso VARCHAR(15) NOT NULL,
    cuenta_destino CHAR(22) NULL,
    comprobante VARCHAR(100) NULL,
    movimiento_caja_id BIGINT NULL,
    usuario_id INT NOT NULL,
    CONSTRAINT PK_liquidaciones PRIMARY KEY (id),
    CONSTRAINT UQ_liquidaciones_solicitud_id UNIQUE (solicitud_id),
    CONSTRAINT UQ_liquidaciones_credito_id UNIQUE (credito_id),
    CONSTRAINT CK_liquidaciones_medio_desembolso CHECK (medio_desembolso IN ('Efectivo', 'Transferencia')),
    CONSTRAINT CK_liquidaciones_neto CHECK (importe_neto = capital - gasto_otorgamiento - iva_gastos AND importe_neto > 0),
    CONSTRAINT CK_liquidaciones_medio CHECK ((medio_desembolso = 'Transferencia' AND cuenta_destino IS NOT NULL AND movimiento_caja_id IS NULL) OR (medio_desembolso = 'Efectivo' AND movimiento_caja_id IS NOT NULL AND cuenta_destino IS NULL))
);
GO
ALTER TABLE dbo.simulaciones ADD CONSTRAINT FK_simulaciones_cliente_id FOREIGN KEY (cliente_id) REFERENCES dbo.clientes (id);
ALTER TABLE dbo.simulaciones ADD CONSTRAINT FK_simulaciones_version_condiciones_id FOREIGN KEY (version_condiciones_id) REFERENCES dbo.versiones_condiciones (id);
ALTER TABLE dbo.solicitudes_credito ADD CONSTRAINT FK_solicitudes_credito_cliente_id FOREIGN KEY (cliente_id) REFERENCES dbo.clientes (id);
ALTER TABLE dbo.solicitudes_credito ADD CONSTRAINT FK_solicitudes_credito_version_condiciones_id FOREIGN KEY (version_condiciones_id) REFERENCES dbo.versiones_condiciones (id);
ALTER TABLE dbo.solicitudes_credito ADD CONSTRAINT FK_solicitudes_credito_simulacion_id FOREIGN KEY (simulacion_id) REFERENCES dbo.simulaciones (id);
ALTER TABLE dbo.solicitudes_credito ADD CONSTRAINT FK_solicitudes_credito_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.transiciones_solicitud ADD CONSTRAINT FK_transiciones_solicitud_solicitud_id FOREIGN KEY (solicitud_id) REFERENCES dbo.solicitudes_credito (id);
ALTER TABLE dbo.transiciones_solicitud ADD CONSTRAINT FK_transiciones_solicitud_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.revisiones_documentales ADD CONSTRAINT FK_revisiones_documentales_solicitud_id FOREIGN KEY (solicitud_id) REFERENCES dbo.solicitudes_credito (id);
ALTER TABLE dbo.revisiones_documentales ADD CONSTRAINT FK_revisiones_documentales_version_condiciones_id FOREIGN KEY (version_condiciones_id) REFERENCES dbo.versiones_condiciones (id);
ALTER TABLE dbo.revisiones_documentales ADD CONSTRAINT FK_revisiones_documentales_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.verificaciones_documento ADD CONSTRAINT FK_verificaciones_documento_revision_documental_id FOREIGN KEY (revision_documental_id) REFERENCES dbo.revisiones_documentales (id);
ALTER TABLE dbo.verificaciones_documento ADD CONSTRAINT FK_verificaciones_documento_documento_legajo_id FOREIGN KEY (documento_legajo_id) REFERENCES dbo.documentos_legajo (id);
ALTER TABLE dbo.evaluaciones_crediticias ADD CONSTRAINT FK_evaluaciones_crediticias_solicitud_id FOREIGN KEY (solicitud_id) REFERENCES dbo.solicitudes_credito (id);
ALTER TABLE dbo.evaluaciones_crediticias ADD CONSTRAINT FK_evaluaciones_crediticias_version_parametros_id FOREIGN KEY (version_parametros_id) REFERENCES dbo.versiones_parametros (id);
ALTER TABLE dbo.evaluaciones_crediticias ADD CONSTRAINT FK_evaluaciones_crediticias_evaluacion_origen_id FOREIGN KEY (evaluacion_origen_id) REFERENCES dbo.evaluaciones_crediticias (id);
ALTER TABLE dbo.evaluaciones_crediticias ADD CONSTRAINT FK_evaluaciones_crediticias_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.antecedentes_externos ADD CONSTRAINT FK_antecedentes_externos_evaluacion_id FOREIGN KEY (evaluacion_id) REFERENCES dbo.evaluaciones_crediticias (id);
ALTER TABLE dbo.estructuraciones ADD CONSTRAINT FK_estructuraciones_solicitud_id FOREIGN KEY (solicitud_id) REFERENCES dbo.solicitudes_credito (id);
ALTER TABLE dbo.estructuraciones ADD CONSTRAINT FK_estructuraciones_version_parametros_id FOREIGN KEY (version_parametros_id) REFERENCES dbo.versiones_parametros (id);
ALTER TABLE dbo.alternativas_financiacion ADD CONSTRAINT FK_alternativas_financiacion_estructuracion_id FOREIGN KEY (estructuracion_id) REFERENCES dbo.estructuraciones (id);
ALTER TABLE dbo.resoluciones ADD CONSTRAINT FK_resoluciones_solicitud_id FOREIGN KEY (solicitud_id) REFERENCES dbo.solicitudes_credito (id);
ALTER TABLE dbo.resoluciones ADD CONSTRAINT FK_resoluciones_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.resoluciones ADD CONSTRAINT FK_resoluciones_alternativa_id FOREIGN KEY (alternativa_id) REFERENCES dbo.alternativas_financiacion (id);
ALTER TABLE dbo.liquidaciones ADD CONSTRAINT FK_liquidaciones_solicitud_id FOREIGN KEY (solicitud_id) REFERENCES dbo.solicitudes_credito (id);
ALTER TABLE dbo.liquidaciones ADD CONSTRAINT FK_liquidaciones_credito_id FOREIGN KEY (credito_id) REFERENCES dbo.creditos (id);
ALTER TABLE dbo.liquidaciones ADD CONSTRAINT FK_liquidaciones_movimiento_caja_id FOREIGN KEY (movimiento_caja_id) REFERENCES dbo.movimientos_caja (id);
ALTER TABLE dbo.liquidaciones ADD CONSTRAINT FK_liquidaciones_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
CREATE INDEX IX_simulaciones_cliente ON dbo.simulaciones (cliente_id, fecha_generacion);
CREATE INDEX IX_simulaciones_version_condiciones_id ON dbo.simulaciones (version_condiciones_id);
CREATE INDEX IX_solicitudes_cliente ON dbo.solicitudes_credito (cliente_id, estado);
CREATE INDEX IX_solicitudes_estado ON dbo.solicitudes_credito (estado, ultimo_movimiento);
CREATE INDEX IX_solicitudes_credito_version_condiciones_id ON dbo.solicitudes_credito (version_condiciones_id);
CREATE INDEX IX_solicitudes_credito_simulacion_id ON dbo.solicitudes_credito (simulacion_id);
CREATE INDEX IX_solicitudes_credito_usuario_id ON dbo.solicitudes_credito (usuario_id);
CREATE INDEX IX_transiciones_solicitud ON dbo.transiciones_solicitud (solicitud_id, fecha_hora);
CREATE INDEX IX_transiciones_solicitud_usuario_id ON dbo.transiciones_solicitud (usuario_id);
CREATE INDEX IX_revisiones_solicitud ON dbo.revisiones_documentales (solicitud_id, fecha);
CREATE INDEX IX_revisiones_documentales_version_condiciones_id ON dbo.revisiones_documentales (version_condiciones_id);
CREATE INDEX IX_revisiones_documentales_usuario_id ON dbo.revisiones_documentales (usuario_id);
CREATE INDEX IX_verificaciones_documento_documento_legajo_id ON dbo.verificaciones_documento (documento_legajo_id);
CREATE UNIQUE INDEX UQ_evaluaciones_crediticias_evaluacion_origen_id ON dbo.evaluaciones_crediticias (evaluacion_origen_id) WHERE evaluacion_origen_id IS NOT NULL;
CREATE UNIQUE INDEX UQ_evaluaciones_vigente ON dbo.evaluaciones_crediticias (solicitud_id) WHERE vigencia = 'Vigente';
CREATE INDEX IX_evaluaciones_pendientes ON dbo.evaluaciones_crediticias (procesamiento) WHERE procesamiento = 'Pendiente';
CREATE INDEX IX_evaluaciones_vencimiento ON dbo.evaluaciones_crediticias (vence) WHERE vigencia = 'Vigente';
CREATE INDEX IX_evaluaciones_crediticias_version_parametros_id ON dbo.evaluaciones_crediticias (version_parametros_id);
CREATE INDEX IX_evaluaciones_crediticias_usuario_id ON dbo.evaluaciones_crediticias (usuario_id);
CREATE INDEX IX_antecedentes_evaluacion ON dbo.antecedentes_externos (evaluacion_id);
CREATE INDEX IX_estructuraciones_solicitud ON dbo.estructuraciones (solicitud_id, fecha);
CREATE INDEX IX_estructuraciones_version_parametros_id ON dbo.estructuraciones (version_parametros_id);
CREATE UNIQUE INDEX UQ_alternativas_confirmada ON dbo.alternativas_financiacion (estructuracion_id) WHERE estado = 'Confirmada';
CREATE UNIQUE INDEX UQ_resoluciones_aprobacion_vigente ON dbo.resoluciones (solicitud_id) WHERE decision = 'Aprobada' AND vigente = 1;
CREATE INDEX IX_resoluciones_vencimiento ON dbo.resoluciones (vence_aprobacion) WHERE decision = 'Aprobada' AND vigente = 1;
CREATE INDEX IX_resoluciones_usuario_id ON dbo.resoluciones (usuario_id);
CREATE INDEX IX_resoluciones_alternativa_id ON dbo.resoluciones (alternativa_id);
CREATE UNIQUE INDEX UQ_liquidaciones_movimiento_caja_id ON dbo.liquidaciones (movimiento_caja_id) WHERE movimiento_caja_id IS NOT NULL;
CREATE INDEX IX_liquidaciones_usuario_id ON dbo.liquidaciones (usuario_id);
