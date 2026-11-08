# -*- coding: utf-8 -*-
"""Especificación única del modelo de datos del SIG-CCR.
De aquí se generan: el script DDL, el diccionario de datos y los DER."""

M2 = 'DECIMAL(19,2)'   # importes exigibles y movimientos (RNF-06)
M4 = 'DECIMAL(19,4)'   # resultados intermedios (RN-50)
TASA = 'DECIMAL(18,12)'  # tasas, al menos 12 decimales (RNF-06)
TS = 'DATETIMEOFFSET(3)'  # instantes (UTC con desplazamiento)
D = 'DATE'            # fechas de negocio locales
B = 'BIT'
JSON = 'NVARCHAR(MAX)'

# ---------------------------------------------------------------- enumeraciones
ENUMS = {
    # 4.1.1 (existentes)
    'EstadoCuenta': ['PendienteActivacion', 'Habilitada', 'BloqueadaTemporalmente', 'Desactivada'],
    'EstadoCliente': ['Activo', 'Desactivado'],
    'EstadoLinea': ['Activa', 'Suspendida', 'Vencida'],
    'EstadoSolicitud': ['PendienteVerificacion', 'PendienteDocumentacion', 'PendienteEvaluacion', 'EnEvaluacion',
                        'PendienteContingencia', 'PendienteSeleccionPlan', 'PendienteResolucion', 'DevueltaRevision',
                        'AprobadaPendienteLiquidacion', 'Liquidada', 'Rechazada', 'Cancelada', 'Vencida'],
    'EstadoRevision': ['PendienteRevision', 'Observada', 'Verificada', 'Rechazada'],
    'EstadoCuota': ['Pendiente', 'Vencida', 'Pagada', 'Sustituida', 'SinEfecto'],
    'EstadoCredito': ['Activo', 'EnMora', 'Cancelado', 'Refinanciado', 'Revocado'],
    'EstadoPropuesta': ['PendienteResolucion', 'AprobadaPendienteAceptacion', 'Observada', 'Rechazada', 'Aplicada',
                        'Vencida', 'Cancelada'],
    'EstadoOrden': ['PendienteConfirmacion', 'Confirmada', 'Rechazada', 'EnConciliacion', 'Vencida'],
    'EstadoJornada': ['Abierta', 'Cerrada'],
    'EstadoDevolucion': ['PendienteEjecucion', 'EnEjecucion', 'Completada', 'EnConciliacion', 'Fallida', 'Vencida',
                         'Anulada'],
    'EstadoCampana': ['Activa', 'Finalizada'],
    'EstadoOferta': ['Vigente', 'Aceptada', 'Rechazada', 'Vencida'],
    'ConceptoDeuda': ['Punitorio', 'InteresOrdinario', 'GastosCargos', 'IVA', 'NoCapitalizable', 'Capital'],
    'EstadoAutorizacion': ['Vigente', 'Utilizada', 'Vencida', 'Sustituida'],
    'EstadoVigencia': ['Vigente', 'Vencida'],
    'EstadoAlternativa': ['Vigente', 'Confirmada', 'Descartada', 'Vencida'],
    'EstadoCaja': ['Habilitada', 'Inhabilitada'],
    'EstadoAlerta': ['Pendiente', 'Resuelta'],
    'EstadoComunicacion': ['Pendiente', 'AceptadaProveedor', 'Entregada', 'ReintentoProgramado', 'Fallida', 'Cancelada',
                           'EnRevision'],
    'EstadoMovimientoFondos': ['Programado', 'Realizado', 'Anulado'],
    'EstadoCopia': ['EnCurso', 'Verificada', 'Fallida', 'Corrupta'],
    'TipoCliente': ['PersonaHumana', 'PersonaJuridica'],
    'CategoriaIngresos': ['RelacionDependencia', 'JubiladoPensionado', 'Monotributista', 'ResponsableInscripto', 'Empresa'],
    'MedioPago': ['Efectivo', 'TarjetaDebito', 'TarjetaCredito', 'Transferencia', 'PagoElectronico'],
    'MedioDesembolso': ['Efectivo', 'Transferencia'],
    'TipoMovimientoCaja': ['FondoInicial', 'Cobro', 'Desembolso'],
    'TipoCobro': ['Ordinario', 'CancelacionAnticipada', 'Revocacion'],
    'EstadoRevocacion': ['PendienteRestitucion', 'Confirmada'],
    'TipoRechazo': ['Documental', 'Crediticio'],
    'ModalidadEvaluacion': ['Automatica', 'Contingencia'],
    'EstadoProcesamiento': ['Pendiente', 'EnProceso', 'Finalizada', 'NoCompletada'],
    'ResultadoEvaluacion': ['Favorable', 'Desfavorable', 'RequiereAnalisisGerencial'],
    'CategoriaRiesgo': ['A', 'B', 'C', 'D', 'E'],
    'EstadoVigenciaEvaluacion': ['Vigente', 'Vencida', 'Invalidada', 'Sustituida'],
    # complementarias (usadas en el modelo de dominio y no detalladas en 4.1.1)
    'TipoCuenta': ['UsuarioInterno', 'Cliente'],
    'TipoDocumento': ['DNI', 'LC', 'LE', 'Pasaporte'],
    'PropositoAutorizacion': ['Activacion', 'Rehabilitacion', 'DefinicionInicial', 'Recuperacion', 'Restablecimiento',
                              'CambioCorreo', 'VerificacionCorreo'],
    'TipoConsentimiento': ['PoliticaDatos', 'ComunicacionesComerciales'],
    'MedioConsentimiento': ['Portal', 'Sucursal'],
    'OrigenSolicitud': ['Independiente', 'Oferta'],
    'ResultadoVerificacion': ['Conforme', 'Observado', 'Rechazado'],
    'MotivoObservacion': ['Faltante', 'Ilegible', 'Vencido', 'Inconsistente', 'NoCorresponde'],
    'DecisionResolucion': ['Aprobada', 'Rechazada', 'DevueltaRevision'],
    'EtapaRetorno': ['Verificacion', 'Evaluacion', 'SeleccionPlan', 'Resolucion'],
    'TipoDiaNoHabil': ['Feriado', 'NoLaborable', 'CierreEntidad'],
    'EstadoCobro': ['Confirmado', 'Anulado'],
    'CanalCobro': ['Ventanilla', 'Electronico'],
    'TipoIncidencia': ['ImporteDistinto', 'FueraDePlazo', 'ObligacionesCanceladas', 'EstadoDesconocido'],
    'EstadoIncidencia': ['Pendiente', 'Resuelta'],
    'DestinoDevolucion': ['MedioOriginal', 'Transferencia'],
    'ResultadoEjecucion': ['Pendiente', 'Confirmada', 'Rechazada', 'Incierta'],
    'TipoAfectacion': ['Compromiso', 'Reserva'],
    'EstadoAfectacion': ['Vigente', 'Consumida', 'Liberada'],
    'TipoMovimientoFondos': ['AporteCapital', 'Financiamiento', 'GastoOperativo', 'Otro'],
    'CaracterMovimiento': ['Ingreso', 'Egreso'],
    'OrigenCredito': ['Liquidacion', 'Refinanciacion'],
    'DecisionPropuesta': ['Aprobada', 'Rechazada', 'Observada'],
    'TipoCancelacionPropuesta': ['Desistimiento', 'Administrativa'],
    'CausaCierre': ['Vigencia', 'Cupo', 'Disponibilidad'],
    'CriterioComparacion': ['Monto', 'Costo'],
    'TipoAlerta': ['Operativa', 'Vencimiento', 'Integracion', 'Proceso', 'Seguridad'],
    'TipoComunicacion': ['Autorizacion', 'VerificacionCorreo', 'Oferta', 'AvisoObligacion', 'Notificacion'],
    'TipoParametros': ['Evaluacion', 'Estructuracion', 'Morosidad', 'Campanas'],
    'OrigenCopia': ['Manual', 'Automatica'],
    # técnicas (sin clase de dominio)
    'EstadoNotificacion': ['Pendiente', 'Procesada', 'Descartada', 'Error'],
    'ServicioExterno': ['InformacionCrediticia', 'Pasarela', 'Comunicaciones'],
    'EstadoEjecucion': ['EnCurso', 'Finalizada', 'ConErrores', 'Fallida'],
    'AplicaDocumentacion': ['PersonaHumana', 'PersonaJuridica', 'Ambos'],
    'TipoGastoAdministrativo': ['Fijo', 'Porcentual'],
    'CategoriaMonotributo': ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'],
}


class C:
    def __init__(self, name, typ, null=False, d='', pk=False, fk=None, default=None, enum=None, uq=False, ident=False):
        self.name, self.null, self.d, self.pk, self.fk, self.default, self.enum, self.uq = name, null, d, pk, fk, default, enum, uq
        if enum:
            n = max(len(v) for v in ENUMS[enum])
            typ = 'CHAR(1)' if n == 1 else f'VARCHAR({max(10, ((n + 4) // 5) * 5)})'
        self.typ = typ
        self.ident = ident


def ID(big=False, d='Identificador interno.'):
    return C('id', 'BIGINT' if big else 'INT', pk=True, ident=True, d=d)


def FK(name, ref, null=False, d='', big=False, uq=False):
    return C(name, 'BIGINT' if big else 'INT', null=null, fk=ref, d=d, uq=uq)


def EN(name, enum, null=False, d='', default=None):
    return C(name, None, null=null, enum=enum, d=d or f'Valor de {enum} (4.1.1).', default=default)


def VF():
    return C('version_fila', 'INT', default='1', d='Versión de la fila para control de concurrencia optimista (RNF-06, RN-24).')


def CREADO():
    return C('creado_en', TS, default='SYSDATETIMEOFFSET()', d='Instante de registro.')


def ACT():
    return C('actualizado_en', TS, null=True, d='Instante de la última modificación.')


class T:
    def __init__(self, name, ss, clase, desc, cols, uq=(), ck=(), idx=(), fkx=(), pk=None):
        self.name, self.ss, self.clase, self.desc, self.cols = name, ss, clase, desc, cols
        self.uq, self.ck, self.idx, self.fkx, self.pkcols = list(uq), list(ck), list(idx), list(fkx), pk
        # uq: (nombre, [cols], where|None)   ck: (nombre, expr)   idx: (nombre, [cols], include|None, where|None)
        # fkx: (nombre, [cols], tabla_ref, [cols_ref])   pk: lista de columnas para PK compuesta


TABLES = []


def add(*a, **k):
    t = T(*a, **k)
    TABLES.append(t)
    return t


SS = {
    'SS-01': 'Acceso y administración de usuarios',
    'SS-02': 'Gestión de clientes',
    'SS-03': 'Gestión de líneas de crédito',
    'SS-04': 'Solicitudes, evaluación y otorgamiento de créditos',
    'SS-05': 'Caja, cobranzas, pagos y fondos',
    'SS-06': 'Cartera, morosidad y refinanciaciones',
    'SS-08': 'Campañas crediticias',
    'SS-10': 'Servicios comunes',
    'SS-11': 'Mantenimiento y procesos programados',
}

# ============================================================== SS-10 (base)
add('tipos_documentacion', 'SS-10', '(catálogo)', 'Catálogo de tipos de documentación respaldatoria exigibles en el legajo (RN-11). Se ubica en servicios comunes porque lo utilizan SS-02 y SS-03, que no dependen entre sí.', [
    ID(),
    C('codigo', 'VARCHAR(30)', uq=True, d='Código único del tipo de documento.'),
    C('nombre', 'NVARCHAR(120)', d='Denominación visible.'),
    EN('aplica_a', 'AplicaDocumentacion', d='Tipo de cliente al que aplica.'),
    C('es_clave', B, default='0', d='Indica si su cambio invalida la evaluación (documento clave, RN-61).'),
    C('activo', B, default='1', d='Disponible para nuevas exigencias.'),
])

add('documentacion_sugerida', 'SS-10', '(catálogo)', 'Documentación base que se propone para cada categoría de ingresos al registrar o modificar una línea (RN-11, D-25). La exigencia efectiva la define cada versión de condiciones.', [
    EN('categoria_ingresos', 'CategoriaIngresos'),
    FK('tipo_documentacion_id', 'tipos_documentacion'),
], pk=['categoria_ingresos', 'tipo_documentacion_id'])

add('categorias_monotributo', 'SS-10', '(catálogo)', 'Categorías del Régimen Simplificado (monotributo) con su tope de ingresos brutos anuales, versionadas por fecha de vigencia porque ARCA las actualiza cada semestre (D-26).', [
    ID(),
    EN('categoria', 'CategoriaMonotributo'),
    C('ingresos_brutos_anuales', M2, d='Tope de ingresos brutos de los últimos doce meses para la categoría.'),
    C('vigente_desde', D, d='Fecha desde la que rige el tope.'),
    CREADO(),
], uq=[('UQ_categorias_monotributo_vigencia', ['categoria', 'vigente_desde'], None)],
   ck=[('CK_categorias_monotributo_tope', 'ingresos_brutos_anuales > 0')])

add('provincias', 'SS-10', '(catálogo)', 'Provincias de la República Argentina y Ciudad Autónoma de Buenos Aires, identificadas por su código INDEC (Servicio de Normalización de Datos Geográficos, georef). Se ubica en servicios comunes como catálogo de referencia geográfica.', [
    C('id', 'INT', pk=True, d='Código INDEC de la provincia (por ejemplo, 54 Misiones, 18 Corrientes).'),
    C('nombre', 'NVARCHAR(80)', uq=True, d='Nombre oficial abreviado.'),
])

add('localidades', 'SS-10', '(catálogo)', 'Localidades de cada provincia para el domicilio de los clientes. Se cargan del catálogo oficial de localidades censales (georef) con el comando geografia:importar; las localidades base se cargan con la instalación.', [
    ID(),
    FK('provincia_id', 'provincias'),
    C('nombre', 'NVARCHAR(150)', d='Nombre de la localidad.'),
    C('departamento', 'NVARCHAR(100)', null=True, d='Departamento o partido; distingue localidades homónimas de una provincia.'),
    C('codigo', 'VARCHAR(12)', null=True, d='Identificador de la localidad censal en georef; nulo en las localidades cargadas manualmente.'),
    C('activa', B, default='1', d='Disponible para nuevos domicilios.'),
], uq=[('UQ_localidades_codigo', ['codigo'], 'codigo IS NOT NULL'),
       ('UQ_localidades_nombre', ['provincia_id', 'nombre', 'departamento'], None)])

add('versiones_parametros', 'SS-10', 'VersionParametros', 'Versiones de cada grupo de parámetros de negocio; las operaciones referencian la versión aplicada (RN-15, RN-45).', [
    ID(),
    EN('tipo', 'TipoParametros', d='Grupo de parámetros (CU-51, CU-58 a CU-60).'),
    C('numero', 'INT', d='Número correlativo dentro del grupo.'),
    C('vigente_desde', TS, d='Instante desde el que rige la versión.'),
    C('valores', JSON, d='Valores de los parámetros en formato JSON (validado con ISJSON).'),
    C('motivo', 'NVARCHAR(500)', null=True, d='Motivo de la modificación.'),
    C('usuario_id', 'INT', null=True, d='Gerente que registró la versión (referencia validada por el servicio); nulo en la carga inicial.'),
    CREADO(),
], uq=[('UQ_versiones_parametros_tipo_numero', ['tipo', 'numero'], None)],
   ck=[('CK_versiones_parametros_valores', 'ISJSON(valores) = 1')],
   idx=[('IX_versiones_parametros_vigencia', ['tipo', 'vigente_desde'], None, None)])

add('registros_auditoria', 'SS-10', 'RegistroAuditoria', 'Evidencia inalterable de operaciones y decisiones relevantes (RN-45). Solo admite inserciones: un disparador rechaza modificaciones y eliminaciones, y el usuario de la aplicación no tiene permiso UPDATE ni DELETE sobre ella.', [
    ID(True),
    C('fecha_hora', TS, default='SYSDATETIMEOFFSET()', d='Instante de la operación.'),
    C('cuenta_acceso_id', 'INT', null=True, d='Cuenta que ejecutó la operación, sin clave foránea para que la evidencia no dependa de otras tablas; nulo si fue un proceso.'),
    C('responsable', 'NVARCHAR(150)', d='Nombre del responsable o del proceso, conservado como texto.'),
    C('proceso', 'VARCHAR(60)', null=True, d='Comando programado que tomó la decisión automática.'),
    C('accion', 'VARCHAR(60)', d='Operación realizada (por ejemplo, solicitud.resolver).'),
    C('objeto_tipo', 'VARCHAR(60)', d='Tabla o clase del objeto afectado.'),
    C('objeto_id', 'VARCHAR(40)', null=True, d='Identificador del objeto afectado.'),
    C('resultado', 'VARCHAR(20)', d='Exitoso, Rechazado o Error.'),
    C('motivo', 'NVARCHAR(1000)', null=True, d='Motivo o fundamento, cuando es exigible.'),
    C('detalle', JSON, null=True, d='Datos adicionales en JSON: estado anterior y nuevo, parámetros aplicados. Sin contraseñas ni autorizaciones (RNF-14).'),
    C('ip_origen', 'VARCHAR(45)', null=True, d='Dirección IP de origen (IPv4 o IPv6).'),
], ck=[('CK_registros_auditoria_detalle', 'detalle IS NULL OR ISJSON(detalle) = 1')],
   idx=[('IX_registros_auditoria_fecha', ['fecha_hora'], None, None),
        ('IX_registros_auditoria_objeto', ['objeto_tipo', 'objeto_id'], None, None),
        ('IX_registros_auditoria_cuenta', ['cuenta_acceso_id', 'fecha_hora'], None, None)])

add('alertas', 'SS-10', 'Alerta', 'Avisos que requieren la intervención de un rol o de un usuario (RF-11, CU-83).', [
    ID(True),
    EN('tipo', 'TipoAlerta'),
    C('causa', 'NVARCHAR(300)', d='Descripción de la situación detectada.'),
    C('accion_pendiente', 'NVARCHAR(300)', null=True, d='Acción esperada del destinatario.'),
    C('rol_destino', 'VARCHAR(30)', null=True, d='Código del rol destinatario (referencia validada por el servicio).'),
    C('usuario_destino_id', 'INT', null=True, d='Usuario destinatario, cuando es individual (referencia validada por el servicio).'),
    C('objeto_tipo', 'VARCHAR(60)', null=True, d='Tipo del objeto que originó la alerta.'),
    C('objeto_id', 'VARCHAR(40)', null=True, d='Identificador del objeto que originó la alerta.'),
    C('fecha_generacion', TS, default='SYSDATETIMEOFFSET()'),
    C('fecha_resolucion', TS, null=True),
    EN('estado', 'EstadoAlerta', default="'Pendiente'"),
    C('resuelta_por', 'NVARCHAR(150)', null=True, d='Usuario o proceso automático que resolvió la situación (CU-83 A2).'),
], ck=[('CK_alertas_destino', 'rol_destino IS NOT NULL OR usuario_destino_id IS NOT NULL'),
       ('CK_alertas_resolucion', "(estado = 'Pendiente' AND fecha_resolucion IS NULL) OR (estado = 'Resuelta' AND fecha_resolucion IS NOT NULL)")],
   idx=[('IX_alertas_pendientes_rol', ['rol_destino', 'estado'], None, "estado = 'Pendiente'"),
        ('IX_alertas_pendientes_usuario', ['usuario_destino_id', 'estado'], None, "estado = 'Pendiente'"),
        ('IX_alertas_objeto', ['objeto_tipo', 'objeto_id'], None, "estado = 'Pendiente'")])

add('alertas_vistas', 'SS-10', 'Alerta', 'Primera vez que cada usuario interno vio el detalle de una alerta (postcondición de CU-83).', [
    FK('alerta_id', 'alertas', big=True),
    C('usuario_id', 'INT', d='Usuario que vio la alerta (referencia validada por el servicio, como usuario_destino_id).'),
    C('fecha_hora', TS, default='SYSDATETIMEOFFSET()'),
], pk=['alerta_id', 'usuario_id'],
   idx=[('IX_alertas_vistas_usuario_id', ['usuario_id'], None, None)])

add('comunicaciones', 'SS-10', 'Comunicacion', 'Cola de comunicaciones salientes con su política de reintentos (RNF-09, CU-95).', [
    ID(True),
    EN('tipo', 'TipoComunicacion'),
    C('canal', 'VARCHAR(10)', default="'Correo'", d='Canal de envío; en esta versión solo correo electrónico.'),
    C('destino', 'NVARCHAR(254)', d='Dirección de destino.'),
    C('plantilla', 'VARCHAR(60)', d='Plantilla de mensaje.'),
    C('datos_cifrados', 'VARBINARY(MAX)', null=True, d='Datos variables del mensaje cifrados con la clave de la aplicación; incluye el enlace de una autorización, que nunca se guarda en claro. Se eliminan al finalizar la comunicación.'),
    C('identificador_operacion', 'UNIQUEIDENTIFIER', default='NEWID()', uq=True, d='Identificador enviado al proveedor para evitar duplicados.'),
    C('origen_tipo', 'VARCHAR(40)', d='Tipo de la operación de origen (AutorizacionTemporal, Oferta, Cuota…), que reemplaza las asociaciones del dominio para no depender de subsistemas superiores (RNF-09).'),
    C('origen_id', 'BIGINT', d='Identificador de la operación de origen.'),
    C('programada_para', TS, d='Instante a partir del cual puede enviarse (reintentos a 1, 3 y 5 minutos).'),
    C('intentos', 'TINYINT', default='0', d='Cantidad de intentos realizados.'),
    EN('estado', 'EstadoComunicacion', default="'Pendiente'"),
    VF(),
    CREADO(),
], ck=[('CK_comunicaciones_intentos', 'intentos BETWEEN 0 AND 4')],
   idx=[('IX_comunicaciones_origen', ['origen_tipo', 'origen_id'], None, None),
        ('IX_comunicaciones_cola', ['estado', 'programada_para'], None, "estado IN ('Pendiente', 'ReintentoProgramado')")])

add('intentos_envio', 'SS-10', 'IntentoEnvio', 'Cada intento de envío de una comunicación y su resultado (RNF-09).', [
    ID(True),
    FK('comunicacion_id', 'comunicaciones', big=True),
    C('fecha_hora', TS, default='SYSDATETIMEOFFSET()'),
    C('resultado', 'VARCHAR(20)', d='Aceptado, FalloTransitorio, FalloDefinitivo o Incierto.'),
    C('id_proveedor', 'VARCHAR(100)', null=True, d='Identificador asignado por el proveedor.'),
    C('detalle', 'NVARCHAR(500)', null=True, d='Código o mensaje del proveedor, sin datos sensibles.'),
], idx=[('IX_intentos_envio_comunicacion', ['comunicacion_id'], None, None)])

add('ejecuciones_proceso', 'SS-10', 'EjecucionProceso', 'Registro de cada ejecución de un proceso automático: inicio, fin, elementos procesados y errores (RNF-10).', [
    ID(True),
    C('proceso', 'VARCHAR(60)', d='Nombre del comando spark (por ejemplo, cartera:punitorios).'),
    C('fecha_referencia', D, null=True, d='Fecha de negocio procesada; permite detectar y recuperar ejecuciones omitidas.'),
    C('inicio', TS, default='SYSDATETIMEOFFSET()'),
    C('fin', TS, null=True),
    C('elementos_procesados', 'INT', default='0'),
    C('errores', 'INT', default='0'),
    EN('estado', 'EstadoEjecucion', default="'EnCurso'"),
    C('detalle', 'NVARCHAR(2000)', null=True, d='Resumen de errores.'),
], idx=[('IX_ejecuciones_proceso', ['proceso', 'fecha_referencia'], None, None)])

add('intercambios_externos', 'SS-10', '(técnica)', 'Peticiones y respuestas intercambiadas con servicios externos, necesarias para la conciliación y el diagnóstico, sin datos sensibles (RNF-08, RNF-16).', [
    ID(True),
    EN('servicio', 'ServicioExterno'),
    C('operacion', 'VARCHAR(60)', d='Operación invocada.'),
    C('referencia', 'VARCHAR(100)', null=True, d='Referencia de la operación interna (orden, evaluación, comunicación).'),
    C('fecha_hora', TS, default='SYSDATETIMEOFFSET()'),
    C('duracion_ms', 'INT', null=True),
    C('codigo_respuesta', 'VARCHAR(20)', null=True, d='Código HTTP o del proveedor; Timeout si se agotó la espera.'),
    C('resumen', 'NVARCHAR(2000)', null=True, d='Resumen de la petición y la respuesta, sin credenciales ni datos de tarjetas.'),
], idx=[('IX_intercambios_externos_ref', ['servicio', 'referencia'], None, None)])

# ============================================================== SS-01
add('roles', 'SS-01', 'Rol', 'Perfiles de usuario interno (RN-01, RN-06).', [
    ID(),
    C('codigo', 'VARCHAR(30)', uq=True, d='Administrador, Gerente, AnalistaCredito o Cajero.'),
    C('nombre', 'NVARCHAR(60)'),
    C('descripcion', 'NVARCHAR(255)', null=True),
])

add('permisos', 'SS-01', 'Permiso', 'Operaciones del sistema que pueden habilitarse para un rol (RNF-01).', [
    ID(),
    C('codigo', 'VARCHAR(80)', uq=True, d='Identificador de la operación, verificado por el filtro de autorización (por ejemplo, cobros.registrar).'),
    C('descripcion', 'NVARCHAR(255)'),
])

add('roles_permisos', 'SS-01', '(asociación Rol–Permiso)', 'Resuelve la asociación muchos a muchos «habilita» entre roles y permisos.', [
    FK('rol_id', 'roles'),
    FK('permiso_id', 'permisos'),
], pk=['rol_id', 'permiso_id'])

add('cuentas_acceso', 'SS-01', 'CuentaAcceso', 'Superclase de las cuentas de acceso (herencia por tablas de clase). El discriminador tipo_cuenta separa las cuentas del panel y del portal (RN-08).', [
    ID(),
    EN('tipo_cuenta', 'TipoCuenta', d='Discriminador de la generalización.'),
    C('correo', 'NVARCHAR(254)', d='Correo de acceso; único dentro de cada tipo de cuenta (RN-08).'),
    C('hash_contrasena', 'VARCHAR(255)', null=True, d='Hash Argon2id generado con password_hash(); nulo hasta la activación (RN-62).'),
    C('correo_verificado', B, default='0'),
    EN('estado', 'EstadoCuenta', default="'PendienteActivacion'"),
    C('intentos_fallidos', 'TINYINT', default='0', d='Intentos fallidos dentro de la ventana actual (RN-62).'),
    C('inicio_ventana_intentos', TS, null=True, d='Primer intento fallido de la ventana de 15 minutos.'),
    C('bloqueada_hasta', TS, null=True, d='Fin del bloqueo temporal.'),
    C('ultimo_acceso', TS, null=True),
    C('version_seguridad', 'INT', default='1', d='Se incrementa al desactivar, cambiar el rol o restablecer credenciales; las sesiones con un valor anterior se invalidan (RNF-11).'),
    VF(),
    CREADO(), ACT(),
], ck=[('CK_cuentas_acceso_contrasena', "estado = 'PendienteActivacion' OR hash_contrasena IS NOT NULL"),
       ('CK_cuentas_acceso_intentos', 'intentos_fallidos BETWEEN 0 AND 5')],
   uq=[('UQ_cuentas_acceso_correo', ['tipo_cuenta', 'correo'], None),
       ('UQ_cuentas_acceso_id_tipo', ['id', 'tipo_cuenta'], None)])

add('usuarios_internos', 'SS-01', 'UsuarioInterno', 'Subclase de CuentaAcceso para el personal de la entidad. Comparte la clave primaria con cuentas_acceso.', [
    C('id', 'INT', pk=True, d='Clave primaria y foránea a cuentas_acceso.'),
    C('tipo_cuenta', "AS CAST('UsuarioInterno' AS VARCHAR(15)) PERSISTED NOT NULL", d='Columna calculada que fija el discriminador y participa en la clave foránea compuesta.'),
    FK('rol_id', 'roles', d='Rol único del usuario (RN-06).'),
    C('nombre', 'NVARCHAR(80)'),
    C('apellido', 'NVARCHAR(80)'),
    EN('tipo_documento', 'TipoDocumento'),
    C('nro_documento', 'VARCHAR(15)'),
], uq=[('UQ_usuarios_internos_documento', ['tipo_documento', 'nro_documento'], None)],
   fkx=[('FK_usuarios_internos_cuenta', ['id', 'tipo_cuenta'], 'cuentas_acceso', ['id', 'tipo_cuenta'])])

add('cuentas_cliente', 'SS-01', 'CuentaCliente', 'Subclase de CuentaAcceso para el acceso al portal. El vínculo con el cliente se registra en clientes.cuenta_cliente_id; la rehabilitación reutiliza la cuenta existente (RN-08, RN-10).', [
    C('id', 'INT', pk=True, d='Clave primaria y foránea a cuentas_acceso.'),
    C('tipo_cuenta', "AS CAST('Cliente' AS VARCHAR(15)) PERSISTED NOT NULL", d='Discriminador fijo.'),
    C('fecha_activacion', TS, null=True),
], fkx=[('FK_cuentas_cliente_cuenta', ['id', 'tipo_cuenta'], 'cuentas_acceso', ['id', 'tipo_cuenta'])])

add('autorizaciones_temporales', 'SS-01', 'AutorizacionTemporal', 'Códigos o enlaces de un solo uso. Se guarda solo el hash SHA-256 del valor enviado (RN-03, RNF-14).', [
    ID(True),
    FK('cuenta_acceso_id', 'cuentas_acceso'),
    EN('proposito', 'PropositoAutorizacion'),
    C('hash_codigo', 'CHAR(64)', uq=True, d='Hash SHA-256 en hexadecimal del código o enlace.'),
    C('correo_destino', 'NVARCHAR(254)', null=True, d='Nuevo correo a verificar en un cambio de correo (RN-58).'),
    C('emitida', TS, default='SYSDATETIMEOFFSET()'),
    C('vence', TS, d='24 horas, 15 o 30 minutos según el propósito (RN-03).'),
    C('utilizada', TS, null=True),
    EN('estado', 'EstadoAutorizacion', default="'Vigente'"),
], uq=[('UQ_autorizaciones_vigente', ['cuenta_acceso_id', 'proposito'], "estado = 'Vigente'")],
   ck=[('CK_autorizaciones_vence', 'vence > emitida')])

# ============================================================== SS-02
add('clientes', 'SS-02', 'Cliente', 'Superclase del padrón de clientes (herencia por tablas de clase). Conserva los datos comunes a personas humanas y jurídicas.', [
    ID(),
    EN('tipo_cliente', 'TipoCliente', d='Discriminador de la generalización.'),
    C('cuit_cuil', 'CHAR(11)', null=True, d='CUIT o CUIL sin guiones; obligatorio para personas jurídicas (RN-07, RN-75).'),
    C('denominacion', 'NVARCHAR(200)', d='«Apellido, Nombre» o razón social, mantenida por la aplicación para la búsqueda (RNF-03).'),
    C('correo', 'NVARCHAR(254)', null=True, d='Correo de contacto.'),
    C('telefono', 'VARCHAR(30)', null=True, d='Teléfono informativo; no identifica (RN-08).'),
    C('domicilio', 'NVARCHAR(200)', d='Calle, número, piso y departamento del domicilio real o legal.'),
    C('ingresos_declarados', M2, d='Ingresos netos mensuales declarados.'),
    FK('localidad_id', 'localidades', null=True, d='Localidad del domicilio; obligatoria en el alta y en cada modificación (D-18), nula en clientes anteriores.'),
    C('codigo_postal', 'VARCHAR(8)', null=True, d='Código postal: cuatro dígitos o CPA de ocho caracteres (por ejemplo, N3350ABC).'),
    EN('categoria_ingresos', 'CategoriaIngresos'),
    EN('categoria_monotributo', 'CategoriaMonotributo', null=True, d='Categoría del monotributo; obligatoria para los monotributistas desde D-26, nula en las demás categorías.'),
    C('persona_expuesta', B, null=True, d='Declaración jurada de persona expuesta políticamente (RN-80); nula si el cliente registrado antes de D-31 todavía no la declaró.'),
    C('fecha_declaracion_pep', TS, null=True, d='Fecha de la declaración.'),
    EN('estado', 'EstadoCliente', default="'Activo'"),
    FK('cuenta_cliente_id', 'cuentas_cliente', null=True, uq=True, d='Cuenta de acceso al portal; nula hasta que el cliente la solicita.'),
    C('fecha_alta', D, d='Fecha local de alta en el padrón, informada por la aplicación (antigüedad, RN-69).'),
    VF(),
    CREADO(), ACT(),
], uq=[('UQ_clientes_cuit', ['cuit_cuil'], 'cuit_cuil IS NOT NULL'),
       ('UQ_clientes_id_tipo', ['id', 'tipo_cliente'], None)],
   ck=[('CK_clientes_cuit_juridica', "tipo_cliente = 'PersonaHumana' OR cuit_cuil IS NOT NULL"),
       ('CK_clientes_cuit_formato', "cuit_cuil IS NULL OR cuit_cuil NOT LIKE '%[^0-9]%'"),
       ('CK_clientes_categoria', "(tipo_cliente = 'PersonaJuridica' AND categoria_ingresos = 'Empresa') OR (tipo_cliente = 'PersonaHumana' AND categoria_ingresos <> 'Empresa')"),
       ('CK_clientes_ingresos', 'ingresos_declarados >= 0'),
       ('CK_clientes_monotributo', "categoria_monotributo IS NULL OR categoria_ingresos = 'Monotributista'"),
       ('CK_clientes_declaracion_pep', '(persona_expuesta IS NULL AND fecha_declaracion_pep IS NULL) OR (persona_expuesta IS NOT NULL AND fecha_declaracion_pep IS NOT NULL)')],
   idx=[('IX_clientes_denominacion', ['denominacion'], ['estado', 'tipo_cliente'], None)])

add('personas_humanas', 'SS-02', 'PersonaHumana', 'Subclase de Cliente identificada por su documento.', [
    C('cliente_id', 'INT', pk=True, d='Clave primaria y foránea a clientes.'),
    C('tipo_cliente', "AS CAST('PersonaHumana' AS VARCHAR(15)) PERSISTED NOT NULL", d='Discriminador fijo.'),
    EN('tipo_documento', 'TipoDocumento'),
    C('nro_documento', 'VARCHAR(15)'),
    C('nombre', 'NVARCHAR(80)'),
    C('apellido', 'NVARCHAR(80)'),
    C('fecha_nacimiento', D, d='Utilizada para los límites de edad de la línea (RN-14).'),
    C('estado_civil', 'VARCHAR(20)', null=True),
    C('ocupacion', 'NVARCHAR(100)', null=True, d='Actividad laboral o profesional principal; obligatoria desde D-31 (RN-80).'),
    C('nacionalidad', 'NVARCHAR(60)', null=True, d='Obligatoria desde D-31 (RN-80); nula en clientes anteriores hasta su próxima modificación.'),
    C('empleador', 'NVARCHAR(150)', null=True),
], uq=[('UQ_personas_humanas_documento', ['tipo_documento', 'nro_documento'], None)],
   fkx=[('FK_personas_humanas_cliente', ['cliente_id', 'tipo_cliente'], 'clientes', ['id', 'tipo_cliente'])],
   idx=[('IX_personas_humanas_nro', ['nro_documento'], None, None)])

add('personas_juridicas', 'SS-02', 'PersonaJuridica', 'Subclase de Cliente para pequeñas y medianas empresas locales (RN-75).', [
    C('cliente_id', 'INT', pk=True, d='Clave primaria y foránea a clientes.'),
    C('tipo_cliente', "AS CAST('PersonaJuridica' AS VARCHAR(15)) PERSISTED NOT NULL", d='Discriminador fijo.'),
    C('razon_social', 'NVARCHAR(200)'),
    C('fecha_constitucion', D, d='Utilizada para la antigüedad mínima de la línea (RN-14).'),
    C('actividad_principal', 'NVARCHAR(150)'),
    C('representante_nombre', 'NVARCHAR(150)'),
    C('representante_documento', 'VARCHAR(15)'),
    C('representante_cargo', 'NVARCHAR(80)'),
], fkx=[('FK_personas_juridicas_cliente', ['cliente_id', 'tipo_cliente'], 'clientes', ['id', 'tipo_cliente'])])

add('documentos_legajo', 'SS-02', 'DocumentoLegajo', 'Documentos incorporados al legajo. El archivo se guarda fuera del directorio público con nombre aleatorio (RNF-12); un reemplazo crea un registro nuevo que referencia al anterior (RN-44).', [
    ID(),
    FK('cliente_id', 'clientes'),
    FK('tipo_documentacion_id', 'tipos_documentacion'),
    C('nombre_original', 'NVARCHAR(255)', d='Nombre del archivo subido, solo informativo.'),
    C('ruta_archivo', 'VARCHAR(260)', uq=True, d='Ruta relativa en el volumen de adjuntos, con nombre aleatorio.'),
    C('tipo_mime', 'VARCHAR(20)', d='application/pdf, image/jpeg o image/png, validado por su contenido.'),
    C('tamano_bytes', 'INT', d='Hasta 5 MB.'),
    C('hash_sha256', 'CHAR(64)', d='Código de integridad del archivo.'),
    C('fecha_incorporacion', TS, default='SYSDATETIMEOFFSET()'),
    C('vigencia_hasta', D, null=True),
    FK('reemplaza_documento_id', 'documentos_legajo', null=True, uq=True, d='Documento anterior que este reemplaza.'),
    FK('usuario_id', 'usuarios_internos', null=True, d='Usuario que lo incorporó.'),
], ck=[('CK_documentos_legajo_mime', "tipo_mime IN ('application/pdf', 'image/jpeg', 'image/png')"),
       ('CK_documentos_legajo_tamano', 'tamano_bytes BETWEEN 1 AND 5242880')],
   idx=[('IX_documentos_legajo_cliente', ['cliente_id', 'tipo_documentacion_id'], None, None)])

add('consentimientos', 'SS-02', 'Consentimiento', 'Historial de aceptaciones y revocaciones; rige el último registro de cada tipo (RN-64, RNF-14).', [
    ID(),
    FK('cliente_id', 'clientes'),
    EN('tipo', 'TipoConsentimiento'),
    C('otorgado', B, d='1 = otorgado, 0 = revocado.'),
    C('fecha', TS, default='SYSDATETIMEOFFSET()'),
    EN('medio', 'MedioConsentimiento'),
], idx=[('IX_consentimientos_cliente', ['cliente_id', 'tipo', 'fecha'], None, None)])

# ============================================================== SS-03
add('lineas_credito', 'SS-03', 'LineaCredito', 'Productos crediticios ofrecidos por la entidad.', [
    ID(),
    C('codigo', 'VARCHAR(20)', uq=True),
    C('nombre', 'NVARCHAR(100)'),
    C('descripcion', 'NVARCHAR(500)', null=True),
    EN('estado', 'EstadoLinea', default="'Activa'"),
    VF(),
    CREADO(), ACT(),
])

add('versiones_condiciones', 'SS-03', 'VersionCondiciones', 'Condiciones versionadas de una línea. Una versión registrada no se modifica: los cambios generan una versión nueva (RN-15).', [
    ID(),
    FK('linea_credito_id', 'lineas_credito'),
    C('numero', 'INT', d='Número correlativo dentro de la línea.'),
    C('vigente_desde', TS),
    C('tna', TASA, d='Tasa nominal anual en tanto por uno.'),
    C('gasto_otorgamiento_pct', TASA, d='Porcentaje del capital en tanto por uno (RN-68).'),
    C('gasto_administrativo_cuota', M2, d='Gasto fijo por cuota (RN-68); 0 cuando el gasto es porcentual.'),
    EN('tipo_gasto_administrativo', 'TipoGastoAdministrativo', default="'Fijo'", d='Importe fijo por cuota o porcentaje del capital en cada cuota (D-28).'),
    C('gasto_administrativo_pct', TASA, null=True, d='Porcentaje del capital que se cobra en cada cuota, en tanto por uno (D-28); nulo si el gasto es fijo.'),
    C('monto_minimo', M2), C('monto_maximo', M2),
    C('cuotas_minimas', 'SMALLINT'), C('cuotas_maximas', 'SMALLINT'),
    C('edad_minima', 'TINYINT', null=True, d='Solo personas humanas.'),
    C('edad_maxima', 'TINYINT', null=True),
    C('antiguedad_minima_meses', 'SMALLINT', null=True, d='Solo personas jurídicas.'),
    C('admite_persona_humana', B, d='Reemplaza el atributo tiposClienteAdmitidos del dominio.'),
    C('admite_persona_juridica', B),
    C('vigencia_inicio', D), C('vigencia_fin', D),
], uq=[('UQ_versiones_condiciones_numero', ['linea_credito_id', 'numero'], None)],
   ck=[('CK_versiones_condiciones_montos', 'monto_minimo > 0 AND monto_minimo < monto_maximo'),
       ('CK_versiones_condiciones_cuotas', 'cuotas_minimas >= 1 AND cuotas_minimas < cuotas_maximas'),
       ('CK_versiones_condiciones_edades', 'edad_minima IS NULL OR edad_maxima IS NULL OR edad_minima < edad_maxima'),
       ('CK_versiones_condiciones_gasto_administrativo', "(tipo_gasto_administrativo = 'Fijo' AND gasto_administrativo_pct IS NULL) OR (tipo_gasto_administrativo = 'Porcentual' AND gasto_administrativo_pct > 0 AND gasto_administrativo_pct <= 0.1 AND gasto_administrativo_cuota = 0)"),
       ('CK_versiones_condiciones_vigencia', 'vigencia_inicio <= vigencia_fin'),
       ('CK_versiones_condiciones_tasas', 'tna >= 0 AND gasto_otorgamiento_pct >= 0 AND gasto_otorgamiento_pct < 1 AND gasto_administrativo_cuota >= 0'),
       ('CK_versiones_condiciones_tipos', 'admite_persona_humana = 1 OR admite_persona_juridica = 1')])

add('condiciones_categorias', 'SS-03', '(atributo multivaluado)', 'Categorías de ingresos admitidas por una versión; normaliza el atributo categoriasAdmitidas del dominio.', [
    FK('version_condiciones_id', 'versiones_condiciones'),
    EN('categoria_ingresos', 'CategoriaIngresos'),
], pk=['version_condiciones_id', 'categoria_ingresos'])

add('requisitos_documentales', 'SS-03', 'RequisitoDocumental', 'Documentación exigida por una versión según la categoría de ingresos (RN-11).', [
    ID(),
    FK('version_condiciones_id', 'versiones_condiciones'),
    FK('tipo_documentacion_id', 'tipos_documentacion'),
    EN('categoria_ingresos', 'CategoriaIngresos', null=True, d='Nulo cuando el requisito aplica a todas las categorías.'),
    C('obligatorio', B, default='1'),
], uq=[('UQ_requisitos_documentales', ['version_condiciones_id', 'tipo_documentacion_id', 'categoria_ingresos'], None)])

# ============================================================== SS-04
add('dias_no_habiles', 'SS-04', 'DiaNoHabil', 'Calendario operativo para el cómputo de horas hábiles de las aprobaciones (RN-49, CU-82).', [
    C('fecha', D, pk=True),
    C('descripcion', 'NVARCHAR(150)'),
    EN('tipo', 'TipoDiaNoHabil'),
])

add('simulaciones_comparativas', 'SS-04', 'SimulacionComparativa', 'Simulación de todas las líneas admitidas para un cliente con una sola capacidad de pago (CU-99, D-10). La alternativa recomendada de cada línea viable se guarda como una simulación vinculada, que puede formalizarse con CU-22; las líneas no viables se conservan con su motivo. No genera solicitud ni deuda (RN-17).', [
    ID(),
    FK('cliente_id', 'clientes'),
    FK('version_parametros_id', 'versiones_parametros', d='Parámetros de estructuración aplicados.'),
    C('fecha_generacion', TS, default='SYSDATETIMEOFFSET()'),
    C('ingresos_considerados', M2), C('obligaciones_vigentes', M2),
    C('capacidad_pago', M2, d='Capacidad de pago común a todas las líneas (RN-22).'),
    C('importe_pedido', M2, null=True), C('cuotas_pedidas', 'SMALLINT', null=True),
    EN('criterio', 'CriterioComparacion', d='Orden de las alternativas: mayor monto o menor CFTEA.'),
    C('lineas_no_viables', JSON, null=True, d='Líneas descartadas con su motivo, en JSON.'),
    C('vence', TS, d='Generación + 7 días corridos (RN-48).'),
    FK('usuario_id', 'usuarios_internos'),
], ck=[('CK_simulaciones_comparativas_pedido', '(importe_pedido IS NULL OR importe_pedido > 0) AND (cuotas_pedidas IS NULL OR cuotas_pedidas > 0)'),
       ('CK_simulaciones_comparativas_no_viables', 'lineas_no_viables IS NULL OR ISJSON(lineas_no_viables) = 1')],
   idx=[('IX_simulaciones_comparativas_cliente', ['cliente_id', 'fecha_generacion'], None, None)])

add('simulaciones', 'SS-04', 'Simulacion', 'Cálculos preliminares; no generan solicitud ni deuda (RN-17).', [
    ID(),
    FK('cliente_id', 'clientes'),
    FK('version_condiciones_id', 'versiones_condiciones'),
    C('fecha_generacion', TS, default='SYSDATETIMEOFFSET()'),
    C('importe', M2), C('cantidad_cuotas', 'SMALLINT'),
    C('valor_cuota', M2, d='Cuota total de la primera cuota (RN-68).'),
    C('tea', TASA), C('cftea', TASA),
    C('vence', TS, d='Generación + 7 días corridos (RN-48).'),
    EN('estado', 'EstadoVigencia', default="'Vigente'"),
    FK('simulacion_comparativa_id', 'simulaciones_comparativas', null=True, d='Simulación comparativa que la generó (CU-99).'),
    C('posicion', 'TINYINT', null=True, d='Orden de la alternativa dentro de la comparación.'),
], ck=[('CK_simulaciones_comparativa', '(simulacion_comparativa_id IS NULL AND posicion IS NULL) OR (simulacion_comparativa_id IS NOT NULL AND posicion >= 1)')],
   idx=[('IX_simulaciones_cliente', ['cliente_id', 'fecha_generacion'], None, None),
        ('IX_simulaciones_simulacion_comparativa_id', ['simulacion_comparativa_id'], None, 'simulacion_comparativa_id IS NOT NULL')])

add('solicitudes_credito', 'SS-04', 'SolicitudCredito', 'Peticiones formales de financiación; su ciclo de vida se describe en 4.2.3.', [
    ID(),
    C('numero', 'INT', default='NEXT VALUE FOR seq_solicitudes', uq=True, d='Número visible, asignado por secuencia.'),
    FK('cliente_id', 'clientes'),
    FK('version_condiciones_id', 'versiones_condiciones', d='Versión con la que se rige (RN-15).'),
    FK('simulacion_id', 'simulaciones', null=True, d='Simulación de origen.'),
    EN('origen', 'OrigenSolicitud'),
    C('fecha_registro', TS, default='SYSDATETIMEOFFSET()'),
    C('importe', M2), C('cantidad_cuotas', 'SMALLINT'),
    EN('estado', 'EstadoSolicitud', default="'PendienteVerificacion'"),
    EN('tipo_rechazo', 'TipoRechazo', null=True),
    C('motivo_cancelacion', 'NVARCHAR(500)', null=True),
    C('ultimo_movimiento', TS, default='SYSDATETIMEOFFSET()', d='Base del control de inactividad de 30 días (RN-72).'),
    FK('usuario_id', 'usuarios_internos', d='Analista que la registró.'),
    VF(),
], ck=[('CK_solicitudes_rechazo', "(estado = 'Rechazada' AND tipo_rechazo IS NOT NULL) OR (estado <> 'Rechazada' AND tipo_rechazo IS NULL)"),
       ('CK_solicitudes_cancelacion', "estado <> 'Cancelada' OR motivo_cancelacion IS NOT NULL"),
       ('CK_solicitudes_importe', 'importe > 0 AND cantidad_cuotas > 0')],
   idx=[('IX_solicitudes_cliente', ['cliente_id', 'estado'], None, None),
        ('IX_solicitudes_estado', ['estado', 'ultimo_movimiento'], None, None)])

add('transiciones_solicitud', 'SS-04', '(historial de estados)', 'Historial de cambios de estado de la solicitud: origen, destino, instante, responsable y motivo (3.5.3).', [
    ID(True),
    FK('solicitud_id', 'solicitudes_credito'),
    EN('estado_origen', 'EstadoSolicitud', null=True, d='Nulo en el registro inicial.'),
    EN('estado_destino', 'EstadoSolicitud'),
    C('fecha_hora', TS, default='SYSDATETIMEOFFSET()'),
    FK('usuario_id', 'usuarios_internos', null=True),
    C('proceso', 'VARCHAR(60)', null=True, d='Comando que produjo la transición, si fue automática.'),
    C('motivo', 'NVARCHAR(500)', null=True),
], ck=[('CK_transiciones_responsable', 'usuario_id IS NOT NULL OR proceso IS NOT NULL')],
   idx=[('IX_transiciones_solicitud', ['solicitud_id', 'fecha_hora'], None, None)])

add('revisiones_documentales', 'SS-04', 'RevisionDocumental', 'Verificaciones de la documentación de una solicitud para su versión de condiciones (3.5.4).', [
    ID(),
    FK('solicitud_id', 'solicitudes_credito'),
    FK('version_condiciones_id', 'versiones_condiciones'),
    C('fecha', TS, default='SYSDATETIMEOFFSET()'),
    EN('estado', 'EstadoRevision', default="'PendienteRevision'"),
    C('observaciones', 'NVARCHAR(1000)', null=True),
    C('motivo_rechazo', 'NVARCHAR(500)', null=True),
    FK('usuario_id', 'usuarios_internos', null=True, d='Analista que la resolvió.'),
], ck=[('CK_revisiones_rechazo', "estado <> 'Rechazada' OR motivo_rechazo IS NOT NULL")],
   idx=[('IX_revisiones_solicitud', ['solicitud_id', 'fecha'], None, None)])

add('verificaciones_documento', 'SS-04', 'VerificacionDocumento', 'Resultado de la verificación de cada documento del legajo dentro de una revisión.', [
    ID(),
    FK('revision_documental_id', 'revisiones_documentales'),
    FK('documento_legajo_id', 'documentos_legajo'),
    EN('resultado', 'ResultadoVerificacion'),
    EN('motivo_observacion', 'MotivoObservacion', null=True),
    C('observacion', 'NVARCHAR(500)', null=True),
    C('fecha_verificacion', TS, default='SYSDATETIMEOFFSET()'),
], uq=[('UQ_verificaciones_documento', ['revision_documental_id', 'documento_legajo_id'], None)],
   ck=[('CK_verificaciones_motivo', "resultado = 'Conforme' OR motivo_observacion IS NOT NULL")])

add('evaluaciones_crediticias', 'SS-04', 'EvaluacionCrediticia', 'Análisis de riesgo con sus dimensiones independientes (3.5.5). La contingencia es una nueva fila vinculada con el intento no completado (RN-21).', [
    ID(),
    FK('solicitud_id', 'solicitudes_credito'),
    FK('version_parametros_id', 'versiones_parametros', d='Parámetros de evaluación aplicados (RN-20).'),
    FK('evaluacion_origen_id', 'evaluaciones_crediticias', null=True, uq=True, d='Evaluación automática no completada a la que responde la contingencia.'),
    EN('modalidad', 'ModalidadEvaluacion'),
    EN('procesamiento', 'EstadoProcesamiento', default="'Pendiente'"),
    EN('resultado', 'ResultadoEvaluacion', null=True),
    C('puntaje', 'DECIMAL(5,2)', null=True, d='0 a 100 (RN-69).'),
    EN('categoria_riesgo', 'CategoriaRiesgo', null=True),
    C('indicadores', JSON, null=True, d='Valor y peso de cada indicador en JSON (RN-69).'),
    C('fecha_inicio', TS, null=True),
    C('fecha_finalizacion', TS, null=True),
    C('vence', TS, null=True, d='Finalización + 30 días corridos (RN-20).'),
    EN('vigencia', 'EstadoVigenciaEvaluacion', null=True),
    C('motivo_no_completada', 'NVARCHAR(500)', null=True),
    C('recomendacion', 'NVARCHAR(1000)', null=True, d='Recomendación del Analista (contingencia).'),
    C('justificacion', 'NVARCHAR(2000)', null=True),
    FK('usuario_id', 'usuarios_internos', null=True, d='Analista de la contingencia.'),
    VF(),
], uq=[('UQ_evaluaciones_vigente', ['solicitud_id'], "vigencia = 'Vigente'")],
   ck=[('CK_evaluaciones_finalizada', "(procesamiento = 'Finalizada' AND resultado IS NOT NULL AND vigencia IS NOT NULL AND vence IS NOT NULL) OR (procesamiento <> 'Finalizada' AND resultado IS NULL AND vigencia IS NULL)"),
       ('CK_evaluaciones_contingencia', "modalidad = 'Automatica' OR (evaluacion_origen_id IS NOT NULL AND recomendacion IS NOT NULL AND justificacion IS NOT NULL AND usuario_id IS NOT NULL AND (resultado IS NULL OR resultado = 'RequiereAnalisisGerencial'))"),
       ('CK_evaluaciones_puntaje', 'puntaje IS NULL OR puntaje BETWEEN 0 AND 100'),
       ('CK_evaluaciones_indicadores', 'indicadores IS NULL OR ISJSON(indicadores) = 1')],
   idx=[('IX_evaluaciones_pendientes', ['procesamiento'], None, "procesamiento = 'Pendiente'"),
        ('IX_evaluaciones_vencimiento', ['vence'], None, "vigencia = 'Vigente'")])

add('antecedentes_externos', 'SS-04', 'AntecedenteExterno', 'Información obtenida de la Central de Deudores para una evaluación.', [
    ID(True),
    FK('evaluacion_id', 'evaluaciones_crediticias'),
    C('fuente', 'VARCHAR(30)', d='Servicio consultado (por ejemplo, BCRA-CENDEU) o Manual en contingencia.'),
    C('entidad', 'NVARCHAR(150)'),
    C('situacion', 'TINYINT', d='Situación 1 a 6 informada.'),
    C('monto', M2),
    C('periodo', 'CHAR(6)', d='Período informado con formato AAAAMM.'),
], ck=[('CK_antecedentes_situacion', 'situacion BETWEEN 1 AND 6')],
   idx=[('IX_antecedentes_evaluacion', ['evaluacion_id'], None, None)])

add('estructuraciones', 'SS-04', 'Estructuracion', 'Determinación de la capacidad de pago y del monto máximo financiable (RF-14, CU-87).', [
    ID(),
    FK('solicitud_id', 'solicitudes_credito'),
    FK('version_parametros_id', 'versiones_parametros', d='Parámetros de estructuración aplicados.'),
    C('fecha', TS, default='SYSDATETIMEOFFSET()'),
    C('ingresos_considerados', M2),
    C('obligaciones_vigentes', M2, d='Cuotas vigentes y comprometidas del cliente (RN-18).'),
    C('capacidad_pago', M2),
    C('monto_maximo_financiable', M2),
], idx=[('IX_estructuraciones_solicitud', ['solicitud_id', 'fecha'], None, None)])

add('alternativas_financiacion', 'SS-04', 'AlternativaFinanciacion', 'Combinaciones viables de importe y plazo (RN-23, RN-48).', [
    ID(),
    FK('estructuracion_id', 'estructuraciones'),
    C('importe', M2), C('cantidad_cuotas', 'SMALLINT'),
    C('valor_cuota', M2), C('tea', TASA), C('cftea', TASA),
    C('total_a_pagar', M2),
    C('vence', TS, d='Generación + 7 días corridos (RN-48).'),
    EN('estado', 'EstadoAlternativa', default="'Vigente'"),
    C('motivo_descarte', 'NVARCHAR(300)', null=True),
    C('fecha_conformidad', TS, null=True),
    C('medio_conformidad', 'NVARCHAR(100)', null=True),
], uq=[('UQ_alternativas_confirmada', ['estructuracion_id'], "estado = 'Confirmada'")],
   ck=[('CK_alternativas_conformidad', "estado <> 'Confirmada' OR fecha_conformidad IS NOT NULL")])

add('resoluciones', 'SS-04', 'Resolucion', 'Decisiones sobre una solicitud, automáticas o gerenciales. La aprobación conserva su vencimiento calculado (RN-49).', [
    ID(),
    FK('solicitud_id', 'solicitudes_credito'),
    EN('decision', 'DecisionResolucion'),
    C('fundamento', 'NVARCHAR(2000)', null=True),
    C('fecha', TS, default='SYSDATETIMEOFFSET()'),
    C('automatica', B, default='0'),
    FK('usuario_id', 'usuarios_internos', null=True, d='Gerente que resolvió; nulo si fue automática.'),
    FK('alternativa_id', 'alternativas_financiacion', null=True, d='Plan aprobado (RN-22).'),
    C('importe_aprobado', M2, null=True), C('cuotas_aprobadas', 'SMALLINT', null=True),
    C('vence_aprobacion', TS, null=True, d='72 horas hábiles calculadas al registrar la aprobación (RN-49).'),
    C('vigente', B, default='1', d='0 cuando la aprobación quedó sin efecto (RN-61).'),
    EN('etapa_retorno', 'EtapaRetorno', null=True, d='Etapa indicada en una devolución para revisión.'),
], uq=[('UQ_resoluciones_aprobacion_vigente', ['solicitud_id'], "decision = 'Aprobada' AND vigente = 1")],
   ck=[('CK_resoluciones_responsable', '(automatica = 1 AND usuario_id IS NULL) OR (automatica = 0 AND usuario_id IS NOT NULL AND fundamento IS NOT NULL)'),
       ('CK_resoluciones_aprobacion', "decision <> 'Aprobada' OR (vence_aprobacion IS NOT NULL AND importe_aprobado IS NOT NULL AND cuotas_aprobadas IS NOT NULL)"),
       ('CK_resoluciones_devolucion', "decision <> 'DevueltaRevision' OR etapa_retorno IS NOT NULL")],
   idx=[('IX_resoluciones_vencimiento', ['vence_aprobacion'], None, "decision = 'Aprobada' AND vigente = 1")])

add('contratos_prestamo', 'SS-04', 'ContratoPrestamo', 'Contrato de préstamo generado para cada aprobación vigente, con la constancia firmada incorporada al legajo y la fecha en que el cliente recibió el contrato, que inicia el plazo de revocación (RN-78, RN-79, D-31).', [
    ID(),
    FK('solicitud_id', 'solicitudes_credito'),
    FK('resolucion_id', 'resoluciones', uq=True, d='Aprobación cuyas condiciones instrumenta.'),
    C('fecha_generacion', TS, default='SYSDATETIMEOFFSET()'),
    FK('documento_legajo_id', 'documentos_legajo', null=True, d='Copia escaneada de la constancia firmada (tipo CONTRATO_FIRMADO).'),
    C('fecha_recepcion', D, null=True, d='Día en que el cliente recibió su ejemplar firmado.'),
    C('fecha_registro', TS, null=True),
    FK('usuario_id', 'usuarios_internos', null=True, d='Analista que incorporó la constancia.'),
], ck=[('CK_contratos_prestamo_constancia', '(documento_legajo_id IS NULL AND fecha_recepcion IS NULL AND fecha_registro IS NULL AND usuario_id IS NULL) OR (documento_legajo_id IS NOT NULL AND fecha_recepcion IS NOT NULL AND fecha_registro IS NOT NULL AND usuario_id IS NOT NULL)')])

add('liquidaciones', 'SS-04', 'Liquidacion', 'Registro del desembolso. Una sola liquidación por solicitud (RNF-06, RN-25).', [
    ID(),
    FK('solicitud_id', 'solicitudes_credito', uq=True),
    FK('credito_id', 'creditos', uq=True, d='Crédito generado, registrado en la misma transacción (RN-26).'),
    C('fecha', TS, default='SYSDATETIMEOFFSET()'),
    C('capital', M2), C('gasto_otorgamiento', M2), C('iva_gastos', M2),
    C('importe_neto', M2, d='Capital menos gasto de otorgamiento y su IVA (RN-25).'),
    EN('medio_desembolso', 'MedioDesembolso'),
    C('cuenta_destino', 'CHAR(22)', null=True, d='CBU o CVU de titularidad verificada.'),
    C('comprobante', 'VARCHAR(100)', null=True, d='Comprobante de la transferencia.'),
    FK('movimiento_caja_id', 'movimientos_caja', null=True, big=True, uq=True, d='Egreso de la jornada en el desembolso en efectivo.'),
    FK('usuario_id', 'usuarios_internos'),
], ck=[('CK_liquidaciones_neto', 'importe_neto = capital - gasto_otorgamiento - iva_gastos AND importe_neto > 0'),
       ('CK_liquidaciones_medio', "(medio_desembolso = 'Transferencia' AND cuenta_destino IS NOT NULL AND movimiento_caja_id IS NULL) OR (medio_desembolso = 'Efectivo' AND movimiento_caja_id IS NOT NULL AND cuenta_destino IS NULL)"),
       ('CK_liquidaciones_transferencia', "medio_desembolso = 'Transferencia'")])

# ============================================================== SS-05
add('cajas', 'SS-05', 'Caja', 'Unidades operativas de cobro (CU-80, CU-81).', [
    ID(),
    C('codigo', 'VARCHAR(10)', uq=True),
    C('denominacion', 'NVARCHAR(60)'),
    C('ubicacion', 'NVARCHAR(100)', null=True),
    C('observaciones', 'NVARCHAR(300)', null=True, d='Observaciones del alta (CU-80).'),
    EN('estado', 'EstadoCaja', default="'Habilitada'"),
])

add('asignaciones_caja', 'SS-05', 'AsignacionCaja', 'Asignación de una caja a un cajero, vigente desde su inicio hasta su fin inclusive (RN-28). Finalizarla fija el fin en el día anterior; una asignación finalizada antes de comenzar queda con un período vacío (fin = inicio − 1, D-21).', [
    ID(),
    FK('caja_id', 'cajas'),
    FK('usuario_id', 'usuarios_internos', d='Cajero asignado.'),
    C('desde', D), C('hasta', D, null=True),
], uq=[('UQ_asignaciones_caja_vigente', ['caja_id'], 'hasta IS NULL'),
       ('UQ_asignaciones_caja_clave', ['id', 'caja_id', 'usuario_id'], None)],
   ck=[('CK_asignaciones_caja_periodo', 'hasta IS NULL OR hasta >= DATEADD(day, -1, desde)')])

add('jornadas_caja', 'SS-05', 'JornadaCaja', 'Operaciones entre la apertura y el cierre de una caja. caja_id y usuario_id se repiten desde la asignación para impedir, con índices únicos, dos jornadas abiertas por caja o por cajero (RN-28).', [
    ID(),
    C('asignacion_caja_id', 'INT', d='Asignación vigente al abrir.'),
    C('caja_id', 'INT', d='Copia controlada de la asignación.'),
    C('usuario_id', 'INT', d='Copia controlada de la asignación.'),
    C('apertura', TS, default='SYSDATETIMEOFFSET()'),
    C('cierre', TS, null=True),
    C('fondo_inicial', M2, default='0', d='Siempre 0 desde D-30; conserva el fondo de las jornadas anteriores.'),
    C('efectivo_esperado', M2, null=True, d='Arqueo de las jornadas anteriores a D-30.'),
    C('efectivo_declarado', M2, null=True, d='Arqueo de las jornadas anteriores a D-30.'),
    C('diferencia', M2, null=True, d='Arqueo de las jornadas anteriores a D-30; la conciliación por medio está en conciliaciones_jornada.'),
    C('explicacion', 'NVARCHAR(500)', null=True),
    EN('estado', 'EstadoJornada', default="'Abierta'"),
    VF(),
], uq=[('UQ_jornadas_caja_abierta', ['caja_id'], "estado = 'Abierta'"),
       ('UQ_jornadas_cajero_abierta', ['usuario_id'], "estado = 'Abierta'")],
   fkx=[('FK_jornadas_caja_asignacion', ['asignacion_caja_id', 'caja_id', 'usuario_id'], 'asignaciones_caja', ['id', 'caja_id', 'usuario_id'])],
   ck=[('CK_jornadas_caja_cierre', "(estado = 'Abierta' AND cierre IS NULL) OR (estado = 'Cerrada' AND cierre IS NOT NULL)"),
       ('CK_jornadas_caja_diferencia', 'diferencia IS NULL OR diferencia = 0 OR explicacion IS NOT NULL'),
       ('CK_jornadas_caja_fondo', 'fondo_inicial >= 0'),
       ('CK_jornadas_caja_sin_efectivo', 'fondo_inicial = 0 AND efectivo_declarado IS NULL')])

add('conciliaciones_jornada', 'SS-05', 'ConciliacionMedio', 'Conciliación del cierre de una jornada para cada medio de pago: total de cobros registrados, total acreditado en las cuentas de la entidad según el extracto o el resumen del procesador, y la diferencia con su explicación (RN-32, D-30).', [
    ID(),
    FK('jornada_caja_id', 'jornadas_caja'),
    C('medio', 'VARCHAR(20)', d='Medio de pago conciliado; no incluye el efectivo (RN-77).'),
    C('total_registrado', M2, d='Suma de lo aplicado con el medio en los cobros confirmados de la jornada.'),
    C('total_acreditado', M2, d='Total que informa el Cajero según el extracto o el resumen del procesador.'),
    C('diferencia', M2, d='Acreditado menos registrado.'),
    C('explicacion', 'NVARCHAR(500)', null=True),
], uq=[('UQ_conciliaciones_jornada_medio', ['jornada_caja_id', 'medio'], None)],
   ck=[('CK_conciliaciones_jornada_medio', "medio IN ('TarjetaDebito', 'TarjetaCredito', 'Transferencia', 'PagoElectronico')"),
       ('CK_conciliaciones_jornada_totales', 'total_registrado >= 0 AND total_acreditado >= 0 AND diferencia = total_acreditado - total_registrado'),
       ('CK_conciliaciones_jornada_explicacion', 'diferencia = 0 OR explicacion IS NOT NULL')])

add('cobros', 'SS-05', 'Cobro', 'Pagos confirmados de una o más cuotas, en ventanilla o electrónicos (RN-29, RN-30).', [
    ID(True),
    C('numero', 'BIGINT', default='NEXT VALUE FOR seq_cobros', uq=True),
    FK('cliente_id', 'clientes'),
    FK('jornada_caja_id', 'jornadas_caja', null=True, d='Jornada del cobro en ventanilla.'),
    FK('transaccion_pasarela_id', 'transacciones_pasarela', null=True, big=True, d='Transacción que lo confirmó (canal electrónico).'),
    C('fecha_hora', TS, default='SYSDATETIMEOFFSET()', d='Instante de registro.'),
    C('fecha_efectiva_pago', TS, d='Instante efectivo del pago, base de los días de atraso (RN-55).'),
    C('importe', M2),
    EN('tipo', 'TipoCobro', default="'Ordinario'"),
    EN('canal', 'CanalCobro'),
    EN('estado', 'EstadoCobro', default="'Confirmado'"),
    FK('usuario_id', 'usuarios_internos', null=True, d='Cajero; nulo en cobros electrónicos.'),
    C('cambio', M2, default='0', d='Vuelto en efectivo de los cobros anteriores a D-30; siempre 0 desde entonces.'),
], uq=[('UQ_cobros_transaccion', ['transaccion_pasarela_id'], 'transaccion_pasarela_id IS NOT NULL')],
   ck=[('CK_cobros_canal_datos', "(canal = 'Ventanilla' AND jornada_caja_id IS NOT NULL AND usuario_id IS NOT NULL) OR (canal = 'Electronico' AND transaccion_pasarela_id IS NOT NULL AND cambio = 0)"),
       ('CK_cobros_importe', 'importe > 0'),
       ('CK_cobros_cambio', 'cambio >= 0'),
       ('CK_cobros_sin_cambio', 'cambio = 0')],
   idx=[('IX_cobros_cliente', ['cliente_id', 'fecha_hora'], None, None),
        ('IX_cobros_jornada', ['jornada_caja_id'], ['importe', 'cambio', 'estado'], None)])

add('cobros_medios', 'SS-05', 'MedioCobro', 'Medios con los que se pagó un cobro y el importe aplicado con cada uno. Cada medio lleva su número de operación (RN-29, RN-77, D-11); las filas en efectivo y el cambio corresponden a cobros anteriores a D-30. El cobro electrónico tiene una sola fila PagoElectronico.', [
    ID(True),
    FK('cobro_id', 'cobros', big=True),
    EN('medio', 'MedioPago'),
    C('importe', M2, d='Importe aplicado al cobro con este medio.'),
    C('importe_recibido', M2, d='Importe entregado; en efectivo puede superar al aplicado.'),
    C('numero_operacion', 'VARCHAR(60)', null=True, d='Obligatorio para los medios distintos del efectivo y del pago electrónico.'),
], uq=[('UQ_cobros_medios_efectivo', ['cobro_id'], "medio = 'Efectivo'"),
       ('UQ_cobros_medios_operacion', ['cobro_id', 'medio', 'numero_operacion'], 'numero_operacion IS NOT NULL')],
   ck=[('CK_cobros_medios_importe', 'importe > 0 AND importe_recibido >= importe'),
       ('CK_cobros_medios_cambio', "medio = 'Efectivo' OR importe_recibido = importe"),
       ('CK_cobros_medios_operacion', "medio IN ('Efectivo', 'PagoElectronico') OR numero_operacion IS NOT NULL"),
       ('CK_cobros_medios_sin_efectivo', "medio <> 'Efectivo'")],
   idx=[('IX_cobros_medios_cobro', ['cobro_id'], ['medio', 'importe'], None)])

add('movimientos_caja', 'SS-05', 'MovimientoCaja', 'Ingresos y egresos de una jornada (RN-28, RN-32).', [
    ID(True),
    FK('jornada_caja_id', 'jornadas_caja'),
    C('fecha_hora', TS, default='SYSDATETIMEOFFSET()'),
    EN('tipo', 'TipoMovimientoCaja'),
    C('importe', M2),
    EN('medio', 'MedioPago'),
    C('concepto', 'NVARCHAR(200)', null=True),
    FK('cobro_id', 'cobros', null=True, big=True, d='Cobro que originó el ingreso.'),
    FK('cobro_medio_id', 'cobros_medios', null=True, big=True, uq=True, d='Medio del cobro que originó el ingreso: cada medio genera su movimiento (D-11).'),
], ck=[('CK_movimientos_caja_importe', 'importe > 0'),
       ('CK_movimientos_caja_cobro', "(tipo = 'Cobro' AND cobro_id IS NOT NULL AND cobro_medio_id IS NOT NULL) OR (tipo <> 'Cobro' AND cobro_id IS NULL AND cobro_medio_id IS NULL)"),
       ('CK_movimientos_caja_desembolso', "tipo <> 'Desembolso' OR medio = 'Efectivo'"),
       ('CK_movimientos_caja_solo_cobros', "tipo = 'Cobro'")],
   idx=[('IX_movimientos_caja_jornada', ['jornada_caja_id', 'tipo'], ['medio', 'importe'], None)])

add('imputaciones', 'SS-05', 'Imputacion', 'Distribución del importe de un cobro o de una aplicación de saldo entre cuotas y conceptos, según la prelación de RN-29.', [
    ID(True),
    FK('cobro_id', 'cobros', null=True, big=True),
    FK('aplicacion_saldo_id', 'aplicaciones_saldo', null=True),
    FK('cuota_id', 'cuotas', big=True),
    EN('concepto', 'ConceptoDeuda'),
    C('importe', M2),
    C('parcial', B, default='0', d='La imputación no canceló el concepto de la cuota: cobro ordinario parcial (D-12) o imputaciones de RN-56 y RN-57.'),
], ck=[('CK_imputaciones_origen', '(cobro_id IS NOT NULL AND aplicacion_saldo_id IS NULL) OR (cobro_id IS NULL AND aplicacion_saldo_id IS NOT NULL)'),
       ('CK_imputaciones_importe', 'importe > 0')],
   idx=[('IX_imputaciones_cuota', ['cuota_id'], ['concepto', 'importe'], None),
        ('IX_imputaciones_cobro', ['cobro_id'], None, 'cobro_id IS NOT NULL')])

add('comprobantes_pago', 'SS-05', 'ComprobantePago', 'Constancia de un cobro confirmado; su reemisión no genera un nuevo cobro (RN-31).', [
    ID(True),
    FK('cobro_id', 'cobros', big=True, uq=True),
    C('numero', 'BIGINT', default='NEXT VALUE FOR seq_comprobantes', uq=True),
    C('fecha_emision', TS, default='SYSDATETIMEOFFSET()'),
    C('cantidad_reemisiones', 'INT', default='0'),
])

add('anulaciones_cobro', 'SS-05', 'AnulacionCobro', 'Anulación gerencial de un cobro; conserva el original (RN-33).', [
    ID(),
    FK('cobro_id', 'cobros', big=True, uq=True),
    C('fecha', TS, default='SYSDATETIMEOFFSET()'),
    C('motivo', 'NVARCHAR(500)'),
    FK('usuario_id', 'usuarios_internos', d='Gerente que anuló.'),
    FK('saldo_a_favor_id', 'saldos_a_favor', null=True, uq=True, d='Saldo a favor generado por el importe recibido (RN-33).'),
])

add('ordenes_pago_electronico', 'SS-05', 'OrdenPagoElectronico', 'Solicitudes de pago de importe fijo enviadas a la pasarela (RN-54).', [
    ID(True),
    C('numero', 'BIGINT', default='NEXT VALUE FOR seq_ordenes', uq=True),
    FK('cliente_id', 'clientes'),
    C('importe', M2),
    C('emision', TS, default='SYSDATETIMEOFFSET()'),
    C('vence', TS, d='00:00 local del día siguiente a la emisión, límite exclusivo (3.5.1).'),
    C('referencia_externa', 'VARCHAR(100)', null=True, d='Identificador de la orden en la pasarela.'),
    C('medio_presentado', 'VARCHAR(30)', null=True, d='Portal o Ventanilla.'),
    C('url_pago', 'NVARCHAR(500)', null=True, d='Enlace o código QR de pago que devolvió la pasarela (CU-34 A7).'),
    EN('estado', 'EstadoOrden', default="'PendienteConfirmacion'"),
    VF(),
], uq=[('UQ_ordenes_referencia', ['referencia_externa'], 'referencia_externa IS NOT NULL')],
   ck=[('CK_ordenes_importe', 'importe > 0 AND vence > emision')],
   idx=[('IX_ordenes_cliente', ['cliente_id', 'estado'], None, None),
        ('IX_ordenes_pendientes', ['estado', 'vence'], None, "estado IN ('PendienteConfirmacion', 'EnConciliacion')")])

add('ordenes_pago_cuotas', 'SS-05', '(asociación Orden–Cuota)', 'Cuotas incluidas en una orden y su importe al emitirla.', [
    FK('orden_pago_id', 'ordenes_pago_electronico', big=True),
    FK('cuota_id', 'cuotas', big=True),
    C('importe', M2, d='Total actualizado de la cuota al emitir la orden.'),
], pk=['orden_pago_id', 'cuota_id'])

add('notificaciones_pasarela', 'SS-05', '(técnica)', 'Bandeja de entrada de notificaciones de la pasarela: se registran y se responde el acuse; CU-90 las procesa en segundo plano (RNF-08).', [
    ID(True),
    C('recibida_en', TS, default='SYSDATETIMEOFFSET()'),
    C('id_evento', 'VARCHAR(100)', null=True, d='Identificador de la notificación informado por la pasarela.'),
    C('firma_valida', B, d='Resultado de la validación de la firma o secreto compartido.'),
    C('cuerpo', JSON, d='Contenido recibido, sin datos de tarjetas.'),
    EN('estado', 'EstadoNotificacion', default="'Pendiente'"),
    C('intentos', 'TINYINT', default='0'),
    C('procesada_en', TS, null=True),
    C('error', 'NVARCHAR(500)', null=True),
], uq=[('UQ_notificaciones_evento', ['id_evento'], 'id_evento IS NOT NULL')],
   idx=[('IX_notificaciones_pendientes', ['estado', 'recibida_en'], None, "estado = 'Pendiente'")])

add('transacciones_pasarela', 'SS-05', 'TransaccionPasarela', 'Transacciones informadas por la pasarela; el identificador externo es único para impedir registros duplicados (RN-30, RNF-06).', [
    ID(True),
    FK('orden_pago_id', 'ordenes_pago_electronico', big=True),
    C('id_externo', 'VARCHAR(100)', uq=True),
    C('estado', 'VARCHAR(30)', d='Estado informado por la pasarela, conservado sin traducir.'),
    C('importe', M2),
    C('moneda', 'CHAR(3)', default="'ARS'"),
    C('fecha_efectiva', TS, d='Instante verificado de realización (RN-55).'),
    C('fecha_recepcion', TS, default='SYSDATETIMEOFFSET()'),
], idx=[('IX_transacciones_orden', ['orden_pago_id'], None, None)])

add('incidencias_conciliacion', 'SS-05', 'IncidenciaConciliacion', 'Diferencias entre una transacción y su orden que requieren conciliación (RN-54, RN-56).', [
    ID(),
    FK('transaccion_pasarela_id', 'transacciones_pasarela', big=True),
    EN('tipo', 'TipoIncidencia'),
    C('fecha', TS, default='SYSDATETIMEOFFSET()'),
    EN('estado', 'EstadoIncidencia', default="'Pendiente'"),
    C('resolucion', 'NVARCHAR(1000)', null=True),
    FK('usuario_id', 'usuarios_internos', null=True),
], uq=[('UQ_incidencias_tipo', ['transaccion_pasarela_id', 'tipo'], None)],
   ck=[('CK_incidencias_resolucion', "estado = 'Pendiente' OR resolucion IS NOT NULL")])

add('saldos_a_favor', 'SS-05', 'SaldoAFavor', 'Importes recibidos aún no aplicados; separados de la deuda y de los fondos libres (RN-57).', [
    ID(),
    FK('cliente_id', 'clientes'),
    FK('transaccion_origen_id', 'transacciones_pasarela', null=True, big=True, uq=True, d='Transacción de la que proviene el excedente.'),
    C('fecha', TS, default='SYSDATETIMEOFFSET()'),
    C('importe_original', M2),
    C('importe_disponible', M2),
    C('importe_reservado', M2, default='0', d='Reservado por autorizaciones de devolución (RN-73).'),
    VF(),
], ck=[('CK_saldos_importes', 'importe_original > 0 AND importe_disponible >= 0 AND importe_reservado >= 0 AND importe_disponible + importe_reservado <= importe_original')],
   idx=[('IX_saldos_cliente', ['cliente_id'], ['importe_disponible'], None)])

add('aplicaciones_saldo', 'SS-05', 'AplicacionSaldo', 'Uso de un saldo a favor para cancelar obligaciones del mismo cliente (RN-57, CU-76).', [
    ID(),
    FK('saldo_a_favor_id', 'saldos_a_favor'),
    C('fecha', TS, default='SYSDATETIMEOFFSET()'),
    C('importe', M2),
    C('constancia_conformidad', 'NVARCHAR(300)'),
    FK('usuario_id', 'usuarios_internos', d='Cajero.'),
], ck=[('CK_aplicaciones_importe', 'importe > 0')])

add('autorizaciones_devolucion', 'SS-05', 'AutorizacionDevolucion', 'Autorizaciones gerenciales para devolver un saldo a favor; reservan el importe (RN-73).', [
    ID(),
    FK('saldo_a_favor_id', 'saldos_a_favor'),
    C('fecha', TS, default='SYSDATETIMEOFFSET()'),
    C('importe', M2),
    C('motivo', 'NVARCHAR(500)'),
    EN('destino', 'DestinoDevolucion'),
    C('cbu_cvu', 'CHAR(22)', null=True),
    C('cuit_titular', 'CHAR(11)', null=True),
    C('vence', TS, d='Autorización + 72 horas corridas.'),
    EN('estado', 'EstadoDevolucion', default="'PendienteEjecucion'"),
    FK('usuario_id', 'usuarios_internos', d='Gerente que autorizó.'),
    VF(),
    C('fecha_solicitud', D, d='Fecha de la constancia de la solicitud del cliente.'),
    C('medio_solicitud', 'NVARCHAR(100)', d='Medio por el que el cliente solicitó la devolución.'),
    C('entidad_bancaria', 'NVARCHAR(100)', null=True, d='Entidad de la cuenta destino de la transferencia.'),
    C('evidencia_titularidad', 'NVARCHAR(300)', null=True, d='Constancia de la verificación de titularidad de la cuenta (RN-73, RN-77).'),
    C('motivo_anulacion', 'NVARCHAR(500)', null=True),
    C('fecha_anulacion', TS, null=True),
    FK('usuario_anulacion_id', 'usuarios_internos', null=True, d='Gerente que anuló la autorización (CU-77 A5).'),
], ck=[('CK_autorizaciones_devolucion_cuenta', "destino = 'MedioOriginal' OR (cbu_cvu IS NOT NULL AND cuit_titular IS NOT NULL)"),
       ('CK_autorizaciones_devolucion_importe', 'importe > 0'),
       ('CK_autorizaciones_devolucion_titularidad', "destino = 'MedioOriginal' OR (entidad_bancaria IS NOT NULL AND evidencia_titularidad IS NOT NULL)"),
       ('CK_autorizaciones_devolucion_anulacion', "estado <> 'Anulada' OR (motivo_anulacion IS NOT NULL AND fecha_anulacion IS NOT NULL AND usuario_anulacion_id IS NOT NULL)")],
   idx=[('IX_autorizaciones_devolucion_vence', ['vence'], None, "estado = 'PendienteEjecucion'")])

add('ejecuciones_devolucion', 'SS-05', 'EjecucionDevolucion', 'Ejecución de una devolución por la pasarela o por transferencia (CU-78).', [
    ID(),
    FK('autorizacion_devolucion_id', 'autorizaciones_devolucion', uq=True),
    C('inicio', TS, default='SYSDATETIMEOFFSET()'),
    C('identificador_operacion', 'VARCHAR(100)', null=True),
    EN('resultado', 'ResultadoEjecucion', default="'Pendiente'"),
    C('fecha_confirmacion', TS, null=True),
    C('comprobante', 'VARCHAR(100)', null=True),
    FK('usuario_id', 'usuarios_internos', d='Cajero que ejecutó.'),
    C('importe', M2, d='Importe devuelto; igual al autorizado.'),
    C('cbu_destino', 'CHAR(22)', null=True, d='Cuenta a la que se transfirió (CU-78 A1).'),
    C('fecha_transferencia', D, null=True, d='Fecha del comprobante de la transferencia.'),
    C('detalle', 'NVARCHAR(500)', null=True, d='Respuesta de la pasarela o motivo del fallo.'),
], uq=[('UQ_ejecuciones_devolucion_operacion', ['identificador_operacion'], 'identificador_operacion IS NOT NULL')])

add('movimientos_fondos', 'SS-05', 'MovimientoFondos', 'Ingresos y egresos de fondos no originados en operaciones de crédito (CU-85).', [
    ID(),
    EN('tipo', 'TipoMovimientoFondos'),
    EN('caracter', 'CaracterMovimiento'),
    C('importe', M2),
    C('fecha', D),
    C('concepto', 'NVARCHAR(200)'),
    C('referencia', 'VARCHAR(100)', null=True),
    EN('estado', 'EstadoMovimientoFondos', default="'Realizado'"),
    FK('usuario_id', 'usuarios_internos'),
    FK('movimiento_corregido_id', 'movimientos_fondos', null=True, uq=True, d='Movimiento que este corrige con el importe inverso (RN-44, CU-85 A2); cada movimiento se corrige una sola vez.'),
    C('motivo', 'NVARCHAR(300)', null=True, d='Motivo de la corrección o de la anulación de un movimiento programado (CU-85 A2, A3).'),
], ck=[('CK_movimientos_fondos_importe', 'importe > 0'),
       ('CK_movimientos_fondos_motivo', "(movimiento_corregido_id IS NULL AND estado <> 'Anulado') OR motivo IS NOT NULL")],
   idx=[('IX_movimientos_fondos_fecha', ['fecha', 'estado'], None, None)])

add('afectaciones_fondos', 'SS-05', 'AfectacionFondos', 'Compromisos temporales y reservas de fondos. El compromiso se convierte en reserva en la misma fila, sin un segundo descuento (RN-41).', [
    ID(),
    EN('tipo', 'TipoAfectacion'),
    C('importe', M2),
    C('fecha', TS, default='SYSDATETIMEOFFSET()'),
    EN('estado', 'EstadoAfectacion', default="'Vigente'"),
    C('referencia_tipo', 'VARCHAR(20)', d='Solicitud u Oferta: referencia genérica, porque SS-05 no depende de SS-04 ni de SS-08 (3.7).'),
    C('referencia_id', 'INT', d='Identificador de la solicitud u oferta afectada.'),
    VF(),
], ck=[('CK_afectaciones_referencia', "referencia_tipo IN ('Solicitud', 'Oferta')"),
       ('CK_afectaciones_importe', 'importe > 0')],
   uq=[('UQ_afectaciones_vigente', ['referencia_tipo', 'referencia_id'], "estado = 'Vigente'")],
   idx=[('IX_afectaciones_vigentes', ['estado'], ['tipo', 'importe'], "estado = 'Vigente'")])

add('proyecciones_fondos', 'SS-05', 'ProyeccionFondos', 'Estimaciones de disponibilidad para un período futuro; conservan fecha y supuestos (RN-39, CU-92).', [
    ID(),
    C('fecha', TS, default='SYSDATETIMEOFFSET()'),
    C('periodo_dias', 'SMALLINT'),
    C('disponible_actual', M2), C('ingresos_esperados', M2), C('egresos_comprometidos', M2),
    C('reserva_minima', M2), C('disponible_proyectado', M2),
    FK('version_parametros_id', 'versiones_parametros', d='Parámetros de campaña utilizados (factor de cobrabilidad, reserva mínima).'),
])

# ============================================================== SS-06
add('creditos', 'SS-06', 'Credito', 'Créditos activos, originados en una liquidación o en una refinanciación aplicada.', [
    ID(),
    C('numero', 'INT', default='NEXT VALUE FOR seq_creditos', uq=True),
    FK('cliente_id', 'clientes'),
    FK('version_condiciones_id', 'versiones_condiciones', d='Condiciones conservadas (RN-15).'),
    FK('propuesta_origen_id', 'propuestas_refinanciacion', null=True, uq=True, d='Propuesta aplicada que generó el plan.'),
    FK('version_morosidad_id', 'versiones_parametros', d='Régimen de tolerancia y punitorios asignado al liquidar (RN-35).'),
    EN('origen', 'OrigenCredito'),
    C('capital', M2), C('tna', TASA), C('cantidad_cuotas', 'SMALLINT'),
    C('fecha_liquidacion', TS),
    C('tolerancia_dias', 'TINYINT', d='Tolerancia asignada (RN-35, RN-53).'),
    EN('estado', 'EstadoCredito', default="'Activo'"),
    VF(),
], ck=[('CK_creditos_origen_propuesta', "(origen = 'Liquidacion' AND propuesta_origen_id IS NULL) OR (origen = 'Refinanciacion' AND propuesta_origen_id IS NOT NULL)"),
       ('CK_creditos_valores', 'capital > 0 AND tna >= 0 AND cantidad_cuotas > 0')],
   idx=[('IX_creditos_cliente', ['cliente_id', 'estado'], None, None)])

add('revocaciones', 'SS-06', 'Revocacion', 'Ejercicio del derecho de revocación de un crédito dentro de los 10 días hábiles: comunicación, importe a restituir (neto desembolsado más el interés compensatorio devengado y su IVA, menos lo pagado) y confirmación de la restitución (RN-79, D-31).', [
    ID(),
    FK('credito_id', 'creditos', uq=True),
    C('fecha_comunicacion', D, d='Día en que el cliente comunicó la revocación.'),
    C('medio_comunicacion', 'NVARCHAR(60)'),
    C('fecha_limite', D, d='Último día hábil del plazo, desde la recepción del contrato o el desembolso, lo último.'),
    C('capital', M2, d='Importe neto desembolsado al cliente.'),
    C('dias_transcurridos', 'INT', d='Días corridos desde el desembolso hasta la comunicación.'),
    C('interes_devengado', M2),
    C('iva_interes', M2),
    C('pagado_previo', M2, d='Cobros confirmados del crédito antes de la revocación.'),
    C('importe_restituir', M2),
    C('saldo_a_favor', M2, default='0', d='Exceso de lo pagado sobre lo que correspondía restituir.'),
    EN('estado', 'EstadoRevocacion', default="'PendienteRestitucion'"),
    C('fecha_registro', TS),
    FK('usuario_id', 'usuarios_internos'),
    FK('cobro_id', 'cobros', null=True, d='Cobro de tipo Revocacion con la transferencia de restitución.'),
    C('fecha_confirmacion', TS, null=True),
    FK('usuario_confirmacion_id', 'usuarios_internos', null=True),
], ck=[('CK_revocaciones_plazo', 'fecha_comunicacion <= fecha_limite'),
       ('CK_revocaciones_importes', 'capital > 0 AND dias_transcurridos >= 0 AND interes_devengado >= 0 AND iva_interes >= 0 AND pagado_previo >= 0 AND importe_restituir >= 0 AND saldo_a_favor >= 0'),
       ('CK_revocaciones_confirmacion', "(estado = 'PendienteRestitucion' AND fecha_confirmacion IS NULL AND cobro_id IS NULL) OR (estado = 'Confirmada' AND fecha_confirmacion IS NOT NULL AND usuario_confirmacion_id IS NOT NULL AND (cobro_id IS NOT NULL OR importe_restituir = 0))")])

add('cuotas', 'SS-06', 'Cuota', 'Obligaciones del plan con sus conceptos discriminados (RN-50 a RN-52). Los saldos pendientes por concepto se actualizan en la misma transacción que las imputaciones y los punitorios.', [
    ID(True),
    FK('credito_id', 'creditos'),
    C('numero', 'SMALLINT'),
    C('vencimiento', D),
    C('capital', M2), C('interes', M2), C('gastos', M2), C('iva', M2),
    C('concepto_no_capitalizable', M2, default='0', d='Parte del concepto no capitalizable de una refinanciación (RN-70).'),
    C('capital_pendiente', M2),
    C('interes_pendiente', M2), C('gastos_pendiente', M2), C('iva_pendiente', M2),
    C('no_capitalizable_pendiente', M2, default='0'),
    C('punitorio_pendiente', M4, default='0', d='Punitorios devengados y no cancelados ni condonados; se redondea a dos decimales al exigirse.'),
    C('iva_punitorio_pendiente', M4, default='0'),
    EN('estado', 'EstadoCuota', default="'Pendiente'"),
    VF(),
], uq=[('UQ_cuotas_numero', ['credito_id', 'numero'], None)],
   ck=[('CK_cuotas_no_negativos', 'capital >= 0 AND interes >= 0 AND gastos >= 0 AND iva >= 0 AND concepto_no_capitalizable >= 0'),
       ('CK_cuotas_pendientes', 'capital_pendiente BETWEEN 0 AND capital AND interes_pendiente BETWEEN 0 AND interes AND gastos_pendiente BETWEEN 0 AND gastos AND iva_pendiente BETWEEN 0 AND iva AND no_capitalizable_pendiente BETWEEN 0 AND concepto_no_capitalizable AND punitorio_pendiente >= 0 AND iva_punitorio_pendiente >= 0')],
   idx=[('IX_cuotas_vencimiento', ['estado', 'vencimiento'], ['credito_id'], "estado IN ('Pendiente', 'Vencida')"),
        ('IX_cuotas_credito_estado', ['credito_id', 'estado'], None, None)])

add('tramos_punitorios', 'SS-06', 'TramoPunitorio', 'Interés punitorio de un día sobre el capital vencido de una cuota. La clave única (cuota, fecha) hace idempotente el proceso diario (RN-34, RN-35, RNF-10).', [
    ID(True),
    FK('cuota_id', 'cuotas', big=True),
    C('fecha', D, d='Día de atraso al que corresponde.'),
    C('tasa_diaria', TASA, d='Menor entre la tasa configurada y el tope (RN-34).'),
    C('capital_base', M4),
    C('importe', M4),
    C('iva', M4),
    C('exigible', B, default='0', d='Pasa a 1 cuando el atraso supera la tolerancia (RN-53).'),
    FK('version_parametros_id', 'versiones_parametros'),
], uq=[('UQ_tramos_punitorios_dia', ['cuota_id', 'fecha'], None)],
   ck=[('CK_tramos_importes', 'capital_base > 0 AND importe >= 0 AND iva >= 0 AND tasa_diaria >= 0')])

add('cancelaciones_anticipadas', 'SS-06', 'CancelacionAnticipada', 'Cancelación anticipada total de un crédito: componentes del importe cobrado y reducción de los intereses y gastos no devengados, para el comprobante (RN-67, CU-97, D-38).', [
    ID(),
    FK('credito_id', 'creditos', uq=True),
    FK('cobro_id', 'cobros', big=True, uq=True),
    C('fecha_pago', D, d='Día local de la cancelación.'),
    C('vencidas', M2, d='Total actualizado de las cuotas vencidas, con punitorios exigibles.'),
    C('capital_no_vencido', M2),
    C('no_capitalizable_no_vencido', M2, default='0', d='Concepto no capitalizable de las cuotas no vencidas (créditos refinanciados).'),
    C('interes_en_curso', M2, d='Interés proporcional de la cuota en curso.'),
    C('iva_interes_en_curso', M2),
    C('dias_en_curso', 'SMALLINT'),
    C('cuota_en_curso', 'SMALLINT', null=True),
    C('interes_no_devengado', M2, d='Interés de las cuotas no vencidas que no se cobra.'),
    C('gastos_no_devengados', M2),
    C('iva_no_devengado', M2),
    C('total', M2),
], ck=[('CK_cancelaciones_importes', 'total > 0 AND vencidas >= 0 AND capital_no_vencido >= 0 AND no_capitalizable_no_vencido >= 0 AND interes_en_curso >= 0 AND iva_interes_en_curso >= 0 AND dias_en_curso >= 0 AND interes_no_devengado >= 0 AND gastos_no_devengados >= 0 AND iva_no_devengado >= 0')])

add('condonaciones', 'SS-06', 'Condonacion', 'Reducciones autorizadas de punitorios pendientes (RN-38).', [
    ID(),
    C('fecha', TS, default='SYSDATETIMEOFFSET()'),
    C('importe', M2),
    C('fundamento', 'NVARCHAR(1000)'),
    FK('usuario_id', 'usuarios_internos', d='Gerente que condonó.'),
    FK('cliente_id', 'clientes', d='Cliente titular de las cuotas alcanzadas (D-36).'),
], ck=[('CK_condonaciones_importe', 'importe > 0')])

add('condonaciones_cuotas', 'SS-06', '(asociación Condonación–Cuota)', 'Distribución del importe condonado entre las cuotas alcanzadas.', [
    FK('condonacion_id', 'condonaciones'),
    FK('cuota_id', 'cuotas', big=True),
    C('importe', M4, d='Punitorio condonado de la cuota, sin IVA.'),
    C('iva', M4, default='0', d='IVA del punitorio condonado, proporcional a la parte condonada (D-36).'),
], pk=['condonacion_id', 'cuota_id'], ck=[('CK_condonaciones_cuotas_importe', 'importe > 0'), ('CK_condonaciones_cuotas_iva', 'iva >= 0')])

add('simulaciones_refinanciacion', 'SS-06', 'SimulacionRefinanciacion', 'Cálculos preliminares de un nuevo plan; no modifican la deuda (RN-36).', [
    ID(),
    FK('cliente_id', 'clientes'),
    C('fecha', TS, default='SYSDATETIMEOFFSET()'),
    C('deuda_consolidada', M2), C('cantidad_cuotas', 'SMALLINT'),
    C('tasa', TASA), C('valor_cuota', M2),
    C('vence', TS),
    FK('usuario_id', 'usuarios_internos'),
    C('fecha_corte', D, d='Día al que se compuso la deuda (D-36).'),
    C('capital', M2, d='Capital pendiente de las cuotas comprendidas (RN-70).'),
    C('no_capitalizable', M2, d='Concepto no capitalizable a la fecha de corte (RN-70).'),
    FK('version_parametros_id', 'versiones_parametros', d='Límites de refinanciación aplicados (CU-60).'),
])

add('simulaciones_refinanciacion_cuotas', 'SS-06', '(asociación Simulación–Cuota)', 'Cuotas comprendidas en una simulación y su composición, para verificar al registrar la propuesta que la deuda no varió (CU-40 A2, D-36).', [
    FK('simulacion_id', 'simulaciones_refinanciacion'),
    FK('cuota_id', 'cuotas', big=True),
    C('capital_pendiente', M2),
    C('no_capitalizable', M2, d='Intereses vencidos, gastos, IVA, concepto no capitalizable anterior y punitorios exigibles a la fecha de corte.'),
    C('punitorio', M2, d='Punitorios exigibles con su IVA incluidos en no_capitalizable.'),
], pk=['simulacion_id', 'cuota_id'])

add('propuestas_refinanciacion', 'SS-06', 'PropuestaRefinanciacion', 'Planes presentados para resolución gerencial y aceptación del cliente. Cada corrección es una nueva versión vinculada con la anterior (RN-36).', [
    ID(),
    C('numero', 'INT', d='Número de la propuesta, compartido por sus versiones.'),
    C('version', 'SMALLINT', default='1', d='Versión de la propuesta.'),
    FK('cliente_id', 'clientes'),
    FK('propuesta_anterior_id', 'propuestas_refinanciacion', null=True, uq=True),
    FK('simulacion_id', 'simulaciones_refinanciacion', null=True),
    FK('version_parametros_id', 'versiones_parametros', d='Límites de refinanciación aplicados (CU-60).'),
    C('fecha_registro', TS, default='SYSDATETIMEOFFSET()'),
    C('vence', TS, d='Registro + 7 días corridos; la aprobación no lo extiende.'),
    C('fecha_corte', D, d='Fecha a la que se fija la deuda (RN-70).'),
    C('capital_refinanciado', M2),
    C('concepto_no_capitalizable', M2),
    C('cantidad_cuotas', 'SMALLINT'),
    C('tasa', TASA),
    EN('estado', 'EstadoPropuesta', default="'PendienteResolucion'"),
    C('motivo_cancelacion', 'NVARCHAR(500)', null=True),
    FK('usuario_id', 'usuarios_internos', d='Analista que la registró.'),
    VF(),
    EN('tipo_cancelacion', 'TipoCancelacionPropuesta', null=True, d='Desistimiento del cliente (Analista) o causa administrativa (Gerente), CU-96.'),
    C('fecha_cancelacion', TS, null=True),
    FK('usuario_cancelacion_id', 'usuarios_internos', null=True),
    C('fecha_desistimiento', D, null=True, d='Fecha de la constancia de desistimiento del cliente.'),
    C('medio_desistimiento', 'NVARCHAR(100)', null=True, d='Medio por el que el cliente desistió.'),
], uq=[('UQ_propuestas_numero_version', ['numero', 'version'], None)],
   ck=[('CK_propuestas_valores', 'capital_refinanciado > 0 AND concepto_no_capitalizable >= 0 AND cantidad_cuotas > 0 AND tasa >= 0'),
       ('CK_propuestas_cancelacion', "estado <> 'Cancelada' OR motivo_cancelacion IS NOT NULL"),
       ('CK_propuestas_datos_cancelacion', "estado <> 'Cancelada' OR (tipo_cancelacion IS NOT NULL AND fecha_cancelacion IS NOT NULL AND usuario_cancelacion_id IS NOT NULL AND (tipo_cancelacion = 'Administrativa' OR (fecha_desistimiento IS NOT NULL AND medio_desistimiento IS NOT NULL)))")],
   idx=[('IX_propuestas_cliente', ['cliente_id', 'estado'], None, None),
        ('IX_propuestas_vence', ['vence'], None, "estado IN ('PendienteResolucion', 'AprobadaPendienteAceptacion')")])

add('propuestas_cuotas', 'SS-06', '(asociación Propuesta–Cuota)', 'Cuotas comprendidas y composición de la deuda a la fecha de corte, para revalidarla antes de resolver y aplicar (RN-36).', [
    FK('propuesta_id', 'propuestas_refinanciacion'),
    FK('cuota_id', 'cuotas', big=True),
    C('capital_pendiente', M2),
    C('no_capitalizable', M2, d='Intereses vencidos, gastos, IVA y punitorios exigibles a la fecha de corte.'),
    C('punitorio', M2, default='0', d='Punitorios exigibles con su IVA incluidos en no_capitalizable; los posteriores se incorporan al aplicar (RN-70, D-36).'),
], pk=['propuesta_id', 'cuota_id'])

add('resoluciones_propuesta', 'SS-06', 'ResolucionPropuesta', 'Decisiones gerenciales sobre una propuesta (CU-41).', [
    ID(),
    FK('propuesta_id', 'propuestas_refinanciacion'),
    EN('decision', 'DecisionPropuesta'),
    C('fundamento', 'NVARCHAR(1000)'),
    C('fecha', TS, default='SYSDATETIMEOFFSET()'),
    FK('usuario_id', 'usuarios_internos', d='Gerente.'),
])

add('aceptaciones_refinanciacion', 'SS-06', 'AceptacionRefinanciacion', 'Constancia de aceptación del cliente que permite aplicar la propuesta (RN-37).', [
    ID(),
    FK('propuesta_id', 'propuestas_refinanciacion', uq=True),
    C('fecha', TS, default='SYSDATETIMEOFFSET()'),
    C('medio', 'NVARCHAR(100)'),
    C('observaciones', 'NVARCHAR(500)', null=True),
    FK('usuario_id', 'usuarios_internos', d='Analista o Cajero autorizado.'),
    C('fecha_constancia', D, d='Fecha en que el cliente manifestó su aceptación.'),
    C('punitorios_incorporados', M2, default='0', d='Punitorios devengados desde la fecha de corte incorporados al concepto no capitalizable (RN-70).'),
])

# ============================================================== SS-08
add('campanas', 'SS-08', 'Campaña', 'Campañas crediticias; como máximo una activa (RN-66), garantizado por un índice único filtrado.', [
    ID(),
    C('numero', 'INT', default='NEXT VALUE FOR seq_campanas', uq=True),
    FK('proyeccion_fondos_id', 'proyecciones_fondos', d='Proyección que la activó.'),
    FK('version_condiciones_id', 'versiones_condiciones', d='Condiciones de la línea ofrecida.'),
    FK('version_parametros_id', 'versiones_parametros', d='Parámetros de campaña aplicados.'),
    C('activacion', TS, default='SYSDATETIMEOFFSET()'),
    C('vence', TS, d='Fin de la vigencia configurada.'),
    C('fecha_cierre', TS, null=True),
    EN('causa_cierre', 'CausaCierre', null=True),
    C('capital_asignado', M2),
    C('capital_comprometido', M2, default='0'),
    EN('estado', 'EstadoCampana', default="'Activa'"),
    VF(),
], uq=[('UQ_campanas_activa', ['estado'], "estado = 'Activa'")],
   ck=[('CK_campanas_capital', 'capital_asignado > 0 AND capital_comprometido BETWEEN 0 AND capital_asignado'),
       ('CK_campanas_cierre', "(estado = 'Activa' AND fecha_cierre IS NULL) OR (estado = 'Finalizada' AND fecha_cierre IS NOT NULL AND causa_cierre IS NOT NULL)")])

add('ofertas', 'SS-08', 'Oferta', 'Propuestas de financiación dirigidas a un cliente dentro de una campaña (RN-42).', [
    ID(),
    FK('campana_id', 'campanas'),
    FK('cliente_id', 'clientes'),
    C('importe_maximo', M2), C('cantidad_cuotas', 'SMALLINT'),
    C('tna', TASA), C('tea', TASA), C('cftea', TASA),
    C('vence', TS, d='No supera el cierre de la campaña.'),
    EN('estado', 'EstadoOferta', default="'Vigente'"),
    C('fecha_respuesta', TS, null=True),
    FK('solicitud_id', 'solicitudes_credito', null=True, d='Solicitud originada por la aceptación; como máximo una (RN-42).'),
    VF(),
], uq=[('UQ_ofertas_cliente', ['campana_id', 'cliente_id'], None),
       ('UQ_ofertas_solicitud', ['solicitud_id'], 'solicitud_id IS NOT NULL')],
   ck=[('CK_ofertas_respuesta', "estado NOT IN ('Aceptada', 'Rechazada') OR fecha_respuesta IS NOT NULL"),
       ('CK_ofertas_aceptada', "estado <> 'Aceptada' OR solicitud_id IS NOT NULL")],
   idx=[('IX_ofertas_cliente', ['cliente_id', 'estado'], None, None)])

# ============================================================== SS-11
add('copias_seguridad', 'SS-11', 'CopiaSeguridad', 'Respaldos de la base de datos, cifrados y con código de verificación (RNF-13).', [
    ID(),
    C('archivo', 'VARCHAR(260)', uq=True, d='Nombre del archivo en el volumen de copias.'),
    C('fecha_generacion', TS, default='SYSDATETIMEOFFSET()'),
    C('tamano_bytes', 'BIGINT', null=True),
    EN('origen', 'OrigenCopia'),
    C('hash_verificacion', 'CHAR(64)', null=True, d='SHA-256 del archivo.'),
    EN('estado', 'EstadoCopia', default="'EnCurso'"),
    FK('usuario_id', 'usuarios_internos', null=True, d='Administrador; nulo en la copia automática.'),
])

add('restauraciones', 'SS-11', 'Restauracion', 'Restauraciones de una copia. Se registran al finalizar, en la base ya restaurada, y además en el registro técnico del servidor (RN-45).', [
    ID(),
    FK('copia_seguridad_id', 'copias_seguridad'),
    C('fecha', TS, default='SYSDATETIMEOFFSET()'),
    C('resultado', 'NVARCHAR(300)'),
    FK('usuario_id', 'usuarios_internos'),
])

SEQUENCES = {'seq_solicitudes': 'INT', 'seq_cobros': 'BIGINT', 'seq_comprobantes': 'BIGINT', 'seq_ordenes': 'BIGINT', 'seq_creditos': 'INT', 'seq_campanas': 'INT', 'seq_propuestas': 'INT'}
