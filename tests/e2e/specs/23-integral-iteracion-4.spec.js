// Prueba funcional integral, parte 4 (iteración 4) con los clientes del Anexo A: R-153 en adelante de
// docs/pruebas/prueba_funcional_integral_1_a_4.md. Sigue a los mismos clientes y créditos de las partes 1 a 3 por el
// portal, el pre-registro, las campañas, los reportes y la auditoría, y termina con una copia y su restauración, que
// reemplaza la base: por eso es el último archivo de la batería (después de 21-copias).
const crypto = require('node:crypto');
const { test, expect, olvidarSesion } = require('../lib/fixtures');
const { ultimoCorreo, consulta, valor, spark, cerrarBase } = require('../lib/sistema');
const { documento } = require('../lib/archivos');
const { elegirDomicilio } = require('../lib/clientes');
const { clientes, portal, fecha, usuarios } = require('../lib/datos');
const { activarPortal, entrarPortal, comoCliente, anchoDocumento } = require('../lib/portal');
const { registrarParametrosCampanas } = require('../lib/campanas');

const { C01: ana, C02: bruno, C03: carla, C10: julian } = clientes;
const CORREO_ANA = 'ana.gomez2@prueba.local'; // R-024
const CORREO_CARLA_NUEVO = 'carla.sosa2@prueba.local';
const CLAVES = { carla: 'Portal-Carla-2026-OK', julian: 'Portal-Julian-2026-OK' };
// Persona del pre-registro (A.19): el simulador del padrón conoce su denominación (DÍAZ MARTINA)
const martina = { nombre: 'Martina', apellido: 'Díaz', dni: '39444552', cuil: '27394445520', correo: 'martina.diaz@prueba.local', telefono: '3758 43-2001' };
const AJENO = 'No encontramos la información solicitada entre sus operaciones.';

const exito = (page) => page.locator('.alert-success[role="status"]');
const alerta = (page) => page.locator('.alert-danger').first();
const error = (page, campo) => page.locator(`#error-${campo}`);
const idCliente = (cuil) => valor('SELECT id FROM dbo.clientes WHERE cuit_cuil = @c', { c: cuil });
/** «$ 1.234,56» a número. */
const aNumero = (texto) => Number(texto.replace(/[^\d,-]/g, '').replace(',', '.'));
/** Formato de la pantalla para buscar un importe en el texto (1234.5 → «1.234,50»). */
const formato = (n) => Number(n).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const accesosAjenos = () => valor("SELECT COUNT(*) FROM dbo.registros_auditoria WHERE accion = 'portal.acceso-denegado'");
const creditoVigente = async (c) => (await consulta(
  "SELECT TOP 1 k.id, k.estado FROM dbo.creditos k WHERE k.cliente_id = @c AND k.estado IN ('Activo', 'EnMora') ORDER BY k.id",
  { c: await idCliente(c.cuil) },
))[0];
const accionesAuditadas = async () => (await consulta('SELECT DISTINCT accion FROM dbo.registros_auditoria')).map((f) => f.accion);

/** Simulador de la pasarela: aprueba la orden y envía la notificación (como 13-pagos-saldos). */
async function pagarEnPasarela(page, ordenId) {
  const url = await valor('SELECT url_pago FROM dbo.ordenes_pago_electronico WHERE id = @id', { id: ordenId });
  await page.goto(url.replace(/^https?:\/\/[^/]+/, ''));
  await page.locator('#estado').selectOption('approved');
  await page.locator('#notificar').setChecked(true);
  await page.getByRole('button', { name: 'Simular el pago' }).click();
  await page.waitForLoadState();
  spark('pagos:notificaciones');
}

/** CU-51: registra una versión de los parámetros de campañas sobre la línea Personal (A.4). */
const registrarParametros = (page, cambios = {}) => registrarParametrosCampanas(page, {
  umbral_disponibilidad: '0', periodo_proyeccion_dias: '30', factor_cobrabilidad: '90', reserva_minima: '0', linea: 'Personal (PERSONAL)',
  capital_maximo: '3000000', cantidad_maxima_ofertas: '200', categorias: ['RelacionDependencia', 'JubiladoPensionado'], antiguedad_minima_meses: '0',
  morosidad_maxima_dias: '30', admite_obligaciones_vigentes: true, mensaje: 'Oferta especial de préstamos personales', duracion_dias: '15',
  cierre_por_disponibilidad: true, motivo: 'Campaña de la prueba integral',
}, cambios);

/** Movimiento de fondos (CU-85) del Gerente. */
async function registrarAporte(page, importe, concepto) {
  await page.goto('/admin/fondos');
  await page.locator('#tipo').selectOption('AporteCapital');
  await page.locator('#caracter-Ingreso').check();
  await page.locator('#programado').setChecked(false);
  await page.locator('#importe').fill(importe);
  await page.locator('#fecha').fill(fecha());
  await page.locator('#concepto').fill(concepto);
  await page.getByRole('button', { name: 'Registrar movimiento' }).click();
  await expect(exito(page)).toContainText('Movimiento registrado.');
}

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);
test.beforeAll(() => spark('cache:clear'));

// ---------------------------------------------------------------------------------------------------------------
// 24. Portal de autogestión con los clientes de la prueba
// ---------------------------------------------------------------------------------------------------------------

test('R-153 Activar el portal de Carla y de Julián (CU-04)', async ({ page }) => {
  await activarPortal(page, carla.dni, carla.correo, CLAVES.carla);
  await activarPortal(page, julian.dni, julian.correo, CLAVES.julian);
  await entrarPortal(page, carla.correo, CLAVES.carla);
});

test('R-154 Funciones y perfil de Ana (CU-45)', async ({ browser }) => {
  const page = await comoCliente(browser, CORREO_ANA, portal.C01);
  for (const nombre of ['Estado de cuenta', 'Pagar una cuota', 'Comprobantes', 'Mis solicitudes', 'Mi perfil', 'Refinanciaciones y ofertas']) {
    await expect(page.getByRole('link', { name: nombre }).first(), nombre).toBeVisible();
  }
  await page.goto('/portal/perfil');
  await expect(page.locator('main')).toContainText('27-30111233-0');
  await expect(page.locator('main')).toContainText(CORREO_ANA);
  await page.context().close();
});

test('R-155 Consentimiento de comunicaciones comerciales (CU-98)', async ({ browser }) => {
  const page = await comoCliente(browser, CORREO_ANA, portal.C01);
  const cuenta = async () => valor("SELECT COUNT(*) FROM dbo.consentimientos WHERE cliente_id = @id AND medio = 'Portal'", { id: await idCliente(ana.cuil) });
  const antes = await cuenta();
  await page.goto('/portal/perfil/preferencias');
  // Ana puede tener el consentimiento otorgado o revocado según la prueba anterior: se cambia y se vuelve al estado inicial
  const revocar = page.getByRole('button', { name: 'Revocar el consentimiento' });
  const otorgar = page.getByRole('button', { name: 'Otorgar el consentimiento' });
  const [primero, segundo, textoPrimero, textoSegundo] = (await revocar.count()) > 0
    ? [revocar, otorgar, 'Consentimiento revocado', 'Consentimiento otorgado']
    : [otorgar, revocar, 'Consentimiento otorgado', 'Consentimiento revocado'];
  await primero.click();
  await expect(exito(page)).toContainText(textoPrimero);
  await segundo.click();
  await expect(exito(page)).toContainText(textoSegundo);
  expect(await cuenta()).toBe(antes + 2);
  await page.context().close();
});

test('R-156 Cambio de correo de Carla por autogestión (CU-75)', async ({ browser, page }) => {
  const portalCarla = await comoCliente(browser, carla.correo, CLAVES.carla);
  for (const [correo, texto] of [['', 'Ingrese el nuevo correo electrónico.'], [carla.correo, 'El nuevo correo es igual al actual.'], [bruno.correo, 'Ese correo ya está registrado para otro cliente']]) {
    await portalCarla.goto('/portal/perfil/correo');
    await portalCarla.locator('#correo_nuevo').fill(correo);
    await portalCarla.getByRole('button', { name: 'Enviar la confirmación' }).click();
    await expect(error(portalCarla, 'correo_nuevo'), correo || '(vacío)').toContainText(texto);
  }
  await portalCarla.goto('/portal/perfil/correo');
  await portalCarla.locator('#correo_nuevo').fill(CORREO_CARLA_NUEVO);
  await portalCarla.getByRole('button', { name: 'Enviar la confirmación' }).click();
  await expect(exito(portalCarla)).toContainText('Le enviamos un enlace a su correo actual');
  // Se confirma sin sesión, desde el correo actual, y después se verifica el correo nuevo
  await page.goto(ultimoCorreo(carla.correo).enlace);
  await page.getByRole('button', { name: 'Confirmar el cambio' }).click();
  await expect(page.locator('main')).toContainText(`Cambio confirmado. Enviamos un enlace a ${CORREO_CARLA_NUEVO}`);
  await page.goto(ultimoCorreo(CORREO_CARLA_NUEVO).enlace);
  await page.getByRole('button', { name: 'Confirmar correo' }).click();
  await expect(page.locator('main')).toContainText('Su correo quedó verificado');
  // El correo anterior deja de valer y el nuevo ingresa con la misma contraseña (RN-58)
  await page.goto('/portal/login');
  await page.locator('#correo').fill(carla.correo);
  await page.locator('#contrasena').fill(CLAVES.carla);
  await page.getByRole('button', { name: 'Iniciar sesión' }).click();
  await expect(page).toHaveURL(/\/portal\/login/);
  await entrarPortal(page, CORREO_CARLA_NUEVO, CLAVES.carla);
  await portalCarla.context().close();
});

test('R-157 Estado de cuenta de Ana con su deuda y sus punitorios (CU-47)', async ({ browser }) => {
  const page = await comoCliente(browser, CORREO_ANA, portal.C01);
  const k = await creditoVigente(ana);
  expect(k, 'Ana debe tener un crédito vigente (crédito A)').toBeTruthy();
  const base = await valor(
    "SELECT COALESCE(SUM(capital_pendiente + interes_pendiente + gastos_pendiente + iva_pendiente + no_capitalizable_pendiente), 0) FROM dbo.cuotas WHERE credito_id = @k AND estado <> 'Pagada'",
    { k: k.id },
  );
  await page.goto('/portal/estado-cuenta');
  await expect(page.locator('main')).toContainText(k.estado === 'EnMora' ? 'En mora' : 'Al día');
  const total = aNumero(await page.locator('dt', { hasText: 'Saldo deudor total' }).locator('xpath=following-sibling::dd[1]').innerText());
  // La pantalla suma además los punitorios calculados hasta hoy (RN-57)
  expect(total).toBeGreaterThanOrEqual(Number(base) - 0.01);
  await page.goto(`/portal/estado-cuenta/creditos/${k.id}`);
  await expect(page.locator('main')).toContainText('Cuota');
  await page.context().close();
});

test('R-158 Cuota vencida con punitorios o dentro de la tolerancia (CU-47)', async ({ browser }) => {
  const page = await comoCliente(browser, CORREO_ANA, portal.C01);
  const k = await creditoVigente(ana);
  const [q] = await consulta(
    "SELECT TOP 1 numero, DATEDIFF(day, vencimiento, CAST(SYSDATETIMEOFFSET() AT TIME ZONE 'Argentina Standard Time' AS date)) AS atraso, punitorio_pendiente FROM dbo.cuotas WHERE credito_id = @k AND estado = 'Vencida' ORDER BY numero",
    { k: k.id },
  );
  test.skip(!q, 'El crédito de Ana no tiene cuotas vencidas en este momento.');
  await page.goto(`/portal/estado-cuenta/creditos/${k.id}/cuotas/${q.numero}`);
  await expect(page.locator('main')).toContainText(`Vencida hace ${q.atraso} día(s)`);
  await expect(page.locator('main')).toContainText(Number(q.punitorio_pendiente) > 0 ? 'superada la tolerancia' : 'dentro de la tolerancia');
  await page.context().close();
});

test('R-159 Saldo a favor de Carla igual al del sistema (RN-57)', async ({ browser }) => {
  const page = await comoCliente(browser, CORREO_CARLA_NUEVO, CLAVES.carla);
  const [s] = await consulta('SELECT COALESCE(SUM(importe_disponible), 0) AS disponible FROM dbo.saldos_a_favor WHERE cliente_id = @c', { c: await idCliente(carla.cuil) });
  await page.goto('/portal/estado-cuenta');
  if (Number(s.disponible) > 0) await expect(page.locator('main')).toContainText(formato(s.disponible));
  else await expect(page.locator('main')).toContainText('No tiene saldo a favor');
  // La consulta no aplica ni devuelve nada
  expect(Number((await consulta('SELECT COALESCE(SUM(importe_disponible), 0) AS d FROM dbo.saldos_a_favor WHERE cliente_id = @c', { c: await idCliente(carla.cuil) }))[0].d)).toBe(Number(s.disponible));
  await page.context().close();
});

test('R-160 Julián sin deuda: su crédito se canceló en la prueba (CU-47)', async ({ browser }) => {
  const page = await comoCliente(browser, julian.correo, CLAVES.julian);
  await page.goto('/portal/estado-cuenta');
  await expect(page.locator('main')).toContainText('No tiene deuda pendiente.');
  await expect(page.locator('main')).toContainText('Cancelado');
  await page.context().close();
});

test('R-161 Acceso a créditos y comprobantes ajenos (RN-45)', async ({ browser }) => {
  const page = await comoCliente(browser, julian.correo, CLAVES.julian);
  const k = await creditoVigente(ana);
  const cobroAjeno = await valor('SELECT TOP 1 b.id FROM dbo.cobros b WHERE b.cliente_id = @c ORDER BY b.id', { c: await idCliente(ana.cuil) });
  const antes = await accesosAjenos();
  await page.goto(`/portal/estado-cuenta/creditos/${k.id}`);
  await expect(page).toHaveURL(/\/portal\/estado-cuenta$/);
  await expect(alerta(page)).toContainText(AJENO);
  await page.goto(`/portal/comprobantes/${cobroAjeno}`);
  await expect(page).toHaveURL(/\/portal\/comprobantes$/);
  await expect(alerta(page)).toContainText(AJENO);
  expect(await accesosAjenos()).toBe(antes + 2);
  await page.context().close();
});

let ordenId;

test('R-162 Pago electrónico de Ana desde el portal (CU-49)', async ({ browser }) => {
  const page = await comoCliente(browser, CORREO_ANA, portal.C01);
  const k = await creditoVigente(ana);
  const cliente = await idCliente(ana.cuil);
  const ordenes = () => valor('SELECT COUNT(*) FROM dbo.ordenes_pago_electronico WHERE cliente_id = @c', { c: cliente });
  const [primera, segunda] = (await consulta("SELECT numero FROM dbo.cuotas WHERE credito_id = @k AND estado <> 'Pagada' ORDER BY numero", { k: k.id })).map((f) => f.numero);
  expect(primera, 'Ana debe tener cuotas pendientes').toBeTruthy();
  const antes = await ordenes();
  // La selección debe empezar por la cuota más antigua (RN-29)
  if (segunda) {
    await page.goto('/portal/pagar');
    await page.locator(`input[name="cuotas[${k.id}][]"][value="${segunda}"]`).check();
    await page.getByRole('button', { name: 'Generar la orden de pago' }).click();
    await expect(alerta(page)).toBeVisible();
    expect(await ordenes()).toBe(antes);
  }
  await page.goto('/portal/pagar');
  await page.locator(`input[name="cuotas[${k.id}][]"][value="${primera}"]`).check();
  await page.getByRole('button', { name: 'Generar la orden de pago' }).click();
  await expect(exito(page)).toContainText('Orden generada.');
  ordenId = Number(page.url().match(/ordenes\/(\d+)/)[1]);
  // La cuota ya integra una orden vigente (A7)
  await page.goto('/portal/pagar');
  await expect(page.locator(`input[name="cuotas[${k.id}][]"][value="${primera}"]`)).toBeDisabled();
  await pagarEnPasarela(page, ordenId);
  expect(await valor('SELECT estado FROM dbo.ordenes_pago_electronico WHERE id = @id', { id: ordenId })).toBe('Confirmada');
  expect(await valor('SELECT estado FROM dbo.cuotas WHERE credito_id = @k AND numero = @n', { k: k.id, n: primera })).toBe('Pagada');
  await page.context().close();
});

test('R-163 Comprobante del pago del portal en el portal y en el panel (CU-48)', async ({ browser, comoUsuario }) => {
  test.skip(!ordenId, 'No se generó la orden de R-162.');
  const [cobro] = await consulta(
    "SELECT TOP 1 b.id, b.canal, b.estado, b.importe FROM dbo.cobros b JOIN dbo.clientes c ON c.id = b.cliente_id WHERE c.cuit_cuil = @c AND b.canal = 'Electronico' ORDER BY b.id DESC",
    { c: ana.cuil },
  );
  expect(cobro, 'El pago de R-162 debe figurar como cobro electrónico').toBeTruthy();
  expect(cobro.estado).toBe('Confirmado');
  const page = await comoCliente(browser, CORREO_ANA, portal.C01);
  await page.goto('/portal/comprobantes');
  await expect(page.locator('main')).toContainText('Confirmado');
  await page.goto(`/portal/comprobantes/${cobro.id}`);
  await expect(page.locator('main')).toContainText(formato(cobro.importe));
  await page.context().close();
  // El mismo cobro lo ve la Cajera en el panel, con su comprobante (RN-31)
  expect(await valor('SELECT COUNT(*) FROM dbo.comprobantes_pago WHERE cobro_id = @id', { id: cobro.id })).toBe(1);
  const cajero = await comoUsuario('cajero');
  expect((await cajero.goto('/admin/cobros')).status()).toBe(200);
});

test('R-164 Pantalla angosta del portal (RNF-03)', async ({ browser }) => {
  const page = await comoCliente(browser, CORREO_ANA, portal.C01);
  await page.setViewportSize({ width: 360, height: 780 });
  const k = await creditoVigente(ana);
  for (const ruta of ['/portal/estado-cuenta', `/portal/estado-cuenta/creditos/${k.id}`, '/portal/pagar', '/portal/comprobantes', '/portal/perfil', '/portal/propuestas']) {
    await page.goto(ruta);
    expect(await anchoDocumento(page), ruta).toBeLessThanOrEqual(362);
  }
  await page.context().close();
});

// ---------------------------------------------------------------------------------------------------------------
// 25. Pre-registro y alta con consulta al padrón
// ---------------------------------------------------------------------------------------------------------------

const preRegistros = (cuil) => consulta('SELECT id, estado FROM dbo.pre_registros WHERE cuit_cuil = @c ORDER BY id', { c: cuil });

test('R-165 Pre-registro de Martina Díaz y verificación del correo (CU-104)', async ({ page }) => {
  // Persona que ya es cliente: Ana (A1)
  await page.goto('/portal/pre-registro');
  await page.locator('#nombre').fill('Ana');
  await page.locator('#apellido').fill('Gómez');
  await page.locator('#tipo_documento').selectOption('DNI');
  await page.locator('#nro_documento').fill(ana.dni);
  await page.locator('#cuit_cuil').fill(ana.cuil);
  await page.locator('#correo').fill('otra.ana@prueba.local');
  await page.locator('#acepta_politica').check();
  await page.getByRole('button', { name: 'Pre-registrarme' }).click();
  await expect(alerta(page)).toContainText('Usted ya es cliente de la entidad');
  expect(await preRegistros(ana.cuil)).toHaveLength(0);
  // Pre-registro válido
  await page.goto('/portal/pre-registro');
  await page.locator('#nombre').fill(martina.nombre);
  await page.locator('#apellido').fill(martina.apellido);
  await page.locator('#tipo_documento').selectOption('DNI');
  await page.locator('#nro_documento').fill(martina.dni);
  await page.locator('#cuit_cuil').fill(martina.cuil);
  await page.locator('#correo').fill(martina.correo);
  await page.locator('#telefono').fill(martina.telefono);
  await page.locator('#acepta_politica').check();
  await page.getByRole('button', { name: 'Pre-registrarme' }).click();
  await expect(page.locator('main h1')).toHaveText('Verifique su correo');
  const enlace = ultimoCorreo(martina.correo).enlace;
  expect(enlace).toContain('/portal/pre-registro/verificar/');
  await page.goto(enlace);
  await page.getByRole('button', { name: 'Verificar mi correo' }).click();
  await expect(page.locator('main')).toContainText('Su correo quedó verificado');
  expect((await preRegistros(martina.cuil))[0].estado).toBe('PendienteValidacion');
});

test('R-166 Alta desde la bandeja con los datos del padrón (CU-11 A9, D-60)', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  await page.goto('/admin/pre-registros');
  await expect(page.locator('main')).toContainText('Díaz');
  const [pre] = await preRegistros(martina.cuil);
  await page.goto(`/admin/pre-registros/${pre.id}`);
  await page.getByRole('link', { name: 'Completar el alta' }).click();
  await page.getByRole('button', { name: 'Consultar los datos del CUIT/CUIL' }).click();
  await expect(page.locator('[data-padron-resultado]')).toContainText('Se completaron el DNI, el nombre y el apellido');
  await expect(page.locator('#nro_documento')).toHaveValue(martina.dni);
  await expect(page.locator('#apellido')).toHaveValue('Díaz');
  // El padrón no trae el domicilio ni los datos laborales: los completa el Analista y el sistema no los inventa
  await expect(page.locator('#domicilio')).toHaveValue('');
  await expect(page.locator('#ocupacion')).toHaveValue('');
  await page.locator('#fecha_nacimiento').fill('1994-08-21');
  await page.locator('#estado_civil').selectOption('Soltero');
  await page.locator('#nacionalidad').fill('Argentina');
  await page.locator('#ocupacion').fill('Recepcionista');
  await page.locator('#empleador').fill('Clínica Apóstoles');
  await page.locator('#categoria_ingresos').selectOption('RelacionDependencia');
  await page.locator('#ingresos_declarados').fill('1300000');
  await elegirDomicilio(page, 'Av. San Martín 1515');
  await page.locator('#pep-no').check();
  await page.locator('#acepta_politica').check();
  await page.locator('#acepta_comunicaciones').check();
  await page.locator('#doc-DOC_IDENTIDAD').setInputFiles([documento('DNI_Diaz.pdf')]);
  await page.locator('#doc-RECIBO_SUELDO').setInputFiles([documento('recibo_sueldo_Diaz.pdf')]);
  await page.getByRole('button', { name: 'Registrar cliente' }).click();
  await expect(exito(page)).toContainText('Cliente registrado.');
  expect((await preRegistros(martina.cuil))[0].estado).toBe('Completado');
  expect(await valor('SELECT COUNT(*) FROM dbo.clientes WHERE cuit_cuil = @c', { c: martina.cuil })).toBe(1);
});

test('R-167 Persona que no figura en el servicio o servicio sin respuesta (D-60)', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  await page.goto('/admin/clientes/nuevo');
  await page.locator('label[for="tipo-PersonaHumana"]').click();
  for (const [cuil, dni] of [['20990000019', '99000001'], ['20980000010', '98000001']]) {
    await page.locator('#cuit_cuil').fill(cuil);
    await page.getByRole('button', { name: 'Consultar los datos del CUIT/CUIL' }).click();
    await expect(page.locator('[data-padron-resultado]'), cuil).toContainText('No es posible obtener los datos de esta persona.');
    await expect(page.locator('#nro_documento'), cuil).toHaveValue(dni);
    await expect(page.locator('#nombre'), cuil).toHaveValue('');
  }
});

// ---------------------------------------------------------------------------------------------------------------
// 26. Campañas crediticias
// ---------------------------------------------------------------------------------------------------------------

const campanaActiva = () => consulta("SELECT id, numero FROM dbo.campanas WHERE estado = 'Activa'");
const credenciales = { [CORREO_ANA]: portal.C01, [bruno.correo]: portal.C02, [CORREO_CARLA_NUEVO]: CLAVES.carla, [julian.correo]: CLAVES.julian };
const ofertasVigentes = async () => (await consulta(
  "SELECT o.id, o.solicitud_id, ca.correo FROM dbo.ofertas o JOIN dbo.clientes c ON c.id = o.cliente_id JOIN dbo.cuentas_acceso ca ON ca.id = c.cuenta_cliente_id WHERE o.estado = 'Vigente' ORDER BY o.id",
)).filter((f) => credenciales[f.correo]);

test('R-168 Parámetros de campañas y activación con los fondos de la prueba (CU-51, CU-93)', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  const versiones = await valor("SELECT COUNT(*) FROM dbo.versiones_parametros WHERE tipo = 'Campanas'");
  // Valores incoherentes: nada se registra
  await registrarParametros(page, { capital_maximo: '0', factor_cobrabilidad: '120', categorias: [] });
  for (const campo of ['capital_maximo', 'factor_cobrabilidad', 'categorias_admitidas']) await expect(error(page, campo), campo).toBeVisible();
  expect(await valor("SELECT COUNT(*) FROM dbo.versiones_parametros WHERE tipo = 'Campanas'")).toBe(versiones);
  const analista = await comoUsuario('analista');
  expect((await analista.goto('/admin/parametros/campanas')).status()).toBe(403);
  // Un aporte deja la proyección por encima del umbral y se registra la versión válida
  await registrarAporte(page, '40000000', 'Aporte para la campaña de la prueba integral');
  await registrarParametros(page);
  await expect(exito(page)).toContainText('de los parámetros de campañas');
  const salida = spark('fondos:proyectar');
  console.log(salida);
  expect(salida).toMatch(/activada la campaña N\.º \d+ con \d+ oferta\(s\)/);
  expect(await campanaActiva()).toHaveLength(1);
  expect(spark('fondos:proyectar')).toContain('la campaña activa continúa');
});

test('R-169 Las ofertas llegan solo a quienes cumplen las condiciones (RN-66)', async () => {
  const [campana] = await campanaActiva();
  const ofertas = await consulta(
    "SELECT o.id, c.cuit_cuil, c.categoria_ingresos, c.estado AS estado_cliente, "
    + "(SELECT COALESCE(MAX(DATEDIFF(day, q.vencimiento, CAST(SYSDATETIMEOFFSET() AT TIME ZONE 'Argentina Standard Time' AS date))), 0) FROM dbo.cuotas q JOIN dbo.creditos k ON k.id = q.credito_id WHERE k.cliente_id = c.id AND q.estado = 'Vencida') AS atraso "
    + 'FROM dbo.ofertas o JOIN dbo.clientes c ON c.id = o.cliente_id WHERE o.campana_id = @id',
    { id: campana.id },
  );
  expect(ofertas.length).toBeGreaterThan(0);
  for (const o of ofertas) {
    expect(['RelacionDependencia', 'JubiladoPensionado'], `categoría de ${o.cuit_cuil}`).toContain(o.categoria_ingresos);
    expect(o.estado_cliente, `estado de ${o.cuit_cuil}`).toBe('Activo');
    expect(Number(o.atraso), `atraso de ${o.cuit_cuil}`).toBeLessThanOrEqual(30);
  }
  // Las personas jurídicas no reciben ofertas de esta línea
  expect(await valor("SELECT COUNT(*) FROM dbo.ofertas o JOIN dbo.clientes c ON c.id = o.cliente_id WHERE o.campana_id = @id AND c.tipo_cliente = 'PersonaJuridica'", { id: campana.id })).toBe(0);
});

test('R-170 Consulta de campañas por el Analista (CU-50)', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const [campana] = await campanaActiva();
  await page.goto('/admin/campanas?estado=Activa');
  await expect(page.locator('main')).toContainText('Personal');
  await page.goto(`/admin/campanas/${campana.id}`);
  await expect(page.locator('main')).toContainText('oferta(s)');
  const cajero = await comoUsuario('cajero');
  expect((await cajero.goto('/admin/campanas')).status()).toBe(403);
});

test('R-171 Aceptar una oferta desde el portal genera la solicitud (CU-52, RN-42)', async ({ browser, comoUsuario }) => {
  const vigentes = await ofertasVigentes();
  test.skip(vigentes.length === 0, 'Ningún cliente con acceso conocido al portal recibió una oferta.');
  const oferta = vigentes[0];
  const compromisos = () => valor("SELECT COUNT(*) FROM dbo.afectaciones_fondos WHERE tipo = 'Compromiso' AND estado = 'Vigente'");
  const antes = await compromisos();
  const page = await comoCliente(browser, oferta.correo, credenciales[oferta.correo]);
  await page.goto('/portal/propuestas');
  await expect(page.locator(`main a[href$="/portal/ofertas/${oferta.id}"]`)).toBeVisible();
  await page.goto(`/portal/ofertas/${oferta.id}`);
  const alternativas = page.locator('input[name="alternativa"]');
  expect(await alternativas.count()).toBeGreaterThan(0);
  await alternativas.nth((await alternativas.count()) - 1).check();
  await page.getByRole('button', { name: 'Aceptar la oferta' }).click();
  await expect(exito(page)).toContainText('Aceptó la oferta: se generó la solicitud N.º');
  const [aceptada] = await consulta('SELECT estado, solicitud_id FROM dbo.ofertas WHERE id = @id', { id: oferta.id });
  expect(aceptada.estado).toBe('Aceptada');
  const [solicitud] = await consulta('SELECT origen, usuario_id FROM dbo.solicitudes_credito WHERE id = @id', { id: aceptada.solicitud_id });
  expect(solicitud).toMatchObject({ origen: 'Oferta', usuario_id: null });
  expect(await compromisos()).toBe(antes + 1);
  // Una segunda respuesta ya no se ofrece
  await page.goto(`/portal/ofertas/${oferta.id}`);
  await expect(page.getByRole('button', { name: 'Aceptar la oferta' })).toHaveCount(0);
  await page.context().close();
  // La solicitud sigue el circuito de la iteración 2 en el panel
  const analista = await comoUsuario('analista');
  expect((await analista.goto(`/admin/solicitudes/${aceptada.solicitud_id}`)).status()).toBe(200);
});

test('R-172 Rechazar otra oferta y consultar las propuestas de refinanciación (A1, CU-43 A5)', async ({ browser }) => {
  const vigentes = await ofertasVigentes();
  if (vigentes.length > 0) {
    const page = await comoCliente(browser, vigentes[0].correo, credenciales[vigentes[0].correo]);
    await page.goto(`/portal/ofertas/${vigentes[0].id}`);
    await page.getByRole('button', { name: 'Rechazar la oferta' }).click();
    await expect(page.locator('main')).toContainText('Rechazó la oferta. No se generó ninguna solicitud.');
    expect(await valor('SELECT estado FROM dbo.ofertas WHERE id = @id', { id: vigentes[0].id })).toBe('Rechazada');
    await page.context().close();
  }
  // Bruno ve en el portal la propuesta de refinanciación que se aplicó en la parte 3 (R-125)
  const propuesta = await valor('SELECT TOP 1 id FROM dbo.propuestas_refinanciacion WHERE cliente_id = @c ORDER BY id DESC', { c: await idCliente(bruno.cuil) });
  test.skip(!propuesta, 'Bruno no tiene propuestas de refinanciación.');
  const portalBruno = await comoCliente(browser, bruno.correo, portal.C02);
  await portalBruno.goto('/portal/propuestas');
  await expect(portalBruno.locator(`main a[href$="/portal/propuestas/refinanciaciones/${propuesta}"]`)).toBeVisible();
  await portalBruno.goto(`/portal/propuestas/refinanciaciones/${propuesta}`);
  await expect(portalBruno.locator('main h1')).toBeVisible();
  await portalBruno.context().close();
});

test('R-173 Cierre de la campaña por vigencia (CU-94)', async () => {
  const [campana] = await campanaActiva();
  await consulta("UPDATE dbo.campanas SET vence = DATEADD(minute, -1, SYSDATETIMEOFFSET()) WHERE estado = 'Activa'");
  spark('campanas:cerrar');
  const [cerrada] = await consulta('SELECT estado, causa_cierre FROM dbo.campanas WHERE id = @id', { id: campana.id });
  expect(cerrada).toEqual({ estado: 'Finalizada', causa_cierre: 'Vigencia' });
  expect(await valor("SELECT COUNT(*) FROM dbo.ofertas WHERE campana_id = @id AND estado = 'Vigente'", { id: campana.id })).toBe(0);
});

// ---------------------------------------------------------------------------------------------------------------
// 27. Reportes, tablero y auditoría sobre los datos de la prueba
// ---------------------------------------------------------------------------------------------------------------

const REPORTES = ['cartera', 'morosidad', 'cobranzas', 'solicitudes', 'refinanciaciones', 'proyeccion', 'campanas'];

test('R-174 Tablero gerencial y siete reportes con los datos de la prueba (CU-55, CU-65 a CU-71)', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  await page.goto('/admin/tablero');
  await expect(page.getByRole('link', { name: /Ver el reporte/ })).toHaveCount(8);
  const antes = await valor("SELECT COUNT(*) FROM dbo.registros_auditoria WHERE accion LIKE 'reportes.%'");
  for (const clave of REPORTES) {
    expect((await page.goto(`/admin/reportes/${clave}?generar=1`)).status(), clave).toBe(200);
    expect(await page.locator('body').innerText(), clave).not.toMatch(/Exception|SQLSTATE|Stack trace|Whoops/);
    await expect(page.getByRole('link', { name: /Exportar a Excel/ }), clave).toBeVisible();
  }
  // Cada generación queda auditada (RN-45)
  expect(await valor("SELECT COUNT(*) FROM dbo.registros_auditoria WHERE accion LIKE 'reportes.%'")).toBeGreaterThanOrEqual(antes + REPORTES.length);
  const analista = await comoUsuario('analista');
  expect((await analista.goto('/admin/reportes/cartera')).status()).toBe(403);
});

test('R-175 El reporte de cobranzas incluye el pago del portal y el de cartera a los clientes de la prueba (CU-65, CU-67)', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  const motos = await valor("SELECT id FROM dbo.lineas_credito WHERE codigo = 'MOTOS'");
  await page.goto(`/admin/reportes/cartera?generar=1&linea=${motos}`);
  for (const apellido of ['Gómez', 'Fernández', 'Sosa']) await expect(page.locator('main'), apellido).toContainText(apellido);
  // Total cobrado del mes: la suma de los cobros confirmados del sistema
  const total = await valor(
    "SELECT COALESCE(SUM(importe), 0) FROM dbo.cobros WHERE estado = 'Confirmado' AND CAST(fecha_hora AT TIME ZONE 'Argentina Standard Time' AS date) >= DATEFROMPARTS(YEAR(SYSDATETIMEOFFSET() AT TIME ZONE 'Argentina Standard Time'), MONTH(SYSDATETIMEOFFSET() AT TIME ZONE 'Argentina Standard Time'), 1)",
  );
  await page.goto('/admin/reportes/cobranzas?generar=1');
  await expect(page.locator('main')).toContainText('Total cobrado');
  await expect(page.locator('main')).toContainText(formato(total));
});

test('R-176 Exportación a Excel de cada reporte (RNF-02)', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  for (const clave of REPORTES) {
    const r = await page.request.get(`/admin/reportes/${clave}?generar=1&formato=xlsx`);
    expect(r.status(), clave).toBe(200);
    expect(r.headers()['content-type'], clave).toContain('spreadsheetml');
    expect((await r.body()).subarray(0, 2).toString(), clave).toBe('PK');
  }
});

test('R-177 Informe de caja diaria de las jornadas de la prueba (CU-56)', async ({ comoUsuario }) => {
  const gerente = await comoUsuario('gerente');
  await gerente.goto('/admin/caja-diaria');
  await expect(gerente.locator('main h1')).toHaveText('Informe de caja diaria');
  await expect(gerente.locator('#cajero')).toBeVisible();
  // Un Cajero solo ve sus propias jornadas (A2)
  const cajero = await comoUsuario('cajero');
  const otro = await valor('SELECT u.id FROM dbo.usuarios_internos u JOIN dbo.cuentas_acceso a ON a.id = u.id WHERE a.correo = @c', { c: usuarios.cajero2.correo });
  await cajero.goto(`/admin/caja-diaria?cajero=${otro}`);
  await expect(cajero.locator('main')).toContainText('Solo puede consultar sus propias jornadas');
});

test('R-178 Auditoría de las cuatro iteraciones sin datos sensibles (CU-57, RN-45)', async ({ comoUsuario }) => {
  const acciones = await accionesAuditadas();
  for (const a of [
    'usuarios.registrar', 'clientes.registrar', 'lineas.registrar', 'solicitudes.registrar', 'cobros.registrar', 'pagos.generar-orden',
    'pre-registros.registrar', 'pre-registros.verificar', 'clientes.consultar-padron', 'portal.acceso-denegado',
    'parametros.campanas', 'campanas.activar', 'campanas.aceptar-oferta', 'campanas.cerrar', 'tablero.consultar', 'reportes.cartera',
  ]) expect(acciones, a).toContain(a);
  // Ningún registro guarda contraseñas del portal ni enlaces con su código
  for (const prohibido of ['Portal-Ana-2026', 'Portal-Carla-2026', 'Portal-Julian-2026', '/portal/activar/', '/portal/cambio-correo/', '/portal/pre-registro/verificar/']) {
    expect(await valor('SELECT COUNT(*) FROM dbo.registros_auditoria WHERE detalle LIKE @t', { t: `%${prohibido}%` }), prohibido).toBe(0);
  }
  const page = await comoUsuario('admin');
  await page.goto('/admin/auditoria');
  await expect(page.locator('main tbody tr').first()).toBeVisible();
  const cajero = await comoUsuario('cajero');
  expect((await cajero.goto('/admin/auditoria')).status()).toBe(403);
});

// ---------------------------------------------------------------------------------------------------------------
// 28. Copia de seguridad y restauración del estado final
// ---------------------------------------------------------------------------------------------------------------

const DIR = '/var/opt/sigccr/backups';
const huella = async () => ({
  clientes: await valor('SELECT COUNT(*) FROM dbo.clientes'),
  creditos: await valor('SELECT COUNT(*) FROM dbo.creditos'),
  cuotas_pagadas: await valor("SELECT COUNT(*) FROM dbo.cuotas WHERE estado = 'Pagada'"),
  cobros: await valor('SELECT COUNT(*) FROM dbo.cobros'),
  importe_cobros: Number(await valor("SELECT COALESCE(SUM(importe), 0) FROM dbo.cobros WHERE estado = 'Confirmado'")),
  saldos: Number(await valor('SELECT COALESCE(SUM(importe_disponible), 0) FROM dbo.saldos_a_favor')),
  propuestas: await valor('SELECT COUNT(*) FROM dbo.propuestas_refinanciacion'),
  ofertas: await valor('SELECT COUNT(*) FROM dbo.ofertas'),
  movimientos_fondos: await valor('SELECT COUNT(*) FROM dbo.movimientos_fondos'),
});
let antes;
let copiaId;

test('R-179 Generar la copia del estado final (CU-61)', async ({ comoUsuario }) => {
  antes = await huella();
  const page = await comoUsuario('admin');
  await page.goto('/admin/copias');
  await page.locator('#descripcion').fill('Estado final de la prueba integral');
  await page.getByRole('button', { name: 'Generar copia' }).click();
  await expect(exito(page)).toContainText('Copia solicitada');
  copiaId = Number(page.url().match(/copias\/(\d+)/)[1]);
  console.log(spark('copias:procesar'));
  const [c] = await consulta('SELECT estado, hash_verificacion, detalle FROM dbo.copias_seguridad WHERE id = @id', { id: copiaId });
  expect(c.estado, c.detalle || '').toBe('Verificada');
  expect(c.hash_verificacion).toMatch(/^[0-9a-f]{64}$/);
});

test('R-180 Descargar la copia y comprobar su huella (CU-62)', async ({ comoUsuario }) => {
  const page = await comoUsuario('admin');
  await page.goto(`/admin/copias/${copiaId}`);
  const [descarga] = await Promise.all([page.waitForEvent('download'), page.getByRole('button', { name: 'Descargar copia' }).click()]);
  const hash = crypto.createHash('sha256').update(require('node:fs').readFileSync(await descarga.path())).digest('hex');
  expect(hash).toBe(await valor('SELECT hash_verificacion FROM dbo.copias_seguridad WHERE id = @id', { id: copiaId }));
});

test('R-181 Restaurar la copia: lo posterior se descarta y lo anterior se conserva (CU-63)', async ({ comoUsuario, browser }) => {
  test.setTimeout(10 * 60_000);
  const gerente = await comoUsuario('gerente');
  // Una operación posterior a la copia: la restauración la descarta
  await registrarAporte(gerente, '1000', 'Movimiento posterior a la copia');
  expect(await valor('SELECT COUNT(*) FROM dbo.movimientos_fondos')).toBe(antes.movimientos_fondos + 1);
  const page = await comoUsuario('admin');
  await page.goto(`/admin/copias/${copiaId}/restaurar`);
  await page.locator('#confirmacion').fill('si');
  await page.getByRole('button', { name: 'Restaurar la copia' }).click();
  await expect(page.locator('#error-confirmacion')).toContainText('Para confirmar, escriba RESTAURAR');
  await page.locator('#confirmacion').fill('RESTAURAR');
  await page.getByRole('button', { name: 'Restaurar la copia' }).click();
  await expect(page).toHaveURL(/\/mantenimiento$/);
  const otro = await browser.newPage();
  expect((await otro.goto('/admin/login')).status()).toBe(503);
  expect((await otro.goto('/portal/login')).status()).toBe(503);
  await otro.close();
  await cerrarBase();
  console.log(spark('copias:procesar'));
  await page.goto('/mantenimiento');
  await expect(page.locator('main h1')).toHaveText('Restauración finalizada');
  for (const u of Object.values(usuarios)) olvidarSesion(u);
  const [r] = await consulta('SELECT r.exitosa, c.id AS copia FROM dbo.restauraciones r JOIN dbo.copias_seguridad c ON c.id = r.copia_seguridad_id ORDER BY r.id DESC');
  expect(r).toMatchObject({ exitosa: true });
  // La base volvió al estado de la copia
  expect(await huella()).toEqual(antes);
});

test('R-182 Después de restaurar, los clientes de la prueba siguen operando', async ({ browser, comoUsuario }) => {
  // Las sesiones se cerraron: Ana vuelve a ingresar al portal con su contraseña y ve su estado de cuenta
  const page = await comoCliente(browser, CORREO_ANA, portal.C01);
  await page.goto('/portal/estado-cuenta');
  await expect(page.locator('main h1')).toHaveText('Estado de cuenta');
  await page.context().close();
  // El Administrador ve la restauración y la copia previa que el sistema generó antes de reemplazar la base
  const admin = await comoUsuario('admin');
  await admin.goto('/admin/copias');
  await expect(admin.locator('main')).toContainText('Última restauración');
  expect(await valor("SELECT COUNT(*) FROM dbo.copias_seguridad WHERE origen = 'PreviaRestauracion' AND estado = 'Verificada'")).toBeGreaterThanOrEqual(1);
  expect(await valor("SELECT COUNT(*) FROM dbo.registros_auditoria WHERE accion = 'copias.restaurar' AND resultado = 'Exitoso'")).toBeGreaterThanOrEqual(1);
});
