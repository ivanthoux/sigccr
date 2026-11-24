// Iteración 4, bloque A. Portal de autogestión (CU-45 a CU-49, CU-75, CU-98): I-12 a I-43 de
// docs/pruebas/prueba_funcional_iteracion_4.md, con los clientes de demostración Franco Molina y Joaquín Medina.
const { test, expect } = require('../lib/fixtures');
const { ultimoCorreo, consulta, valor, spark, cerrarBase } = require('../lib/sistema');
const { demo, activarPortal, entrarPortal, comoCliente, anchoDocumento, CLAVE_DEMO } = require('../lib/portal');
const { contrasenasInvalidas } = require('../lib/datos');

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
  for (const nombre of ['Estado de cuenta', 'Pagar una cuota', 'Comprobantes', 'Mis solicitudes', 'Mi perfil', 'Mis derechos sobre mis datos', 'Refinanciaciones y ofertas']) {
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

/** CU-107: completa el formulario de cambio de contraseña (el mismo en el panel y en el portal). */
async function cambiarContrasena(page, actual, nueva, confirmacion = nueva) {
  await page.locator('#actual').fill(actual);
  await page.locator('#contrasena').fill(nueva);
  await page.locator('#confirmacion').fill(confirmacion);
  await page.getByRole('button', { name: 'Cambiar la contraseña' }).click();
}

test('I-94 Cambiar la contraseña en el portal (CU-107)', async ({ browser }) => {
  const NUEVA = 'Portal-Cambio-2026-OK';
  const page = await comoCliente(browser, franco.correoNuevo);
  const otra = await comoCliente(browser, franco.correoNuevo); // segunda sesión abierta: debe cerrarse (A2)
  await page.goto('/portal/perfil');
  await page.getByRole('link', { name: 'Cambiar contraseña' }).click();
  await expect(page).toHaveURL(/\/portal\/perfil\/contrasena$/);
  await cambiarContrasena(page, 'Clave-Incorrecta-2026', NUEVA);
  await expect(alerta(page)).toContainText('La contraseña actual no es correcta.');
  await cambiarContrasena(page, CLAVE_DEMO, CLAVE_DEMO);
  await expect(alerta(page)).toContainText('La contraseña nueva debe ser distinta de la actual.');
  await cambiarContrasena(page, CLAVE_DEMO, contrasenasInvalidas.comprometida);
  await expect(alerta(page)).toContainText('lista de contraseñas comprometidas');
  await cambiarContrasena(page, CLAVE_DEMO, NUEVA, 'Otra-Confirmacion-2026');
  await expect(alerta(page)).toContainText('La confirmación no coincide');
  await cambiarContrasena(page, CLAVE_DEMO, NUEVA);
  await expect(exito(page)).toContainText('La contraseña se cambió.');
  await otra.goto('/portal/estado-cuenta');
  await expect(otra).toHaveURL(/\/portal\/login/);
  // La anterior ya no sirve y la nueva sí; después se restablece la de la batería
  const nuevo = await browser.newContext();
  const p2 = await nuevo.newPage();
  await p2.goto('/portal/login');
  await p2.locator('#correo').fill(franco.correoNuevo);
  await p2.locator('#contrasena').fill(CLAVE_DEMO);
  await p2.getByRole('button', { name: 'Iniciar sesión' }).click();
  await expect(alerta(p2)).toBeVisible();
  await entrarPortal(p2, franco.correoNuevo, NUEVA);
  await p2.goto('/portal/perfil/contrasena');
  await cambiarContrasena(p2, NUEVA, CLAVE_DEMO);
  await expect(exito(p2)).toContainText('La contraseña se cambió.');
  expect(await valor("SELECT COUNT(*) FROM dbo.registros_auditoria WHERE accion = 'acceso.cambio-contrasena'")).toBeGreaterThanOrEqual(2);
  await p2.context().close();
  await page.context().close();
  await otra.context().close();
});

test('I-96 Ejercer los derechos sobre los datos personales (CU-108, Ley 25.326)', async ({ browser, comoUsuario }) => {
  const page = await comoCliente(browser, franco.correoNuevo);
  await page.goto('/portal/perfil');
  await page.getByRole('link', { name: 'Ejercer mis derechos sobre mis datos' }).click();
  await expect(page).toHaveURL(/\/portal\/derechos$/);
  await expect(page.locator('[data-leyenda-aaip]')).toBeVisible();
  // Validaciones y revisión de una decisión automatizada: la solicitud de crédito aparece solo con ese tipo
  await page.getByRole('button', { name: 'Enviar solicitud' }).click();
  await expect(alerta(page)).toContainText('Revise los datos marcados.');
  await expect(error(page, 'tipo')).toBeVisible();
  await expect(error(page, 'detalle')).toBeVisible();
  await expect(page.locator('#solicitud_id')).toBeHidden();
  await page.locator('#tipo').selectOption('RevisionAutomatizada');
  await expect(page.locator('#solicitud_id')).toBeVisible();
  // Rectificación válida y sin repetición
  await page.locator('#tipo').selectOption('Rectificacion');
  await page.locator('#detalle').fill('Mi domicilio cambió: necesito que lo actualicen en mi ficha.');
  await page.getByRole('button', { name: 'Enviar solicitud' }).click();
  await expect(exito(page)).toContainText('Recibimos su solicitud');
  await expect(page.getByRole('cell', { name: 'Rectificación de datos inexactos' })).toBeVisible();
  await page.locator('#tipo').selectOption('Rectificacion');
  await page.locator('#detalle').fill('Segundo intento del mismo pedido pendiente.');
  await page.getByRole('button', { name: 'Enviar solicitud' }).click();
  await expect(alerta(page)).toContainText('Ya hay un pedido pendiente');
  const id = await valor("SELECT MAX(id) FROM dbo.solicitudes_derechos WHERE tipo = 'Rectificacion' AND canal = 'Portal'");
  expect(await valor("SELECT COUNT(*) FROM dbo.solicitudes_derechos WHERE cliente_id = @c AND tipo = 'Rectificacion'", { c: await idCliente(franco.cuil) })).toBe(1);
  expect(await valor("SELECT COUNT(*) FROM dbo.alertas WHERE objeto_tipo = 'solicitudes_derechos' AND objeto_id = @i AND estado = 'Pendiente'", { i: String(id) })).toBe(1);

  // El Analista la ve, la valida y responde
  const panel = await comoUsuario('analista');
  await panel.goto('/admin/derechos');
  await expect(panel.getByRole('heading', { name: 'Derechos del titular de los datos' })).toBeVisible();
  await panel.getByRole('link', { name: String(id), exact: true }).click();
  await expect(panel.getByRole('heading', { name: 'Rectificación de datos inexactos' })).toBeVisible();
  await panel.locator('#estado').selectOption('Atendida');
  await panel.locator('#respuesta').fill('ok');
  await panel.getByRole('button', { name: 'Registrar respuesta' }).click();
  await expect(error(panel, 'respuesta')).toBeVisible();
  await panel.locator('#estado').selectOption('Atendida');
  await panel.locator('#respuesta').fill('Actualizamos su domicilio en la ficha del cliente.');
  await panel.getByRole('button', { name: 'Registrar respuesta' }).click();
  await expect(exito(panel)).toContainText('Respuesta registrada.');
  expect(await valor('SELECT estado FROM dbo.solicitudes_derechos WHERE id = @i', { i: id })).toBe('Atendida');
  expect(await valor("SELECT COUNT(*) FROM dbo.alertas WHERE objeto_tipo = 'solicitudes_derechos' AND objeto_id = @i AND estado = 'Pendiente'", { i: String(id) })).toBe(0);
  expect(await valor("SELECT COUNT(*) FROM dbo.registros_auditoria WHERE accion IN ('derechos.registrar', 'derechos.resolver') AND objeto_id = @i", { i: String(id) })).toBe(2);

  // El cliente ve la respuesta
  await page.goto('/portal/derechos');
  await expect(page.getByText('Actualizamos su domicilio en la ficha del cliente.')).toBeVisible();

  // A1: una persona que se presenta en la sucursal; la registra el Analista desde la ficha del cliente
  await panel.goto(`/admin/clientes/${await idCliente(joaquin.cuil)}`);
  await panel.getByRole('link', { name: 'Registrar solicitud de derechos' }).click();
  await panel.locator('#tipo').selectOption('Acceso');
  await panel.locator('#detalle').fill('Se presentó en la sucursal y pidió una copia de sus datos.');
  await panel.getByRole('button', { name: 'Registrar solicitud' }).click();
  await expect(exito(panel)).toContainText('Solicitud registrada.');
  await expect(panel.locator('main')).toContainText('Sucursal');
  expect(await valor("SELECT COUNT(*) FROM dbo.solicitudes_derechos WHERE canal = 'Sucursal' AND tipo = 'Acceso' AND estado = 'Pendiente'")).toBeGreaterThanOrEqual(1);
  await page.context().close();
});
