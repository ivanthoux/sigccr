// Parte 1, sección 4. Portal del cliente (CU-01 a CU-04): R-027 a R-031.
const { test, expect } = require('../lib/fixtures');
const { clientes, empresas, portal } = require('../lib/datos');
const { ultimoCorreo, consulta, valor, cerrarBase } = require('../lib/sistema');

const { C01: ana, C02: bruno } = clientes;
const pyme = empresas.P01;
const correoNuevoAna = 'ana.gomez2@prueba.local';
const exito = (page) => page.locator('.alert-success');

/** Define la contraseña del portal desde el enlace (CU-04). */
async function definirContrasena(page, clave) {
  await page.locator('#contrasena').fill(clave);
  await page.locator('#confirmacion').fill(clave);
  await page.getByRole('button', { name: 'Activar mi acceso' }).click();
  await expect(exito(page)).toContainText('Su acceso al portal quedó activado.');
}

async function entrarPortal(page, correo, clave) {
  await page.goto('/portal/login');
  await page.locator('#correo').fill(correo);
  await page.locator('#contrasena').fill(clave);
  await page.getByRole('button', { name: 'Iniciar sesión' }).click();
  await expect(page).toHaveURL(/\/portal\/?$/);
}

/** CU-04 sin el enlace del alta: documento y correo. */
async function solicitarActivacion(page, tipo, numero, correo) {
  await page.goto('/portal/activar');
  await page.locator('#tipo_documento').selectOption(tipo);
  await page.locator('#numero').fill(numero);
  await page.locator('#correo').fill(correo);
  await page.getByRole('button', { name: 'Enviar instrucciones' }).click();
}

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);

test('R-027 Verificar el correo y activar el portal (D-16)', async ({ page }) => {
  // En R-024 Ana cambió su correo: el enlace vigente es el del correo nuevo
  const enlace = ultimoCorreo(correoNuevoAna).enlace;
  expect(enlace).toContain('/portal/verificar-correo/');
  await page.goto(enlace);
  await page.getByRole('button', { name: 'Confirmar correo' }).click();
  await expect(exito(page)).toContainText('Su correo quedó verificado.');
  await definirContrasena(page, portal.C01);
  const [cuenta] = await consulta("SELECT estado, correo_verificado FROM dbo.cuentas_acceso WHERE correo = @c AND tipo_cuenta = 'Cliente'", { c: correoNuevoAna });
  expect(cuenta).toEqual({ estado: 'Habilitada', correo_verificado: true });
  await entrarPortal(page, correoNuevoAna, portal.C01);
});

// Bruno y la pyme no verificaron su correo con el enlace del alta: CU-04 lo verifica al usar su enlace (D-40)
test('R-028 Activar sin el enlace del alta', async ({ page }) => {
  await solicitarActivacion(page, 'DNI', bruno.dni, bruno.correo);
  await expect(exito(page)).toBeVisible();
  const enlace = ultimoCorreo(bruno.correo).enlace;
  expect(enlace).toContain('/portal/activar/');
  await page.goto(enlace);
  await definirContrasena(page, portal.C02);
  await entrarPortal(page, bruno.correo, portal.C02);
});

test('R-029 Datos que no coinciden', async ({ page }) => {
  await solicitarActivacion(page, 'DNI', bruno.dni, correoNuevoAna);
  await expect(page.locator('.alert-danger')).toContainText('No pudimos verificar los datos ingresados');
});

test('R-030 Activar el portal de la persona jurídica', async ({ page }) => {
  await solicitarActivacion(page, 'CUIT', pyme.cuit, pyme.correo);
  await expect(exito(page)).toBeVisible();
  await page.goto(ultimoCorreo(pyme.correo).enlace);
  await definirContrasena(page, portal.P01);
  await entrarPortal(page, pyme.correo, portal.P01);
  expect(await valor("SELECT correo_verificado FROM dbo.cuentas_acceso WHERE correo = @c AND tipo_cuenta = 'Cliente'", { c: pyme.correo })).toBe(true);
});

test('R-031 Sesiones separadas del portal y el panel', async ({ page }) => {
  await entrarPortal(page, correoNuevoAna, portal.C01);
  await page.goto('/admin');
  await expect(page).toHaveURL(/\/admin\/login/);
  await page.goto('/portal/salir');
  await page.getByRole('button', { name: 'Cerrar sesión' }).click();
  await expect(exito(page)).toContainText('La sesión se cerró correctamente.');
  await page.goto('/portal');
  await expect(page).toHaveURL(/\/portal\/login/);
});
