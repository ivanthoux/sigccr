// Capturas de pantalla para el manual de usuario. Solo corre con E2E_CAPTURAS=1 (Actions → E2E → Run workflow con
// «capturas»), al final de la batería, con los datos que dejaron las pruebas anteriores. No verifica nada: si una
// pantalla falla, se registra en la salida y se sigue con la siguiente.
const fs = require('node:fs');
const path = require('node:path');
const { test } = require('../lib/fixtures');
const { valor, spark, cerrarBase } = require('../lib/sistema');
const { clientes } = require('../lib/datos');
const { demo, comoCliente, CLAVE_DEMO } = require('../lib/portal');
const { simular } = require('../lib/flujo');

const DIR = path.join(__dirname, '..', 'capturas');
const ALTO_MAXIMO = 2200;

test.describe.configure({ mode: 'serial' });
test.skip(!process.env.E2E_CAPTURAS, 'Las capturas del manual se generan a pedido (E2E_CAPTURAS=1).');
test.beforeAll(() => {
  fs.mkdirSync(DIR, { recursive: true });
  spark('cache:clear');
});
test.afterAll(cerrarBase);

/** Abre la ruta (si se indica) y guarda la captura; la página completa hasta ALTO_MAXIMO píxeles. */
async function foto(page, nombre, ruta = null, { completa = true, antes = null } = {}) {
  try {
    if (ruta) await page.goto(ruta);
    await page.waitForLoadState('networkidle').catch(() => {});
    if (antes) await antes(page);
    const alto = await page.evaluate(() => document.documentElement.scrollHeight);
    const ancho = page.viewportSize().width;
    await page.screenshot({
      path: path.join(DIR, `${nombre}.png`),
      fullPage: completa,
      ...(completa ? { clip: { x: 0, y: 0, width: ancho, height: Math.min(alto, ALTO_MAXIMO) } } : {}),
    });
    console.log(`captura ${nombre}: ${page.url()}`);
  } catch (e) {
    console.log(`captura ${nombre} FALLÓ: ${e.message.split('\n')[0]}`);
  }
}
const id = (sql, p = {}) => valor(sql, p).catch(() => null);
const cliente = (cuil) => id('SELECT id FROM dbo.clientes WHERE cuit_cuil = @c', { c: cuil });

test('Generales y acceso', async ({ page, comoUsuario }) => {
  test.setTimeout(180_000);
  await foto(page, '01-login-panel', '/admin/login', { completa: false });
  await foto(page, '02-recuperar', '/admin/recuperar', { completa: false });
  const g = await comoUsuario('gerente');
  await foto(g, '03-inicio-gerente', '/admin', { completa: false });
  await foto(g, '04-alertas', '/admin/alertas');
  await g.setViewportSize({ width: 390, height: 844 });
  await foto(g, '05-celular-menu', '/admin', { completa: false, antes: async (p) => {
    await p.getByRole('button', { name: 'Abrir el menú' }).click().catch(() => {});
    await p.waitForTimeout(500);
  } });
});

test('Administrador', async ({ comoUsuario }) => {
  test.setTimeout(180_000);
  const a = await comoUsuario('admin');
  await foto(a, '10-usuarios', '/admin/usuarios');
  await foto(a, '11-usuario-nuevo', '/admin/usuarios/nuevo');
  const u = await id("SELECT TOP 1 u.id FROM dbo.usuarios_internos u JOIN dbo.cuentas_acceso c ON c.id = u.id WHERE c.correo = 'analista@prueba.local'");
  if (u) await foto(a, '12-usuario-ficha', `/admin/usuarios/${u}`);
  await foto(a, '13-auditoria', '/admin/auditoria');
  const r = await id('SELECT MAX(id) FROM dbo.registros_auditoria');
  if (r) await foto(a, '14-auditoria-detalle', `/admin/auditoria/${r}`);
  await foto(a, '15-copias', '/admin/copias');
  const c = await id("SELECT MAX(id) FROM dbo.copias_seguridad WHERE estado = 'Verificada'");
  if (c) {
    await foto(a, '16-copia-ver', `/admin/copias/${c}`);
    await foto(a, '17-copia-restaurar', `/admin/copias/${c}/restaurar`);
  }
});

test('Analista de Crédito', async ({ comoUsuario }) => {
  test.setTimeout(300_000);
  const a = await comoUsuario('analista');
  await foto(a, '20-clientes', '/admin/clientes');
  await foto(a, '21-cliente-nuevo', '/admin/clientes/nuevo');
  const ana = await cliente(clientes.C01.cuil);
  if (ana) {
    await foto(a, '22-cliente-ficha', `/admin/clientes/${ana}`);
    await foto(a, '23-cliente-historial', `/admin/clientes/${ana}/historial`);
  }
  await foto(a, '24-pre-registros', '/admin/pre-registros');
  const pre = await id('SELECT MAX(id) FROM dbo.pre_registros');
  if (pre) await foto(a, '25-pre-registro', `/admin/pre-registros/${pre}`);
  await foto(a, '26-simulador', '/admin/simulaciones');
  try {
    const sim = await simular(a, clientes.C06, 'MOTOS', 500000, 12);
    if (sim) {
      await foto(a, '27-simulacion', `/admin/simulaciones/${sim}`);
      await foto(a, '28-solicitud-nueva', `/admin/solicitudes/nueva/${sim}`);
    }
  } catch (e) {
    console.log(`simulación FALLÓ: ${e.message.split('\n')[0]}`);
  }
  await foto(a, '29-comparar', '/admin/simulaciones/comparar');
  await foto(a, '30-solicitudes', '/admin/solicitudes');
  const liq = await id("SELECT MAX(id) FROM dbo.solicitudes_credito WHERE estado = 'Liquidada'");
  if (liq) {
    await foto(a, '31-solicitud', `/admin/solicitudes/${liq}`);
    await foto(a, '32-evaluacion', `/admin/solicitudes/${liq}/evaluacion`);
  }
  const verif = await id("SELECT MAX(id) FROM dbo.solicitudes_credito WHERE estado IN ('PendienteVerificacion', 'PendienteDocumentacion')");
  if (verif) await foto(a, '33-verificar', `/admin/solicitudes/${verif}/verificar`);
  const alt = await id("SELECT MAX(id) FROM dbo.solicitudes_credito WHERE estado = 'PendienteSeleccionPlan'");
  if (alt) await foto(a, '34-alternativas', `/admin/solicitudes/${alt}/alternativas`);
  await foto(a, '35-contingencias', '/admin/evaluaciones/contingencia');
  await foto(a, '36-contratos', '/admin/contratos');
  const con = await id("SELECT MAX(id) FROM dbo.solicitudes_credito WHERE estado IN ('AprobadaPendienteLiquidacion', 'Liquidada')");
  if (con) await foto(a, '37-contrato', `/admin/contratos/${con}`);
  await foto(a, '38-lineas', '/admin/lineas');
  const motos = await id("SELECT id FROM dbo.lineas_credito WHERE codigo = 'MOTOS'");
  if (motos) await foto(a, '39-linea', `/admin/lineas/${motos}`);
  await foto(a, '40-refinanciaciones', '/admin/refinanciaciones');
  const suarez = await cliente(demo.suarez?.cuil || '20349996090');
  await foto(a, '41-refinanciacion-simular', `/admin/refinanciaciones/simular${suarez ? `?cliente=${suarez}` : ''}`);
  await foto(a, '42-campanas', '/admin/campanas');
});

test('Gerente', async ({ comoUsuario }) => {
  test.setTimeout(300_000);
  const g = await comoUsuario('gerente');
  await foto(g, '50-tablero', '/admin/tablero');
  await foto(g, '51-reportes', '/admin/reportes');
  await foto(g, '52-reporte-cartera', '/admin/reportes/cartera?generar=1');
  await foto(g, '53-reporte-morosidad', '/admin/reportes/morosidad?generar=1');
  await foto(g, '54-resoluciones', '/admin/resoluciones');
  const res = await id("SELECT MAX(id) FROM dbo.solicitudes_credito WHERE estado = 'PendienteResolucion'");
  if (res) await foto(g, '55-resolver', `/admin/solicitudes/${res}/resolver`);
  await foto(g, '56-fondos', '/admin/fondos');
  await foto(g, '57-parametros-evaluacion', '/admin/parametros/evaluacion');
  await foto(g, '58-parametros-estructuracion', '/admin/parametros/estructuracion');
  await foto(g, '59-parametros-morosidad', '/admin/parametros/morosidad');
  await foto(g, '60-parametros-campanas', '/admin/parametros/campanas');
  await foto(g, '61-calendario', '/admin/calendario');
  await foto(g, '62-documentacion', '/admin/documentacion');
  await foto(g, '63-monotributo', '/admin/monotributo');
  await foto(g, '64-linea-nueva', '/admin/lineas/nueva');
  await foto(g, '65-cajas', '/admin/cajas');
  await foto(g, '66-movimientos-caja', '/admin/cajas/movimientos');
  await foto(g, '67-anular', '/admin/cobros/anulaciones');
  await foto(g, '68-autorizar-devoluciones', '/admin/saldos-favor/devoluciones');
  await foto(g, '69-condonar', '/admin/punitorios/condonar');
  await foto(g, '70-campanas', '/admin/campanas');
  const camp = await id('SELECT MAX(id) FROM dbo.campanas');
  if (camp) await foto(g, '71-campana', `/admin/campanas/${camp}`);
  await foto(g, '72-regimen', '/admin/regimen');
  const reg = await id('SELECT MAX(id) FROM dbo.regimenes_informativos');
  if (reg) await foto(g, '73-regimen-ver', `/admin/regimen/${reg}`);
  await foto(g, '74-caja-diaria', '/admin/caja-diaria');
});

test('Cajero', async ({ comoUsuario }) => {
  test.setTimeout(300_000);
  const c = await comoUsuario('cajero');
  await foto(c, '80-mi-caja', '/admin/caja');
  const suarez = await cliente('20349996090');
  await foto(c, '81-cobro', `/admin/cobros${suarez ? `?cliente=${suarez}` : ''}`);
  await foto(c, '82-comprobantes', '/admin/cobros/comprobantes');
  const cobro = await id('SELECT MAX(id) FROM dbo.cobros');
  if (cobro) await foto(c, '83-comprobante', `/admin/cobros/${cobro}/comprobante`);
  await foto(c, '84-pagos-electronicos', '/admin/pagos-electronicos');
  await foto(c, '85-orden-nueva', '/admin/pagos-electronicos/nueva');
  await foto(c, '86-liquidaciones', '/admin/liquidaciones');
  const cre = await id("SELECT MAX(id) FROM dbo.creditos WHERE estado IN ('Activo', 'EnMora')");
  if (cre) await foto(c, '87-cancelacion', `/admin/creditos/${cre}/cancelacion-anticipada`);
  await foto(c, '88-saldos-aplicar', '/admin/saldos-favor/aplicar');
  await foto(c, '89-devoluciones', '/admin/devoluciones');
  await foto(c, '90-revocaciones', '/admin/revocaciones');
  await foto(c, '91-cierre-caja', '/admin/caja/cierre');
  await foto(c, '92-caja-diaria', '/admin/caja-diaria');
});

test('Portal del cliente', async ({ page, browser }) => {
  test.setTimeout(240_000);
  await foto(page, '100-portal-login', '/portal/login', { completa: false });
  await foto(page, '101-portal-activar', '/portal/activar', { completa: false });
  await foto(page, '102-pre-registro', '/portal/pre-registro');
  let p;
  try {
    p = await comoCliente(browser, demo.franco.correoNuevo, CLAVE_DEMO);
  } catch (e) {
    console.log(`portal FALLÓ: ${e.message.split('\n')[0]}`);
    return;
  }
  await foto(p, '103-portal-inicio', '/portal');
  await foto(p, '104-estado-cuenta', '/portal/estado-cuenta');
  const cre = await id('SELECT MAX(c.id) FROM dbo.creditos c JOIN dbo.clientes cl ON cl.id = c.cliente_id WHERE cl.cuit_cuil = @c', { c: demo.franco.cuil });
  if (cre) await foto(p, '105-plan-credito', `/portal/estado-cuenta/creditos/${cre}`);
  await foto(p, '106-pagar', '/portal/pagar');
  await foto(p, '107-comprobantes', '/portal/comprobantes');
  await foto(p, '108-solicitudes', '/portal/solicitudes');
  await foto(p, '109-propuestas', '/portal/propuestas');
  await foto(p, '110-perfil', '/portal/perfil');
  await foto(p, '111-preferencias', '/portal/perfil/preferencias');
  await p.setViewportSize({ width: 390, height: 844 });
  await foto(p, '112-portal-celular', '/portal/estado-cuenta', { completa: false });
  await p.context().close();
});
