// Iteración 4, bloque B. Campañas crediticias (CU-51, CU-93, CU-50, CU-52, CU-94): I-45 a I-62 de
// docs/pruebas/prueba_funcional_iteracion_4.md.
const { test, expect } = require('../lib/fixtures');
const { consulta, valor, spark, cerrarBase, ultimoCorreo } = require('../lib/sistema');
const { fecha, ddmmaaaa, portal } = require('../lib/datos');
const { demo, comoCliente } = require('../lib/portal');

const { joaquin } = demo;
const exito = (page) => page.locator('.alert-success[role="status"]');
const error = (page, campo) => page.locator(`#error-${campo}`);
const campanaActiva = () => consulta("SELECT id, numero, capital_asignado, capital_comprometido FROM dbo.campanas WHERE estado = 'Activa'");
const ofertaDe = (cuil) => consulta(
  'SELECT TOP 1 o.id, o.estado, o.solicitud_id FROM dbo.ofertas o JOIN dbo.clientes c ON c.id = o.cliente_id WHERE c.cuit_cuil = @c ORDER BY o.id DESC',
  { c: cuil },
).then((f) => f[0]);

/** CU-51: completa el formulario de parámetros de campañas y registra la versión. */
async function registrarParametros(page, cambios = {}) {
  const p = {
    umbral_disponibilidad: '0', periodo_proyeccion_dias: '30', factor_cobrabilidad: '90', reserva_minima: '0', linea: 'Electrodomésticos (ELECTRO)',
    capital_maximo: '3000000', cantidad_maxima_ofertas: '200', categorias: ['RelacionDependencia'], antiguedad_minima_meses: '0', morosidad_maxima_dias: '30',
    admite_obligaciones_vigentes: true, mensaje: 'Oferta especial de electrodomésticos', duracion_dias: '15', cierre_por_disponibilidad: true,
    fecha_vigencia: fecha(), motivo: 'Campaña de prueba de la iteración 4', ...cambios,
  };
  await page.goto('/admin/parametros/campanas');
  for (const k of ['umbral_disponibilidad', 'periodo_proyeccion_dias', 'factor_cobrabilidad', 'reserva_minima', 'capital_maximo', 'cantidad_maxima_ofertas', 'antiguedad_minima_meses', 'morosidad_maxima_dias', 'duracion_dias']) {
    await page.locator(`#${k}`).fill(p[k]);
  }
  if (p.linea === '') await page.locator('#linea_id').selectOption('');
  else await page.locator('#linea_id').selectOption({ label: p.linea });
  for (const caja of await page.locator('input[name="categorias_admitidas[]"]').all()) {
    await caja.setChecked(p.categorias.includes(await caja.getAttribute('value')));
  }
  await page.locator('#canal-Correo').check();
  await page.locator('#admite_obligaciones_vigentes').setChecked(p.admite_obligaciones_vigentes);
  await page.locator('#cierre_por_disponibilidad').setChecked(p.cierre_por_disponibilidad);
  await page.locator('#mensaje').fill(p.mensaje);
  await page.locator('#fecha_vigencia').fill(p.fecha_vigencia);
  await page.locator('#motivo').fill(p.motivo);
  await page.getByRole('button', { name: 'Registrar nueva versión' }).click();
}

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);
test.beforeAll(() => spark('cache:clear'));

test('I-45 Parámetros vigentes y acceso (CU-51)', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  await page.goto('/admin/parametros/campanas');
  await expect(page.locator('#umbral_disponibilidad')).toBeVisible();
  await expect(page.locator('#canal-Correo')).toBeChecked();
  const analista = await comoUsuario('analista');
  expect((await analista.goto('/admin/parametros/campanas')).status()).toBe(403);
});

test('I-46 Valores incoherentes', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  const versiones = await valor("SELECT COUNT(*) FROM dbo.versiones_parametros WHERE tipo = 'Campanas'");
  await registrarParametros(page, { capital_maximo: '0', factor_cobrabilidad: '120', linea: '', categorias: [] });
  for (const campo of ['capital_maximo', 'factor_cobrabilidad', 'linea_id', 'categorias_admitidas']) {
    await expect(error(page, campo), campo).toBeVisible();
  }
  // Con los datos válidos, la fecha anterior se rechaza por retroactividad
  await registrarParametros(page, { fecha_vigencia: fecha(-1) });
  await expect(page.locator('.alert-danger')).toContainText('no se admite retroactividad');
  expect(await valor("SELECT COUNT(*) FROM dbo.versiones_parametros WHERE tipo = 'Campanas'")).toBe(versiones);
});

test('I-47 Nueva versión de los parámetros', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  // Los créditos de demostración consumieron los fondos: un aporte deja la proyección por encima del umbral (CU-93)
  await page.goto('/admin/fondos');
  await page.locator('#tipo').selectOption('AporteCapital');
  await page.locator('#caracter-Ingreso').check();
  await page.locator('#programado').setChecked(false);
  await page.locator('#importe').fill('40000000');
  await page.locator('#fecha').fill(ddmmaaaa(fecha()));
  await page.locator('#concepto').fill('Aporte para la campaña de la iteración 4');
  await page.getByRole('button', { name: 'Registrar movimiento' }).click();
  await expect(exito(page)).toContainText('Movimiento registrado.');
  await registrarParametros(page);
  await expect(exito(page)).toContainText('de los parámetros de campañas');
});

test('I-48 Activación de la campaña (CU-93)', async () => {
  const salida = spark('fondos:proyectar');
  console.log(salida);
  expect(salida).toMatch(/activada la campaña N\.º \d+ con \d+ oferta\(s\)/);
  const [campana] = await campanaActiva();
  expect(campana).toBeTruthy();
  const oferta = await ofertaDe(joaquin.cuil);
  expect(oferta?.estado).toBe('Vigente');
  expect(ultimoCorreo(joaquin.correo)?.asunto).toMatch(/oferta/i);
  expect(await valor("SELECT COUNT(*) FROM dbo.alertas WHERE causa LIKE N'Se activó la campaña%'")).toBeGreaterThanOrEqual(1);
});

test('I-49 Una sola campaña activa (RN-66)', async () => {
  expect(spark('fondos:proyectar')).toContain('la campaña activa continúa');
  expect(await campanaActiva()).toHaveLength(1);
});

test('I-50 Consulta de campañas (CU-50)', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const [campana] = await campanaActiva();
  await page.goto('/admin/campanas?estado=Activa');
  await expect(page.locator('main')).toContainText('Electrodomésticos');
  await page.goto(`/admin/campanas/${campana.id}`);
  await expect(page.locator('main')).toContainText('Medina');
  await page.goto(`/admin/campanas/ofertas/${(await ofertaDe(joaquin.cuil)).id}`);
  await expect(page.locator('main')).toContainText('Medina');
});

test('I-51 a I-53 Ver y aceptar la oferta en el portal (CU-52)', async ({ browser }) => {
  const page = await comoCliente(browser, joaquin.correo);
  const oferta = await ofertaDe(joaquin.cuil);
  const compromisos = () => valor("SELECT COUNT(*) FROM dbo.afectaciones_fondos WHERE tipo = 'Compromiso' AND estado = 'Vigente'");
  const antes = await compromisos();
  await page.goto('/portal/propuestas');
  await expect(page.locator(`main a[href$="/portal/ofertas/${oferta.id}"]`)).toBeVisible();
  await page.goto(`/portal/ofertas/${oferta.id}`);
  const alternativas = page.locator('input[name="alternativa"]');
  expect(await alternativas.count()).toBeGreaterThan(0);
  // Una alternativa menor que la máxima, si la hay
  await alternativas.nth((await alternativas.count()) - 1).check();
  await page.getByRole('button', { name: 'Aceptar la oferta' }).click();
  await expect(exito(page)).toContainText('Aceptó la oferta: se generó la solicitud N.º');
  const aceptada = await ofertaDe(joaquin.cuil);
  expect(aceptada.estado).toBe('Aceptada');
  const [solicitud] = await consulta('SELECT origen, usuario_id, estado FROM dbo.solicitudes_credito WHERE id = @id', { id: aceptada.solicitud_id });
  expect(solicitud).toMatchObject({ origen: 'Oferta', usuario_id: null });
  expect(await compromisos()).toBe(antes + 1);
  // I-53: la oferta aceptada ya no ofrece aceptar ni rechazar (RN-42)
  await page.goto(`/portal/ofertas/${oferta.id}`);
  await expect(page.getByRole('button', { name: 'Aceptar la oferta' })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Rechazar la oferta' })).toHaveCount(0);
  await page.context().close();
});

test('I-55 Rechazar una oferta (A1)', async ({ browser }) => {
  // Otro cliente con oferta vigente y acceso al portal conocido (Ana o Bruno de la prueba integral)
  const credenciales = { 'ana.gomez2@prueba.local': portal.C01, 'bruno.fernandez@prueba.local': portal.C02 };
  const filas = await consulta(
    "SELECT o.id, ca.correo FROM dbo.ofertas o JOIN dbo.clientes c ON c.id = o.cliente_id JOIN dbo.cuentas_acceso ca ON ca.id = c.cuenta_cliente_id WHERE o.estado = 'Vigente'",
  );
  const oferta = filas.find((f) => credenciales[f.correo]);
  test.skip(!oferta, 'Ningún cliente con acceso conocido al portal recibió una oferta en esta campaña.');
  const page = await comoCliente(browser, oferta.correo, credenciales[oferta.correo]);
  await page.goto(`/portal/ofertas/${oferta.id}`);
  await page.getByRole('button', { name: 'Rechazar la oferta' }).click();
  await expect(page.locator('main')).toContainText('Rechazó la oferta. No se generó ninguna solicitud.');
  expect(await valor('SELECT estado FROM dbo.ofertas WHERE id = @id', { id: oferta.id })).toBe('Rechazada');
  await page.context().close();
});

test('I-56 Cierre por vigencia (CU-94)', async () => {
  const [campana] = await campanaActiva();
  await consulta("UPDATE dbo.campanas SET vence = DATEADD(minute, -1, SYSDATETIMEOFFSET()) WHERE estado = 'Activa'");
  spark('campanas:cerrar');
  const [cerrada] = await consulta('SELECT estado, causa_cierre FROM dbo.campanas WHERE id = @id', { id: campana.id });
  expect(cerrada).toEqual({ estado: 'Finalizada', causa_cierre: 'Vigencia' });
  expect(await valor("SELECT COUNT(*) FROM dbo.ofertas WHERE campana_id = @id AND estado = 'Vigente'", { id: campana.id })).toBe(0);
});

test('I-60 Línea suspendida (A2)', async () => {
  await consulta("UPDATE dbo.lineas_credito SET estado = 'Suspendida' WHERE codigo = 'ELECTRO'");
  try {
    const salida = spark('fondos:proyectar');
    expect(salida).toContain('no se activó');
    expect(salida).toContain('no está activa');
    expect(await campanaActiva()).toHaveLength(0);
  } finally {
    await consulta("UPDATE dbo.lineas_credito SET estado = 'Activa' WHERE codigo = 'ELECTRO'");
  }
});

test('I-62 Auditoría del bloque B', async () => {
  const acciones = (await consulta('SELECT DISTINCT accion FROM dbo.registros_auditoria')).map((f) => f.accion);
  for (const a of ['parametros.campanas', 'campanas.activar', 'campanas.aceptar-oferta', 'campanas.cerrar']) expect(acciones, a).toContain(a);
});
