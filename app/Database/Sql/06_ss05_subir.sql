/* SS-05 Caja, cobranzas, pagos y fondos: generado desde la especificación del modelo de datos (4.3) */
GO
-- cajas: Unidades operativas de cobro (CU-80, CU-81).
CREATE TABLE dbo.cajas (
    id INT IDENTITY(1,1) NOT NULL,
    codigo VARCHAR(10) NOT NULL,
    denominacion NVARCHAR(60) NOT NULL,
    ubicacion NVARCHAR(100) NULL,
    estado VARCHAR(15) NOT NULL CONSTRAINT DF_cajas_estado DEFAULT ('Habilitada'),
    CONSTRAINT PK_cajas PRIMARY KEY (id),
    CONSTRAINT UQ_cajas_codigo UNIQUE (codigo),
    CONSTRAINT CK_cajas_estado CHECK (estado IN ('Habilitada', 'Inhabilitada'))
);
GO
-- asignaciones_caja: Asignación de una caja a un cajero; vigente mientras hasta es nulo (RN-28).
CREATE TABLE dbo.asignaciones_caja (
    id INT IDENTITY(1,1) NOT NULL,
    caja_id INT NOT NULL,
    usuario_id INT NOT NULL,
    desde DATE NOT NULL,
    hasta DATE NULL,
    CONSTRAINT PK_asignaciones_caja PRIMARY KEY (id),
    CONSTRAINT UQ_asignaciones_caja_clave UNIQUE (id, caja_id, usuario_id),
    CONSTRAINT CK_asignaciones_caja_periodo CHECK (hasta IS NULL OR hasta >= desde)
);
GO
-- jornadas_caja: Operaciones entre la apertura y el cierre de una caja. caja_id y usuario_id se repiten desde la asignación para impedir, con índices únicos, dos jornadas abiertas por caja o por cajero (RN-28).
CREATE TABLE dbo.jornadas_caja (
    id INT IDENTITY(1,1) NOT NULL,
    asignacion_caja_id INT NOT NULL,
    caja_id INT NOT NULL,
    usuario_id INT NOT NULL,
    apertura DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_jornadas_caja_apertura DEFAULT (SYSDATETIMEOFFSET()),
    cierre DATETIMEOFFSET(3) NULL,
    fondo_inicial DECIMAL(19,2) NOT NULL,
    efectivo_esperado DECIMAL(19,2) NULL,
    efectivo_declarado DECIMAL(19,2) NULL,
    diferencia DECIMAL(19,2) NULL,
    explicacion NVARCHAR(500) NULL,
    estado VARCHAR(10) NOT NULL CONSTRAINT DF_jornadas_caja_estado DEFAULT ('Abierta'),
    version_fila INT NOT NULL CONSTRAINT DF_jornadas_caja_version_fila DEFAULT (1),
    CONSTRAINT PK_jornadas_caja PRIMARY KEY (id),
    CONSTRAINT CK_jornadas_caja_estado CHECK (estado IN ('Abierta', 'Cerrada')),
    CONSTRAINT CK_jornadas_caja_cierre CHECK ((estado = 'Abierta' AND cierre IS NULL) OR (estado = 'Cerrada' AND cierre IS NOT NULL AND efectivo_declarado IS NOT NULL AND diferencia IS NOT NULL)),
    CONSTRAINT CK_jornadas_caja_diferencia CHECK (diferencia IS NULL OR diferencia = 0 OR explicacion IS NOT NULL),
    CONSTRAINT CK_jornadas_caja_fondo CHECK (fondo_inicial >= 0)
);
GO
-- cobros: Pagos confirmados de una o más cuotas, en ventanilla o electrónicos (RN-29, RN-30).
CREATE TABLE dbo.cobros (
    id BIGINT IDENTITY(1,1) NOT NULL,
    numero BIGINT NOT NULL CONSTRAINT DF_cobros_numero DEFAULT (NEXT VALUE FOR seq_cobros),
    cliente_id INT NOT NULL,
    jornada_caja_id INT NULL,
    transaccion_pasarela_id BIGINT NULL,
    fecha_hora DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_cobros_fecha_hora DEFAULT (SYSDATETIMEOFFSET()),
    fecha_efectiva_pago DATETIMEOFFSET(3) NOT NULL,
    importe DECIMAL(19,2) NOT NULL,
    medio VARCHAR(15) NOT NULL,
    numero_operacion VARCHAR(60) NULL,
    tipo VARCHAR(25) NOT NULL CONSTRAINT DF_cobros_tipo DEFAULT ('Ordinario'),
    canal VARCHAR(15) NOT NULL,
    estado VARCHAR(10) NOT NULL CONSTRAINT DF_cobros_estado DEFAULT ('Confirmado'),
    usuario_id INT NULL,
    CONSTRAINT PK_cobros PRIMARY KEY (id),
    CONSTRAINT UQ_cobros_numero UNIQUE (numero),
    CONSTRAINT CK_cobros_medio CHECK (medio IN ('Efectivo', 'TarjetaDebito', 'TarjetaCredito', 'Transferencia', 'PagoElectronico')),
    CONSTRAINT CK_cobros_tipo CHECK (tipo IN ('Ordinario', 'CancelacionAnticipada')),
    CONSTRAINT CK_cobros_canal CHECK (canal IN ('Ventanilla', 'Electronico')),
    CONSTRAINT CK_cobros_estado CHECK (estado IN ('Confirmado', 'Anulado')),
    CONSTRAINT CK_cobros_canal_datos CHECK ((canal = 'Ventanilla' AND jornada_caja_id IS NOT NULL AND usuario_id IS NOT NULL AND medio <> 'PagoElectronico') OR (canal = 'Electronico' AND transaccion_pasarela_id IS NOT NULL AND medio = 'PagoElectronico')),
    CONSTRAINT CK_cobros_operacion CHECK (medio = 'Efectivo' OR numero_operacion IS NOT NULL OR canal = 'Electronico'),
    CONSTRAINT CK_cobros_importe CHECK (importe > 0)
);
GO
-- movimientos_caja: Ingresos y egresos de una jornada (RN-28, RN-32).
CREATE TABLE dbo.movimientos_caja (
    id BIGINT IDENTITY(1,1) NOT NULL,
    jornada_caja_id INT NOT NULL,
    fecha_hora DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_movimientos_caja_fecha_hora DEFAULT (SYSDATETIMEOFFSET()),
    tipo VARCHAR(15) NOT NULL,
    importe DECIMAL(19,2) NOT NULL,
    medio VARCHAR(15) NOT NULL,
    concepto NVARCHAR(200) NULL,
    cobro_id BIGINT NULL,
    CONSTRAINT PK_movimientos_caja PRIMARY KEY (id),
    CONSTRAINT CK_movimientos_caja_tipo CHECK (tipo IN ('FondoInicial', 'Cobro', 'Desembolso')),
    CONSTRAINT CK_movimientos_caja_medio CHECK (medio IN ('Efectivo', 'TarjetaDebito', 'TarjetaCredito', 'Transferencia', 'PagoElectronico')),
    CONSTRAINT CK_movimientos_caja_importe CHECK (importe > 0),
    CONSTRAINT CK_movimientos_caja_cobro CHECK ((tipo = 'Cobro' AND cobro_id IS NOT NULL) OR (tipo <> 'Cobro' AND cobro_id IS NULL)),
    CONSTRAINT CK_movimientos_caja_desembolso CHECK (tipo <> 'Desembolso' OR medio = 'Efectivo')
);
GO
-- imputaciones: Distribución del importe de un cobro o de una aplicación de saldo entre cuotas y conceptos, según la prelación de RN-29.
CREATE TABLE dbo.imputaciones (
    id BIGINT IDENTITY(1,1) NOT NULL,
    cobro_id BIGINT NULL,
    aplicacion_saldo_id INT NULL,
    cuota_id BIGINT NOT NULL,
    concepto VARCHAR(20) NOT NULL,
    importe DECIMAL(19,2) NOT NULL,
    parcial_excepcional BIT NOT NULL CONSTRAINT DF_imputaciones_parcial_excepcional DEFAULT (0),
    CONSTRAINT PK_imputaciones PRIMARY KEY (id),
    CONSTRAINT CK_imputaciones_concepto CHECK (concepto IN ('Punitorio', 'InteresOrdinario', 'GastosCargos', 'IVA', 'NoCapitalizable', 'Capital')),
    CONSTRAINT CK_imputaciones_origen CHECK ((cobro_id IS NOT NULL AND aplicacion_saldo_id IS NULL) OR (cobro_id IS NULL AND aplicacion_saldo_id IS NOT NULL)),
    CONSTRAINT CK_imputaciones_importe CHECK (importe > 0)
);
GO
-- comprobantes_pago: Constancia de un cobro confirmado; su reemisión no genera un nuevo cobro (RN-31).
CREATE TABLE dbo.comprobantes_pago (
    id BIGINT IDENTITY(1,1) NOT NULL,
    cobro_id BIGINT NOT NULL,
    numero BIGINT NOT NULL CONSTRAINT DF_comprobantes_pago_numero DEFAULT (NEXT VALUE FOR seq_comprobantes),
    fecha_emision DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_comprobantes_pago_fecha_emision DEFAULT (SYSDATETIMEOFFSET()),
    cantidad_reemisiones INT NOT NULL CONSTRAINT DF_comprobantes_pago_cantidad_reemisiones DEFAULT (0),
    CONSTRAINT PK_comprobantes_pago PRIMARY KEY (id),
    CONSTRAINT UQ_comprobantes_pago_cobro_id UNIQUE (cobro_id),
    CONSTRAINT UQ_comprobantes_pago_numero UNIQUE (numero)
);
GO
-- anulaciones_cobro: Anulación gerencial de un cobro; conserva el original (RN-33).
CREATE TABLE dbo.anulaciones_cobro (
    id INT IDENTITY(1,1) NOT NULL,
    cobro_id BIGINT NOT NULL,
    fecha DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_anulaciones_cobro_fecha DEFAULT (SYSDATETIMEOFFSET()),
    motivo NVARCHAR(500) NOT NULL,
    usuario_id INT NOT NULL,
    saldo_a_favor_id INT NULL,
    CONSTRAINT PK_anulaciones_cobro PRIMARY KEY (id),
    CONSTRAINT UQ_anulaciones_cobro_cobro_id UNIQUE (cobro_id)
);
GO
-- ordenes_pago_electronico: Solicitudes de pago de importe fijo enviadas a la pasarela (RN-54).
CREATE TABLE dbo.ordenes_pago_electronico (
    id BIGINT IDENTITY(1,1) NOT NULL,
    numero BIGINT NOT NULL CONSTRAINT DF_ordenes_pago_electronico_numero DEFAULT (NEXT VALUE FOR seq_ordenes),
    cliente_id INT NOT NULL,
    importe DECIMAL(19,2) NOT NULL,
    emision DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_ordenes_pago_electronico_emision DEFAULT (SYSDATETIMEOFFSET()),
    vence DATETIMEOFFSET(3) NOT NULL,
    referencia_externa VARCHAR(100) NULL,
    medio_presentado VARCHAR(30) NULL,
    estado VARCHAR(25) NOT NULL CONSTRAINT DF_ordenes_pago_electronico_estado DEFAULT ('PendienteConfirmacion'),
    version_fila INT NOT NULL CONSTRAINT DF_ordenes_pago_electronico_version_fila DEFAULT (1),
    CONSTRAINT PK_ordenes_pago_electronico PRIMARY KEY (id),
    CONSTRAINT UQ_ordenes_pago_electronico_numero UNIQUE (numero),
    CONSTRAINT CK_ordenes_pago_electronico_estado CHECK (estado IN ('PendienteConfirmacion', 'Confirmada', 'Rechazada', 'EnConciliacion', 'Vencida')),
    CONSTRAINT CK_ordenes_importe CHECK (importe > 0 AND vence > emision)
);
GO
-- ordenes_pago_cuotas: Cuotas incluidas en una orden y su importe al emitirla.
CREATE TABLE dbo.ordenes_pago_cuotas (
    orden_pago_id BIGINT NOT NULL,
    cuota_id BIGINT NOT NULL,
    importe DECIMAL(19,2) NOT NULL,
    CONSTRAINT PK_ordenes_pago_cuotas PRIMARY KEY (orden_pago_id, cuota_id)
);
GO
-- notificaciones_pasarela: Bandeja de entrada de notificaciones de la pasarela: se registran y se responde el acuse; CU-90 las procesa en segundo plano (RNF-08).
CREATE TABLE dbo.notificaciones_pasarela (
    id BIGINT IDENTITY(1,1) NOT NULL,
    recibida_en DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_notificaciones_pasarela_recibida_en DEFAULT (SYSDATETIMEOFFSET()),
    id_evento VARCHAR(100) NULL,
    firma_valida BIT NOT NULL,
    cuerpo NVARCHAR(MAX) NOT NULL,
    estado VARCHAR(10) NOT NULL CONSTRAINT DF_notificaciones_pasarela_estado DEFAULT ('Pendiente'),
    intentos TINYINT NOT NULL CONSTRAINT DF_notificaciones_pasarela_intentos DEFAULT (0),
    procesada_en DATETIMEOFFSET(3) NULL,
    error NVARCHAR(500) NULL,
    CONSTRAINT PK_notificaciones_pasarela PRIMARY KEY (id),
    CONSTRAINT CK_notificaciones_pasarela_estado CHECK (estado IN ('Pendiente', 'Procesada', 'Descartada', 'Error'))
);
GO
-- transacciones_pasarela: Transacciones informadas por la pasarela; el identificador externo es único para impedir registros duplicados (RN-30, RNF-06).
CREATE TABLE dbo.transacciones_pasarela (
    id BIGINT IDENTITY(1,1) NOT NULL,
    orden_pago_id BIGINT NOT NULL,
    id_externo VARCHAR(100) NOT NULL,
    estado VARCHAR(30) NOT NULL,
    importe DECIMAL(19,2) NOT NULL,
    moneda CHAR(3) NOT NULL CONSTRAINT DF_transacciones_pasarela_moneda DEFAULT ('ARS'),
    fecha_efectiva DATETIMEOFFSET(3) NOT NULL,
    fecha_recepcion DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_transacciones_pasarela_fecha_recepcion DEFAULT (SYSDATETIMEOFFSET()),
    CONSTRAINT PK_transacciones_pasarela PRIMARY KEY (id),
    CONSTRAINT UQ_transacciones_pasarela_id_externo UNIQUE (id_externo)
);
GO
-- incidencias_conciliacion: Diferencias entre una transacción y su orden que requieren conciliación (RN-54, RN-56).
CREATE TABLE dbo.incidencias_conciliacion (
    id INT IDENTITY(1,1) NOT NULL,
    transaccion_pasarela_id BIGINT NOT NULL,
    tipo VARCHAR(25) NOT NULL,
    fecha DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_incidencias_conciliacion_fecha DEFAULT (SYSDATETIMEOFFSET()),
    estado VARCHAR(10) NOT NULL CONSTRAINT DF_incidencias_conciliacion_estado DEFAULT ('Pendiente'),
    resolucion NVARCHAR(1000) NULL,
    usuario_id INT NULL,
    CONSTRAINT PK_incidencias_conciliacion PRIMARY KEY (id),
    CONSTRAINT UQ_incidencias_tipo UNIQUE (transaccion_pasarela_id, tipo),
    CONSTRAINT CK_incidencias_conciliacion_tipo CHECK (tipo IN ('ImporteDistinto', 'FueraDePlazo', 'ObligacionesCanceladas', 'EstadoDesconocido')),
    CONSTRAINT CK_incidencias_conciliacion_estado CHECK (estado IN ('Pendiente', 'Resuelta')),
    CONSTRAINT CK_incidencias_resolucion CHECK (estado = 'Pendiente' OR resolucion IS NOT NULL)
);
GO
-- saldos_a_favor: Importes recibidos aún no aplicados; separados de la deuda y de los fondos libres (RN-57).
CREATE TABLE dbo.saldos_a_favor (
    id INT IDENTITY(1,1) NOT NULL,
    cliente_id INT NOT NULL,
    transaccion_origen_id BIGINT NULL,
    fecha DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_saldos_a_favor_fecha DEFAULT (SYSDATETIMEOFFSET()),
    importe_original DECIMAL(19,2) NOT NULL,
    importe_disponible DECIMAL(19,2) NOT NULL,
    importe_reservado DECIMAL(19,2) NOT NULL CONSTRAINT DF_saldos_a_favor_importe_reservado DEFAULT (0),
    version_fila INT NOT NULL CONSTRAINT DF_saldos_a_favor_version_fila DEFAULT (1),
    CONSTRAINT PK_saldos_a_favor PRIMARY KEY (id),
    CONSTRAINT CK_saldos_importes CHECK (importe_original > 0 AND importe_disponible >= 0 AND importe_reservado >= 0 AND importe_disponible + importe_reservado <= importe_original)
);
GO
-- aplicaciones_saldo: Uso de un saldo a favor para cancelar obligaciones del mismo cliente (RN-57, CU-76).
CREATE TABLE dbo.aplicaciones_saldo (
    id INT IDENTITY(1,1) NOT NULL,
    saldo_a_favor_id INT NOT NULL,
    fecha DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_aplicaciones_saldo_fecha DEFAULT (SYSDATETIMEOFFSET()),
    importe DECIMAL(19,2) NOT NULL,
    constancia_conformidad NVARCHAR(300) NOT NULL,
    usuario_id INT NOT NULL,
    CONSTRAINT PK_aplicaciones_saldo PRIMARY KEY (id),
    CONSTRAINT CK_aplicaciones_importe CHECK (importe > 0)
);
GO
-- autorizaciones_devolucion: Autorizaciones gerenciales para devolver un saldo a favor; reservan el importe (RN-73).
CREATE TABLE dbo.autorizaciones_devolucion (
    id INT IDENTITY(1,1) NOT NULL,
    saldo_a_favor_id INT NOT NULL,
    fecha DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_autorizaciones_devolucion_fecha DEFAULT (SYSDATETIMEOFFSET()),
    importe DECIMAL(19,2) NOT NULL,
    motivo NVARCHAR(500) NOT NULL,
    destino VARCHAR(15) NOT NULL,
    cbu_cvu CHAR(22) NULL,
    cuit_titular CHAR(11) NULL,
    vence DATETIMEOFFSET(3) NOT NULL,
    estado VARCHAR(20) NOT NULL CONSTRAINT DF_autorizaciones_devolucion_estado DEFAULT ('PendienteEjecucion'),
    usuario_id INT NOT NULL,
    version_fila INT NOT NULL CONSTRAINT DF_autorizaciones_devolucion_version_fila DEFAULT (1),
    CONSTRAINT PK_autorizaciones_devolucion PRIMARY KEY (id),
    CONSTRAINT CK_autorizaciones_devolucion_destino CHECK (destino IN ('MedioOriginal', 'Transferencia')),
    CONSTRAINT CK_autorizaciones_devolucion_estado CHECK (estado IN ('PendienteEjecucion', 'EnEjecucion', 'Completada', 'EnConciliacion', 'Fallida', 'Vencida', 'Anulada')),
    CONSTRAINT CK_autorizaciones_devolucion_cuenta CHECK (destino = 'MedioOriginal' OR (cbu_cvu IS NOT NULL AND cuit_titular IS NOT NULL)),
    CONSTRAINT CK_autorizaciones_devolucion_importe CHECK (importe > 0)
);
GO
-- ejecuciones_devolucion: Ejecución de una devolución por la pasarela o por transferencia (CU-78).
CREATE TABLE dbo.ejecuciones_devolucion (
    id INT IDENTITY(1,1) NOT NULL,
    autorizacion_devolucion_id INT NOT NULL,
    inicio DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_ejecuciones_devolucion_inicio DEFAULT (SYSDATETIMEOFFSET()),
    identificador_operacion VARCHAR(100) NULL,
    resultado VARCHAR(10) NOT NULL CONSTRAINT DF_ejecuciones_devolucion_resultado DEFAULT ('Pendiente'),
    fecha_confirmacion DATETIMEOFFSET(3) NULL,
    comprobante VARCHAR(100) NULL,
    usuario_id INT NOT NULL,
    CONSTRAINT PK_ejecuciones_devolucion PRIMARY KEY (id),
    CONSTRAINT UQ_ejecuciones_devolucion_autorizacion_devolucion_id UNIQUE (autorizacion_devolucion_id),
    CONSTRAINT CK_ejecuciones_devolucion_resultado CHECK (resultado IN ('Pendiente', 'Confirmada', 'Rechazada', 'Incierta'))
);
GO
-- movimientos_fondos: Ingresos y egresos de fondos no originados en operaciones de crédito (CU-85).
CREATE TABLE dbo.movimientos_fondos (
    id INT IDENTITY(1,1) NOT NULL,
    tipo VARCHAR(15) NOT NULL,
    caracter VARCHAR(10) NOT NULL,
    importe DECIMAL(19,2) NOT NULL,
    fecha DATE NOT NULL,
    concepto NVARCHAR(200) NOT NULL,
    referencia VARCHAR(100) NULL,
    estado VARCHAR(10) NOT NULL CONSTRAINT DF_movimientos_fondos_estado DEFAULT ('Realizado'),
    usuario_id INT NOT NULL,
    CONSTRAINT PK_movimientos_fondos PRIMARY KEY (id),
    CONSTRAINT CK_movimientos_fondos_tipo CHECK (tipo IN ('AporteCapital', 'Financiamiento', 'GastoOperativo', 'Otro')),
    CONSTRAINT CK_movimientos_fondos_caracter CHECK (caracter IN ('Ingreso', 'Egreso')),
    CONSTRAINT CK_movimientos_fondos_estado CHECK (estado IN ('Programado', 'Realizado', 'Anulado')),
    CONSTRAINT CK_movimientos_fondos_importe CHECK (importe > 0)
);
GO
-- afectaciones_fondos: Compromisos temporales y reservas de fondos. El compromiso se convierte en reserva en la misma fila, sin un segundo descuento (RN-41).
CREATE TABLE dbo.afectaciones_fondos (
    id INT IDENTITY(1,1) NOT NULL,
    tipo VARCHAR(10) NOT NULL,
    importe DECIMAL(19,2) NOT NULL,
    fecha DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_afectaciones_fondos_fecha DEFAULT (SYSDATETIMEOFFSET()),
    estado VARCHAR(10) NOT NULL CONSTRAINT DF_afectaciones_fondos_estado DEFAULT ('Vigente'),
    referencia_tipo VARCHAR(20) NOT NULL,
    referencia_id INT NOT NULL,
    version_fila INT NOT NULL CONSTRAINT DF_afectaciones_fondos_version_fila DEFAULT (1),
    CONSTRAINT PK_afectaciones_fondos PRIMARY KEY (id),
    CONSTRAINT CK_afectaciones_fondos_tipo CHECK (tipo IN ('Compromiso', 'Reserva')),
    CONSTRAINT CK_afectaciones_fondos_estado CHECK (estado IN ('Vigente', 'Consumida', 'Liberada')),
    CONSTRAINT CK_afectaciones_referencia CHECK (referencia_tipo IN ('Solicitud', 'Oferta')),
    CONSTRAINT CK_afectaciones_importe CHECK (importe > 0)
);
GO
-- proyecciones_fondos: Estimaciones de disponibilidad para un período futuro; conservan fecha y supuestos (RN-39, CU-92).
CREATE TABLE dbo.proyecciones_fondos (
    id INT IDENTITY(1,1) NOT NULL,
    fecha DATETIMEOFFSET(3) NOT NULL CONSTRAINT DF_proyecciones_fondos_fecha DEFAULT (SYSDATETIMEOFFSET()),
    periodo_dias SMALLINT NOT NULL,
    disponible_actual DECIMAL(19,2) NOT NULL,
    ingresos_esperados DECIMAL(19,2) NOT NULL,
    egresos_comprometidos DECIMAL(19,2) NOT NULL,
    reserva_minima DECIMAL(19,2) NOT NULL,
    disponible_proyectado DECIMAL(19,2) NOT NULL,
    version_parametros_id INT NOT NULL,
    CONSTRAINT PK_proyecciones_fondos PRIMARY KEY (id)
);
GO
ALTER TABLE dbo.asignaciones_caja ADD CONSTRAINT FK_asignaciones_caja_caja_id FOREIGN KEY (caja_id) REFERENCES dbo.cajas (id);
ALTER TABLE dbo.asignaciones_caja ADD CONSTRAINT FK_asignaciones_caja_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.jornadas_caja ADD CONSTRAINT FK_jornadas_caja_asignacion FOREIGN KEY (asignacion_caja_id, caja_id, usuario_id) REFERENCES dbo.asignaciones_caja (id, caja_id, usuario_id);
ALTER TABLE dbo.cobros ADD CONSTRAINT FK_cobros_cliente_id FOREIGN KEY (cliente_id) REFERENCES dbo.clientes (id);
ALTER TABLE dbo.cobros ADD CONSTRAINT FK_cobros_jornada_caja_id FOREIGN KEY (jornada_caja_id) REFERENCES dbo.jornadas_caja (id);
ALTER TABLE dbo.cobros ADD CONSTRAINT FK_cobros_transaccion_pasarela_id FOREIGN KEY (transaccion_pasarela_id) REFERENCES dbo.transacciones_pasarela (id);
ALTER TABLE dbo.cobros ADD CONSTRAINT FK_cobros_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.movimientos_caja ADD CONSTRAINT FK_movimientos_caja_jornada_caja_id FOREIGN KEY (jornada_caja_id) REFERENCES dbo.jornadas_caja (id);
ALTER TABLE dbo.movimientos_caja ADD CONSTRAINT FK_movimientos_caja_cobro_id FOREIGN KEY (cobro_id) REFERENCES dbo.cobros (id);
ALTER TABLE dbo.imputaciones ADD CONSTRAINT FK_imputaciones_cobro_id FOREIGN KEY (cobro_id) REFERENCES dbo.cobros (id);
ALTER TABLE dbo.imputaciones ADD CONSTRAINT FK_imputaciones_aplicacion_saldo_id FOREIGN KEY (aplicacion_saldo_id) REFERENCES dbo.aplicaciones_saldo (id);
ALTER TABLE dbo.imputaciones ADD CONSTRAINT FK_imputaciones_cuota_id FOREIGN KEY (cuota_id) REFERENCES dbo.cuotas (id);
ALTER TABLE dbo.comprobantes_pago ADD CONSTRAINT FK_comprobantes_pago_cobro_id FOREIGN KEY (cobro_id) REFERENCES dbo.cobros (id);
ALTER TABLE dbo.anulaciones_cobro ADD CONSTRAINT FK_anulaciones_cobro_cobro_id FOREIGN KEY (cobro_id) REFERENCES dbo.cobros (id);
ALTER TABLE dbo.anulaciones_cobro ADD CONSTRAINT FK_anulaciones_cobro_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.anulaciones_cobro ADD CONSTRAINT FK_anulaciones_cobro_saldo_a_favor_id FOREIGN KEY (saldo_a_favor_id) REFERENCES dbo.saldos_a_favor (id);
ALTER TABLE dbo.ordenes_pago_electronico ADD CONSTRAINT FK_ordenes_pago_electronico_cliente_id FOREIGN KEY (cliente_id) REFERENCES dbo.clientes (id);
ALTER TABLE dbo.ordenes_pago_cuotas ADD CONSTRAINT FK_ordenes_pago_cuotas_orden_pago_id FOREIGN KEY (orden_pago_id) REFERENCES dbo.ordenes_pago_electronico (id);
ALTER TABLE dbo.ordenes_pago_cuotas ADD CONSTRAINT FK_ordenes_pago_cuotas_cuota_id FOREIGN KEY (cuota_id) REFERENCES dbo.cuotas (id);
ALTER TABLE dbo.transacciones_pasarela ADD CONSTRAINT FK_transacciones_pasarela_orden_pago_id FOREIGN KEY (orden_pago_id) REFERENCES dbo.ordenes_pago_electronico (id);
ALTER TABLE dbo.incidencias_conciliacion ADD CONSTRAINT FK_incidencias_conciliacion_transaccion_pasarela_id FOREIGN KEY (transaccion_pasarela_id) REFERENCES dbo.transacciones_pasarela (id);
ALTER TABLE dbo.incidencias_conciliacion ADD CONSTRAINT FK_incidencias_conciliacion_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.saldos_a_favor ADD CONSTRAINT FK_saldos_a_favor_cliente_id FOREIGN KEY (cliente_id) REFERENCES dbo.clientes (id);
ALTER TABLE dbo.saldos_a_favor ADD CONSTRAINT FK_saldos_a_favor_transaccion_origen_id FOREIGN KEY (transaccion_origen_id) REFERENCES dbo.transacciones_pasarela (id);
ALTER TABLE dbo.aplicaciones_saldo ADD CONSTRAINT FK_aplicaciones_saldo_saldo_a_favor_id FOREIGN KEY (saldo_a_favor_id) REFERENCES dbo.saldos_a_favor (id);
ALTER TABLE dbo.aplicaciones_saldo ADD CONSTRAINT FK_aplicaciones_saldo_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.autorizaciones_devolucion ADD CONSTRAINT FK_autorizaciones_devolucion_saldo_a_favor_id FOREIGN KEY (saldo_a_favor_id) REFERENCES dbo.saldos_a_favor (id);
ALTER TABLE dbo.autorizaciones_devolucion ADD CONSTRAINT FK_autorizaciones_devolucion_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.ejecuciones_devolucion ADD CONSTRAINT FK_ejecuciones_devolucion_autorizacion_devolucion_id FOREIGN KEY (autorizacion_devolucion_id) REFERENCES dbo.autorizaciones_devolucion (id);
ALTER TABLE dbo.ejecuciones_devolucion ADD CONSTRAINT FK_ejecuciones_devolucion_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.movimientos_fondos ADD CONSTRAINT FK_movimientos_fondos_usuario_id FOREIGN KEY (usuario_id) REFERENCES dbo.usuarios_internos (id);
ALTER TABLE dbo.proyecciones_fondos ADD CONSTRAINT FK_proyecciones_fondos_version_parametros_id FOREIGN KEY (version_parametros_id) REFERENCES dbo.versiones_parametros (id);
CREATE UNIQUE INDEX UQ_asignaciones_caja_vigente ON dbo.asignaciones_caja (caja_id) WHERE hasta IS NULL;
CREATE INDEX IX_asignaciones_caja_usuario_id ON dbo.asignaciones_caja (usuario_id);
CREATE UNIQUE INDEX UQ_jornadas_caja_abierta ON dbo.jornadas_caja (caja_id) WHERE estado = 'Abierta';
CREATE UNIQUE INDEX UQ_jornadas_cajero_abierta ON dbo.jornadas_caja (usuario_id) WHERE estado = 'Abierta';
CREATE UNIQUE INDEX UQ_cobros_transaccion ON dbo.cobros (transaccion_pasarela_id) WHERE transaccion_pasarela_id IS NOT NULL;
CREATE INDEX IX_cobros_cliente ON dbo.cobros (cliente_id, fecha_hora);
CREATE INDEX IX_cobros_jornada ON dbo.cobros (jornada_caja_id) INCLUDE (medio, importe, estado);
CREATE INDEX IX_cobros_usuario_id ON dbo.cobros (usuario_id);
CREATE UNIQUE INDEX UQ_movimientos_caja_cobro_id ON dbo.movimientos_caja (cobro_id) WHERE cobro_id IS NOT NULL;
CREATE INDEX IX_movimientos_caja_jornada ON dbo.movimientos_caja (jornada_caja_id, tipo) INCLUDE (medio, importe);
CREATE INDEX IX_imputaciones_cuota ON dbo.imputaciones (cuota_id) INCLUDE (concepto, importe);
CREATE INDEX IX_imputaciones_cobro ON dbo.imputaciones (cobro_id) WHERE cobro_id IS NOT NULL;
CREATE INDEX IX_imputaciones_cobro_id ON dbo.imputaciones (cobro_id);
CREATE INDEX IX_imputaciones_aplicacion_saldo_id ON dbo.imputaciones (aplicacion_saldo_id);
CREATE UNIQUE INDEX UQ_anulaciones_cobro_saldo_a_favor_id ON dbo.anulaciones_cobro (saldo_a_favor_id) WHERE saldo_a_favor_id IS NOT NULL;
CREATE INDEX IX_anulaciones_cobro_usuario_id ON dbo.anulaciones_cobro (usuario_id);
CREATE UNIQUE INDEX UQ_ordenes_referencia ON dbo.ordenes_pago_electronico (referencia_externa) WHERE referencia_externa IS NOT NULL;
CREATE INDEX IX_ordenes_cliente ON dbo.ordenes_pago_electronico (cliente_id, estado);
CREATE INDEX IX_ordenes_pendientes ON dbo.ordenes_pago_electronico (estado, vence) WHERE estado IN ('PendienteConfirmacion', 'EnConciliacion');
CREATE INDEX IX_ordenes_pago_cuotas_cuota_id ON dbo.ordenes_pago_cuotas (cuota_id);
CREATE UNIQUE INDEX UQ_notificaciones_evento ON dbo.notificaciones_pasarela (id_evento) WHERE id_evento IS NOT NULL;
CREATE INDEX IX_notificaciones_pendientes ON dbo.notificaciones_pasarela (estado, recibida_en) WHERE estado = 'Pendiente';
CREATE INDEX IX_transacciones_orden ON dbo.transacciones_pasarela (orden_pago_id);
CREATE INDEX IX_incidencias_conciliacion_usuario_id ON dbo.incidencias_conciliacion (usuario_id);
CREATE UNIQUE INDEX UQ_saldos_a_favor_transaccion_origen_id ON dbo.saldos_a_favor (transaccion_origen_id) WHERE transaccion_origen_id IS NOT NULL;
CREATE INDEX IX_saldos_cliente ON dbo.saldos_a_favor (cliente_id) INCLUDE (importe_disponible);
CREATE INDEX IX_aplicaciones_saldo_saldo_a_favor_id ON dbo.aplicaciones_saldo (saldo_a_favor_id);
CREATE INDEX IX_aplicaciones_saldo_usuario_id ON dbo.aplicaciones_saldo (usuario_id);
CREATE INDEX IX_autorizaciones_devolucion_vence ON dbo.autorizaciones_devolucion (vence) WHERE estado = 'PendienteEjecucion';
CREATE INDEX IX_autorizaciones_devolucion_saldo_a_favor_id ON dbo.autorizaciones_devolucion (saldo_a_favor_id);
CREATE INDEX IX_autorizaciones_devolucion_usuario_id ON dbo.autorizaciones_devolucion (usuario_id);
CREATE UNIQUE INDEX UQ_ejecuciones_devolucion_operacion ON dbo.ejecuciones_devolucion (identificador_operacion) WHERE identificador_operacion IS NOT NULL;
CREATE INDEX IX_ejecuciones_devolucion_usuario_id ON dbo.ejecuciones_devolucion (usuario_id);
CREATE INDEX IX_movimientos_fondos_fecha ON dbo.movimientos_fondos (fecha, estado);
CREATE INDEX IX_movimientos_fondos_usuario_id ON dbo.movimientos_fondos (usuario_id);
CREATE UNIQUE INDEX UQ_afectaciones_vigente ON dbo.afectaciones_fondos (referencia_tipo, referencia_id) WHERE estado = 'Vigente';
CREATE INDEX IX_afectaciones_vigentes ON dbo.afectaciones_fondos (estado) INCLUDE (tipo, importe) WHERE estado = 'Vigente';
CREATE INDEX IX_proyecciones_fondos_version_parametros_id ON dbo.proyecciones_fondos (version_parametros_id);
