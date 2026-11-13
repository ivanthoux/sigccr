// Iteración 4, bloque A. Portal de autogestión (CU-45 a CU-49, CU-75, CU-98): I-12 a I-43 de
// docs/pruebas/prueba_funcional_iteracion_4.md, con los clientes de demostración Franco Molina y Joaquín Medina.
const { test, expect } = require('../lib/fixtures');
const { ultimoCorreo, consulta, valor, spark, cerrarBase } = require('../lib/sistema');
const { demo, activarPortal, entrarPortal, comoCliente, anchoDocumento, CLAVE_DEMO } = require('../lib/portal');

const { franco, joaquin } = demo;
const exito = (page) => page.locator('.alert-success[role="status"]');
const alerta = (page) => page.locator('.alert-danger').first();
const error = (page, campo) => page.locator(`#error-${campo}`);
const AJENO = 'No encontramos la información solicitada entre sus operaciones.';
const accesosAjenos = () => valor("SELECT COUNT(*) FROM dbo.registros_auditoria WHERE accion = 'portal.acceso-denegado'");
const idCliente = (cuil) => valor('SELECT id FROM dbo.clientes WHERE cuit_cuil = @c', { c: cuil });
const credito = async (cuil) => (await consulta(
  "SELECT TOP 1 k.id FROM dbo.creditos k JOIN dbo.clientes c ON c.id = k.cliente_id WHERE c.cuit_cuil = @c AND k.estado IN ('Activo', 'EnMora') ORDER BY k.id",
  { c: cuil },
))[0].id;

/** Simulador de la pasarela (como en 13-pagos-saldos): aprueba la orden y envía la notificación. */
async function pagarEnPasarela(page, ordenId) {
  const url = await valor('SELECT url_pago FROM dbo.ordenes_pago_electronico WHERE id = @id', { id: ordenId });
  await page.goto(url.replace(/^https?:\/\/[^/]+/, ''));
  await page.locator('#estado').selectOption('approved');
  await page.locator('#notificar').setChecked(true);
  await page.getByRole('button', { name: 'Simular el pago' }).click();
  await page.waitForLoadState();
  spark('pagos:notificaciones');
}

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);
test.beforeAll(() => spark('cache:clear'));

test('Preparación: activar el portal de Franco Molina y Joaquín Medina (CU-04)', async ({ page }) => {
  await activarPortal(page, franco.dni, franco.correo);
  await activarPortal(page, joaquin.dni, joaquin.correo);
  await entrarPortal(page, franco.correo);
});

test('I-12 Funciones del portal', async ({ browser }) => {
  const page = await comoCliente(browser, franco.correo);
  for (const nombre of ['Estado de cuenta', 'Pagar una cuota', 'Comprobantes', 'Mis solicitudes', 'Mi perfil', 'Refinanciaciones y ofertas']) {
    await expect(page.getByRole('link', { name: nombre }).first(), nombre).toBeVisible();
  }
  await page.context().close();
});

test('I-13 Perfil (CU-45)', async ({ browser }) => {
  const page = await comoCliente(browser, franco.correo);
  await page.goto('/portal/perfil');
  const main = page.locator('main');
  await expect(main).toContainText('20-35666406-0');
  await expect(main).toContainText(franco.correo);
  await expect(main).toContainText('Solicitar corrección de datos');
  await page.context().close();
});

test('I-14 e I-15 Revocar y otorgar el consentimiento (CU-98)', async ({ browser }) => {
  const page = await comoCliente(browser, franco.correo);
  const antes = await valor("SELECT COUNT(*) FROM dbo.consentimientos WHERE cliente_id = @id AND medio = 'Portal'", { id: await idCliente(franco.cuil) });
  await page.goto('/portal/perfil/preferencias');
  await page.getByRole('button', { name: 'Revocar el consentimiento' }).click();
  await expect(exito(page)).toContainText('Consentimiento revocado');
  await page.getByRole('button', { name: 'Otorgar el consentimiento' }).click();
  await expect(exito(page)).toContainText('Consentimiento otorgado');
  expect(await valor("SELECT COUNT(*) FROM dbo.consentimientos WHERE cliente_id = @id AND medio = 'Portal'", { id: await idCliente(franco.cuil) })).toBe(antes + 2);
  await page.context().close();
});

test('I-16 Cambio de correo: validaciones (A1)', async ({ browser }) => {
  const page = await comoCliente(browser, franco.correo);
  for (const [correo, texto] of [['', 'Ingrese el nuevo correo electrónico.'], [franco.correo, 'El nuevo correo es igual al actual.'], [demo.camila.correo, 'Ese correo ya está registrado para otro cliente']]) {
    await page.goto('/portal/perfil/correo');
    await page.locator('#correo_nuevo').fill(correo);
    await page.getByRole('button', { name: 'Enviar la confirmación' }).click();
    await expect(error(page, 'correo_nuevo'), correo || '(vacío)').toContainText(texto);
  }
  await page.context().close();
});

let enlaceRecuperacion;

test('I-17 e I-18 Solicitar y confirmar desde el correo actual (CU-75)', async ({ browser, page }) => {
  const portal = await comoCliente(browser, franco.correo);
  await portal.goto('/portal/perfil/correo');
  await portal.locator('#correo_nuevo').fill(franco.correoNuevo);
  await portal.getByRole('button', { name: 'Enviar la confirmación' }).click();
  await expect(exito(portal)).toContainText('Le enviamos un enlace a su correo actual');
  const confirmacion = ultimoCorreo(franco.correo);
  expect(confirmacion.enlace).toContain('/portal/cambio-correo/');
  expect(confirmacion.html).toContain(franco.correoNuevo);
  // I-20: un enlace de recuperación pedido con el correo anterior
  await page.goto('/portal/recuperar');
  await page.locator('#correo').fill(franco.correo);
  await page.getByRole('button', { name: 'Enviar instrucciones' }).click();
  enlaceRecuperacion = ultimoCorreo(franco.correo).enlace;
  expect(enlaceRecuperacion).toContain('/portal/restablecer/');
  // Se confirma sin sesión, como desde otro dispositivo
  await page.goto(confirmacion.enlace);
  await page.getByRole('button', { name: 'Confirmar el cambio' }).click();
  await expect(page.locator('main')).toContainText(`Cambio confirmado. Enviamos un enlace a ${franco.correoNuevo}`);
  // Un enlace ya usado se informa como no válido sin ofrecer confirmar
  await page.goto(confirmacion.enlace);
  await expect(page.locator('main')).toContainText('expiró o ya no es válido');
  await portal.context().close();
});

test('I-19 e I-20 Verificar el correo nuevo; el anterior y sus enlaces dejan de valer (RN-58)', async ({ page }) => {
  const verificacion = ultimoCorreo(franco.correoNuevo);
  expect(verificacion.enlace).toContain('/portal/verificar-correo/');
  await page.goto(verificacion.enlace);
  await page.getByRole('button', { name: 'Confirmar correo' }).click();
  await expect(page.locator('main')).toContainText('Su correo quedó verificado');
  await page.goto('/portal/login');
  await page.locator('#correo').fill(franco.correo);
  await page.locator('#contrasena').fill(CLAVE_DEMO);
  await page.getByRole('button', { name: 'Iniciar sesión' }).click();
  await expect(page).toHaveURL(/\/portal\/login/);
  await entrarPortal(page, franco.correoNuevo);
  await page.goto(enlaceRecuperacion);
  await expect(page).toHaveURL(/\/portal\/recuperar/);
  await expect(alerta(page)).toContainText('expiró o ya no es válido');
});

test('I-21, I-22 e I-24 Solicitudes propias y ajenas (CU-46)', async ({ browser }) => {
  const page = await comoCliente(browser, franco.correoNuevo);
  await page.goto('/portal/solicitudes');
  await expect(page.locator('main')).toContainText('Liquidada');
  await page.locator('main a[href*="/portal/solicitudes/"]').first().click();
  await expect(page.locator('main')).toContainText('Liquidada');
  const ajena = await valor('SELECT TOP 1 s.id FROM dbo.solicitudes_credito s JOIN dbo.clientes c ON c.id = s.cliente_id WHERE c.cuit_cuil = @c', { c: joaquin.cuil });
  const antes = await accesosAjenos();
  for (const id of [ajena, 999999]) {
    await page.goto(`/portal/solicitudes/${id}`);
    await expect(page).toHaveURL(/\/portal\/solicitudes$/);
    await expect(alerta(page)).toContainText(AJENO);
  }
  expect(await accesosAjenos()).toBe(antes + 2);
  await page.context().close();
});

test('I-25, I-27 e I-30 Estado de cuenta con mora (CU-47)', async ({ browser }) => {
  const page = await comoCliente(browser, franco.correoNuevo);
  await page.goto('/portal/estado-cuenta');
  await expect(page.locator('main')).toContainText('En mora');
  const id = await credito(franco.cuil);
  await page.goto(`/portal/estado-cuenta/creditos/${id}`);
  await expect(page.locator('main')).toContainText('Cuota');
  await page.goto(`/portal/estado-cuenta/creditos/${id}/cuotas/1`);
  await expect(page.locator('main')).toContainText('superada la tolerancia');
  const antes = await accesosAjenos();
  await page.goto(`/portal/estado-cuenta/creditos/${await credito(joaquin.cuil)}`);
  await expect(page).toHaveURL(/\/portal\/estado-cuenta$/);
  await expect(alerta(page)).toContainText(AJENO);
  expect(await accesosAjenos()).toBe(antes + 1);
  await page.context().close();
});

test('I-28 Cuota dentro de la tolerancia (A3)', async ({ browser }) => {
  const page = await comoCliente(browser, joaquin.correo);
  await page.goto(`/portal/estado-cuenta/creditos/${await credito(joaquin.cuil)}/cuotas/1`);
  await expect(page.locator('main')).toContainText('dentro de la tolerancia');
  await page.context().close();
});

let ordenId;

test('I-31 a I-34 Pago electrónico desde el portal (CU-49)', async ({ browser }) => {
  const page = await comoCliente(browser, franco.correoNuevo);
  const id = await credito(franco.cuil);
  const cliente = await idCliente(franco.cuil);
  const ordenes = () => valor('SELECT COUNT(*) FROM dbo.ordenes_pago_electronico WHERE cliente_id = @c', { c: cliente });
  const antes = await ordenes();
  // I-31: la selección debe empezar por la cuota más antigua (RN-29)
  await page.goto('/portal/pagar');
  await page.locator(`input[name="cuotas[${id}][]"][value="2"]`).check();
  await page.getByRole('button', { name: 'Generar la orden de pago' }).click();
  await expect(alerta(page)).toBeVisible();
  expect(await ordenes()).toBe(antes);
  // I-32
  await page.goto('/portal/pagar');
  await page.locator(`input[name="cuotas[${id}][]"][value="1"]`).check();
  await page.getByRole('button', { name: 'Generar la orden de pago' }).click();
  await expect(exito(page)).toContainText('Orden generada.');
  ordenId = Number(page.url().match(/ordenes\/(\d+)/)[1]);
  // I-33: la cuota ya integra una orden vigente
  await page.goto('/portal/pagar');
  await expect(page.locator(`input[name="cuotas[${id}][]"][value="1"]`)).toBeDisabled();
  await expect(page.locator('main')).toContainText(`En la orden N.º`);
  // I-34
  await pagarEnPasarela(page, ordenId);
  expect(await valor('SELECT estado FROM dbo.ordenes_pago_electronico WHERE id = @id', { id: ordenId })).toBe('Confirmada');
  expect(await valor('SELECT estado FROM dbo.cuotas WHERE credito_id = @k AND numero = 1', { k: id })).toBe('Pagada');
  await page.context().close();
});

test('I-36 a I-38 y I-40 Comprobantes (CU-48)', async ({ browser }) => {
  const page = await comoCliente(browser, franco.correoNuevo);
  await page.goto('/portal/comprobantes');
  await expect(page.locator('main')).toContainText('Confirmado');
  await page.locator('main a[href*="/portal/comprobantes/"]').first().click();
  const cobroId = Number(page.url().match(/comprobantes\/(\d+)/)[1]);
  const reemisiones = () => valor('SELECT cantidad_reemisiones FROM dbo.comprobantes_pago WHERE cobro_id = @id', { id: cobroId });
  const antes = await reemisiones();
  await page.evaluate(() => { window.print = () => {}; });
  await page.getByRole('button', { name: 'Descargar o imprimir' }).click();
  await page.waitForLoadState();
  expect(await reemisiones()).toBe(antes + 1);
  const ajeno = await valor('SELECT TOP 1 b.id FROM dbo.cobros b JOIN dbo.clientes c ON c.id = b.cliente_id WHERE c.cuit_cuil <> @c ORDER BY b.id', { c: franco.cuil });
  await page.goto(`/portal/comprobantes/${ajeno}`);
  await expect(page).toHaveURL(/\/portal\/comprobantes$/);
  await expect(alerta(page)).toContainText(AJENO);
  await page.context().close();
});

test('I-43 Pantalla angosta (360 px)', async ({ browser }) => {
  const page = await comoCliente(browser, franco.correoNuevo);
  await page.setViewportSize({ width: 360, height: 780 });
  for (const ruta of ['/portal/estado-cuenta', `/portal/estado-cuenta/creditos/${await credito(franco.cuil)}`, '/portal/pagar', '/portal/comprobantes', '/portal/perfil', '/portal/pre-registro']) {
    await page.goto(ruta);
    expect(await anchoDocumento(page), ruta).toBeLessThanOrEqual(362);
  }
  await page.context().close();
});

test('I-44 Auditoría del bloque A', async () => {
  const acciones = (await consulta('SELECT DISTINCT accion FROM dbo.registros_auditoria')).map((f) => f.accion);
  for (const a of ['pre-registros.registrar', 'pre-registros.verificar', 'pre-registros.descartar', 'clientes.consultar-padron', 'portal.acceso-denegado']) {
    expect(acciones, a).toContain(a);
  }
});
