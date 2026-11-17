"""Genera los planes de JMeter de las pruebas de rendimiento (RNF-03, RNF-06 y RNF-07).

    python3 tests/rendimiento/generar_planes.py

Escribe carga.jmx (RP-01 a RP-05: carga mixta de 10 usuarios) y concurrencia.jmx (RP-06). Los planes se generan desde
este script para revisarlos como código; también se pueden abrir y modificar con la interfaz de JMeter.
"""
from pathlib import Path
from xml.sax.saxutils import escape

DIR = Path(__file__).parent
# Token de los formularios: el campo oculto o, en las páginas sin formulario, la etiqueta meta del panel
CSRF = '(?:name="csrf_sigccr" value|name="csrf-valor" content)="([^"]+)"'
ASSERT_SUBSTRING = 16


def e(texto: str) -> str:
    return escape(texto, {'"': '&quot;'})


def nodo(xml: str, hijos: list[str] | None = None) -> str:
    """Elemento de JMeter seguido de su hashTree de hijos."""
    return xml + '<hashTree>' + ''.join(hijos or []) + '</hashTree>'


def argumentos(pares: list[tuple[str, str]]) -> str:
    items = ''.join(
        f'<elementProp name="{e(n)}" elementType="HTTPArgument"><boolProp name="HTTPArgument.always_encode">true</boolProp>'
        f'<stringProp name="Argument.value">{e(v)}</stringProp><stringProp name="Argument.metadata">=</stringProp>'
        f'<boolProp name="HTTPArgument.use_equals">true</boolProp><stringProp name="Argument.name">{e(n)}</stringProp></elementProp>'
        for n, v in pares
    )
    return f'<elementProp name="HTTPsampler.Arguments" elementType="Arguments" guiclass="HTTPArgumentsPanel" testclass="Arguments"><collectionProp name="Arguments.arguments">{items}</collectionProp></elementProp>'


def http(nombre: str, metodo: str, ruta: str, pares: list[tuple[str, str]] | None = None, hijos: list[str] | None = None) -> str:
    return nodo(
        f'<HTTPSamplerProxy guiclass="HttpTestSampleGui" testclass="HTTPSamplerProxy" testname="{e(nombre)}">'
        + argumentos(pares or [])
        + f'<stringProp name="HTTPSampler.path">{e(ruta)}</stringProp><stringProp name="HTTPSampler.method">{metodo}</stringProp>'
        '<boolProp name="HTTPSampler.follow_redirects">true</boolProp><boolProp name="HTTPSampler.auto_redirects">false</boolProp>'
        '<boolProp name="HTTPSampler.use_keepalive">true</boolProp><boolProp name="HTTPSampler.DO_MULTIPART_POST">false</boolProp>'
        '</HTTPSamplerProxy>',
        hijos,
    )


def extraer(variable: str, regex: str, plantilla: str = '$1$') -> str:
    return nodo(
        f'<RegexExtractor guiclass="RegexExtractorGui" testclass="RegexExtractor" testname="Extraer {e(variable)}">'
        '<stringProp name="RegexExtractor.useHeaders">false</stringProp><stringProp name="Sample.scope">all</stringProp>'
        f'<stringProp name="RegexExtractor.refname">{e(variable)}</stringProp><stringProp name="RegexExtractor.regex">{e(regex)}</stringProp>'
        f'<stringProp name="RegexExtractor.template">{e(plantilla)}</stringProp><stringProp name="RegexExtractor.default">NOTFOUND</stringProp>'
        '<stringProp name="RegexExtractor.match_number">1</stringProp></RegexExtractor>'
    )


def contiene(texto: str) -> str:
    return nodo(
        f'<ResponseAssertion guiclass="AssertionGui" testclass="ResponseAssertion" testname="Contiene «{e(texto)}»">'
        f'<collectionProp name="Asserion.test_strings"><stringProp name="0">{e(texto)}</stringProp></collectionProp>'
        '<stringProp name="Assertion.custom_message"></stringProp><stringProp name="Assertion.test_field">Assertion.response_data</stringProp>'
        f'<boolProp name="Assertion.assume_success">false</boolProp><intProp name="Assertion.test_type">{ASSERT_SUBSTRING}</intProp></ResponseAssertion>'
    )


def pausa(minimo_ms: int, rango_ms: int) -> str:
    return nodo(
        '<UniformRandomTimer guiclass="UniformRandomTimerGui" testclass="UniformRandomTimer" testname="Pausa del usuario">'
        f'<stringProp name="ConstantTimer.delay">{minimo_ms}</stringProp><stringProp name="RandomTimer.range">{rango_ms}</stringProp></UniformRandomTimer>'
    )


def sincronizar(grupo: int) -> str:
    return nodo(
        '<SyncTimer guiclass="TestBeanGUI" testclass="SyncTimer" testname="Enviar al mismo tiempo">'
        f'<intProp name="groupSize">{grupo}</intProp><longProp name="timeoutInMs">120000</longProp></SyncTimer>'
    )


def una_vez(nombre: str, hijos: list[str]) -> str:
    return nodo(f'<OnceOnlyController guiclass="OnceOnlyControllerGui" testclass="OnceOnlyController" testname="{e(nombre)}"/>', hijos)


def si(nombre: str, condicion: str, hijos: list[str]) -> str:
    return nodo(
        f'<IfController guiclass="IfControllerPanel" testclass="IfController" testname="{e(nombre)}">'
        f'<stringProp name="IfController.condition">{e(condicion)}</stringProp><boolProp name="IfController.evaluateAll">false</boolProp>'
        '<boolProp name="IfController.useExpression">true</boolProp></IfController>',
        hijos,
    )


def csv(nombre: str, archivo: str, variables: str, reciclar: bool) -> str:
    return nodo(
        f'<CSVDataSet guiclass="TestBeanGUI" testclass="CSVDataSet" testname="{e(nombre)}">'
        f'<stringProp name="filename">{e(archivo)}</stringProp><stringProp name="fileEncoding">UTF-8</stringProp>'
        f'<stringProp name="variableNames">{variables}</stringProp><boolProp name="ignoreFirstLine">false</boolProp>'
        '<stringProp name="delimiter">,</stringProp><boolProp name="quotedData">false</boolProp>'
        f'<boolProp name="recycle">{"true" if reciclar else "false"}</boolProp><boolProp name="stopThread">false</boolProp>'
        '<stringProp name="shareMode">shareMode.all</stringProp></CSVDataSet>'
    )


def grupo(nombre: str, hilos: int, rampa: int, hijos: list[str], duracion: str | None = '${__P(duracion,600)}', vueltas: int = -1) -> str:
    programado = duracion is not None
    return nodo(
        f'<ThreadGroup guiclass="ThreadGroupGui" testclass="ThreadGroup" testname="{e(nombre)}">'
        '<stringProp name="ThreadGroup.on_sample_error">continue</stringProp>'
        '<elementProp name="ThreadGroup.main_controller" elementType="LoopController" guiclass="LoopControlPanel" testclass="LoopController">'
        f'<boolProp name="LoopController.continue_forever">false</boolProp><intProp name="LoopController.loops">{vueltas}</intProp></elementProp>'
        f'<stringProp name="ThreadGroup.num_threads">{hilos}</stringProp><stringProp name="ThreadGroup.ramp_time">{rampa}</stringProp>'
        f'<boolProp name="ThreadGroup.scheduler">{"true" if programado else "false"}</boolProp>'
        f'<stringProp name="ThreadGroup.duration">{e(duracion or "")}</stringProp><stringProp name="ThreadGroup.delay">0</stringProp>'
        '<boolProp name="ThreadGroup.same_user_on_next_iteration">true</boolProp></ThreadGroup>',
        [nodo('<CookieManager guiclass="CookiePanel" testclass="CookieManager" testname="Cookies de la sesión"><collectionProp name="CookieManager.cookies"/>'
              '<boolProp name="CookieManager.clearEachIteration">false</boolProp><boolProp name="CookieManager.controlledByThreadGroup">false</boolProp></CookieManager>')]
        + hijos,
    )


def inicio_sesion(correo: str) -> list[str]:
    """Inicio de sesión del panel: el token de seguridad (CSRF) se renueva al iniciar sesión."""
    return [
        http('00 Inicio de sesión (formulario)', 'GET', '/admin/login', hijos=[extraer('csrf', CSRF)]),
        http('00 Inicio de sesión', 'POST', '/admin/login', [('csrf_sigccr', '${csrf}'), ('correo', correo), ('contrasena', '${__P(clave)}')],
             [contiene('Cerrar sesión'), extraer('csrf', CSRF)]),
    ]


def deuda_y_cobro(etiqueta_deuda: str, etiqueta_cobro: str, cliente: str, operacion: str, hijos_cobro_previos: list[str] | None = None) -> list[str]:
    extraccion = [
        extraer('csrf', CSRF),
        extraer('cuota_nombre', 'name="(cuotas\\[\\d+\\]\\[\\])" value="\\d+" data-cobro-cuota'),
        extraer('cuota_numero', 'name="cuotas\\[\\d+\\]\\[\\]" value="(\\d+)" data-cobro-cuota'),
        extraer('cuota_total', 'value="\\d+" data-cobro-cuota data-total="([\\d.]+)"'),
    ]
    cobro = http(etiqueta_cobro, 'POST', '/admin/cobros', [
        ('csrf_sigccr', '${csrf}'), ('cliente_id', cliente), ('${cuota_nombre}', '${cuota_numero}'), ('importe', '${cuota_total}'),
        ('medios[0][medio]', 'Transferencia'), ('medios[0][importe]', '${cuota_total}'), ('medios[0][numero_operacion]', operacion),
    ], (hijos_cobro_previos or []) + [extraer('cobro_id', 'admin/cobros/(\\d+)/')])
    return [
        http(etiqueta_deuda, 'GET', '/admin/cobros', [('cliente', cliente)], extraccion),
        si('Si el cliente tiene cuotas a cobrar', '${__jexl3("${cuota_nombre}" != "NOTFOUND")}', [cobro]),
    ]


def plan(nombre: str, grupos: list[str], serializar: bool) -> str:
    valores = nodo(
        '<ConfigTestElement guiclass="HttpDefaultsGui" testclass="ConfigTestElement" testname="Servidor del SIG-CCR">'
        '<elementProp name="HTTPsampler.Arguments" elementType="Arguments"><collectionProp name="Arguments.arguments"/></elementProp>'
        '<stringProp name="HTTPSampler.domain">${__P(host,localhost)}</stringProp><stringProp name="HTTPSampler.port">${__P(puerto,18443)}</stringProp>'
        '<stringProp name="HTTPSampler.protocol">https</stringProp><stringProp name="HTTPSampler.contentEncoding">UTF-8</stringProp>'
        '<stringProp name="HTTPSampler.implementation">HttpClient4</stringProp><stringProp name="HTTPSampler.connect_timeout">10000</stringProp>'
        '<stringProp name="HTTPSampler.response_timeout">120000</stringProp></ConfigTestElement>'
    )
    cuerpo = nodo(
        f'<TestPlan guiclass="TestPlanGui" testclass="TestPlan" testname="{e(nombre)}">'
        '<boolProp name="TestPlan.functional_mode">false</boolProp>'
        f'<boolProp name="TestPlan.serialize_threadgroups">{"true" if serializar else "false"}</boolProp>'
        '<elementProp name="TestPlan.user_defined_variables" elementType="Arguments"><collectionProp name="Arguments.arguments"/></elementProp></TestPlan>',
        [valores] + grupos,
    )
    return '<?xml version="1.0" encoding="UTF-8"?>\n<jmeterTestPlan version="1.2" properties="5.0" jmeter="5.6.3"><hashTree>' + cuerpo + '</hashTree></jmeterTestPlan>\n'


DOMINIO = 'rendimiento.local'

carga = plan('SIG-CCR: carga mixta de 10 usuarios (RP-01 a RP-05)', [
    # Tres Analistas buscan clientes por apellido, documento y CUIL, y consultan el listado de solicitudes
    grupo('Analistas de Crédito', 3, 30, [
        una_vez('Inicio de sesión del Analista', inicio_sesion('rend.analista${__threadNum}@' + DOMINIO)),
        csv('Clientes a buscar', '${__P(datos,.)}/clientes.csv', 'cliente_id,documento,cuil,apellido', True),
        http('RP-01 Búsqueda de clientes por apellido', 'GET', '/admin/clientes', [('texto', '${apellido}')], [pausa(2000, 3000), contiene('${apellido}')]),
        http('RP-01 Búsqueda de clientes por documento', 'GET', '/admin/clientes', [('documento', '${documento}')], [contiene('${documento}')]),
        http('RP-01 Búsqueda de clientes por CUIL', 'GET', '/admin/clientes', [('cuit', '${cuil}')], [contiene('${apellido}')]),
        http('RP-05 Listado de solicitudes', 'GET', '/admin/solicitudes', [('estado', 'Liquidada')], [pausa(1000, 2000), contiene('Liquidada')]),
    ]),
    # Seis Cajeros buscan al cliente en el cobro en ventanilla y registran el cobro de su cuota más antigua
    grupo('Cajeros', 6, 60, [
        una_vez('Inicio de sesión y apertura de la caja', inicio_sesion('rend.cajero${__threadNum}@' + DOMINIO) + [
            http('00 Apertura de la caja', 'POST', '/admin/caja', [('csrf_sigccr', '${csrf}')]),
        ]),
        csv('Clientes a cobrar', '${__P(datos,.)}/cobros.csv', 'cliente_id,documento,cuil,apellido', False),
        http('RP-02 Búsqueda del cliente en el cobro', 'GET', '/admin/cobros', [('texto', '${apellido}')], [pausa(3000, 4000), contiene('Cobro en ventanilla')]),
        *deuda_y_cobro('RP-03 Deuda del cliente', 'RP-03 Registro del cobro', '${cliente_id}', 'REND-${__threadNum}-${__counter(FALSE,)}', [contiene('registrado por')]),
        http('RP-05 Comprobantes del día', 'GET', '/admin/cobros/comprobantes', [('fecha', '${__time(dd/MM/yyyy,)}')], [pausa(1000, 2000)]),
    ]),
    # Un Gerente consulta el tablero y los reportes de cartera y morosidad, que recorren todo el volumen
    grupo('Gerente', 1, 10, [
        una_vez('Inicio de sesión del Gerente', inicio_sesion('rend.gerente1@' + DOMINIO)),
        http('RP-05 Tablero gerencial', 'GET', '/admin/tablero', hijos=[pausa(5000, 5000), contiene('Cartera crediticia')]),
        http('RP-05 Reporte de cartera', 'GET', '/admin/reportes/cartera', [('generar', '1')], [contiene('Saldo deudor total')]),
        http('RP-05 Reporte de morosidad', 'GET', '/admin/reportes/morosidad', [('generar', '1')], [contiene('Índice de morosidad')]),
    ]),
], serializar=False)

concurrencia = plan('SIG-CCR: decisiones simultáneas sobre un mismo objeto (RP-06, RNF-06)', [
    # Dos Cajeros cobran la misma cuota del mismo cliente al mismo tiempo: debe prosperar un solo cobro
    grupo('RP-06a Cobro simultáneo de la misma cuota', 2, 0, [
        *inicio_sesion('rend.cajero${__threadNum}@' + DOMINIO),
        *deuda_y_cobro('06 Deuda del cliente', 'RP-06a Cobro simultáneo', '${__P(cliente_concurrencia)}', 'CONC-${__threadNum}', [sincronizar(2)]),
    ], duracion=None, vueltas=1),
    # Dos Gerentes anulan el mismo cobro al mismo tiempo: debe registrarse una sola anulación
    grupo('RP-06b Anulación simultánea del mismo cobro', 2, 0, [
        *inicio_sesion('rend.gerente${__threadNum}@' + DOMINIO),
        http('06 Anulación (formulario)', 'GET', '/admin/cobros/${__P(cobro_concurrencia)}/anular', hijos=[extraer('csrf', CSRF)]),
        http('RP-06b Anulación simultánea', 'POST', '/admin/cobros/${__P(cobro_concurrencia)}/anular',
             [('csrf_sigccr', '${csrf}'), ('motivo', 'Prueba de concurrencia de RNF-06')], [sincronizar(2)]),
    ], duracion=None, vueltas=1),
], serializar=True)

(DIR / 'carga.jmx').write_text(carga, encoding='utf-8')
(DIR / 'concurrencia.jmx').write_text(concurrencia, encoding='utf-8')
print('Planes generados: carga.jmx y concurrencia.jmx')
