// Parte 1, sección 1. Acceso al panel (CU-01 a CU-03): R-001 a R-007.
const { test, expect, entrar } = require('../lib/fixtures');
const { usuarios, contrasenasInvalidas } = require('../lib/datos');
const { ultimoCorreo, cantidadCorreos, consulta, cerrarBase } = require('../lib/sistema');

const admin = usuarios.admin;
const alerta = (page) => page.locator('.alert').first();

/** Simula el fin del bloqueo de 15 minutos (CU-01 A1) sin esperarlo: el estado que deja el bloqueo vencido. */
async function vencerBloqueo(correo) {
  await consulta(
    "UPDATE dbo.cuentas_acceso SET estado = 'Habilitada', intentos_fallidos = 0, inicio_ventana_intentos = NULL, bloqueada_hasta = NULL "
      + "WHERE correo = @c AND estado = 'BloqueadaTemporalmente'",
    { c: correo },
  );
}

/** Cinco intentos fallidos seguidos: mensaje genérico, advertencias y bloqueo. */
async function cincoFallos(page, correo) {
  const esperados = [
    'Usuario o contraseña incorrectos.',
    'Usuario o contraseña incorrectos.',
    'Le quedan 2 intentos',
    'Le queda un intento',
    'el acceso quedó bloqueado durante 15 minutos',
  ];
  for (const texto of esperados) {
    await entrar(page, { correo }, 'Clave-Equivocada-2026');
    await expect(alerta(page)).toContainText(texto);
  }
}

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);

test('R-001 Inicio de sesión del Administrador', async ({ page }) => {
  await entrar(page, admin);
  await expect(page).toHaveURL(/\/admin\/?$/);
  await expect(page.locator('.cuenta .nombre')).toContainText('Thoux');
  await expect(page.locator('.cuenta')).toContainText('Administrador');
  await expect(page.locator('nav.menu-lateral')).toContainText('Usuarios');
});

test('R-002 Contraseña incorrecta y bloqueo', async ({ page }) => {
  await cincoFallos(page, admin.correo);
  await entrar(page, admin);
  await expect(alerta(page)).toContainText('bloqueado');
  await expect(page).toHaveURL(/\/admin\/login/);
  const [cuenta] = await consulta('SELECT estado FROM dbo.cuentas_acceso WHERE correo = @c', { c: admin.correo });
  expect(cuenta.estado).toBe('BloqueadaTemporalmente');
  await vencerBloqueo(admin.correo);
});

test('R-003 Correo inexistente: la misma secuencia de mensajes', async ({ page }) => {
  await cincoFallos(page, 'nadie@prueba.local');
});

test('R-004 Campos vacíos', async ({ page }) => {
  await page.goto('/admin/login');
  await page.getByRole('button', { name: 'Iniciar sesión' }).click();
  await expect(alerta(page)).toContainText('Ingrese el correo electrónico y la contraseña.');
});

test('R-005 Recuperar acceso: el mismo mensaje y solo el existente recibe el enlace', async ({ page }) => {
  const mensajes = [];
  for (const correo of [admin.correo, 'nadie@prueba.local']) {
    await page.goto('/admin/recuperar');
    await page.locator('#correo').fill(correo);
    await page.getByRole('button', { name: 'Enviar instrucciones' }).click();
    await expect(alerta(page)).toBeVisible();
    mensajes.push(await alerta(page).innerText());
  }
  expect(mensajes[0]).toContain('Si el correo corresponde a una cuenta habilitada');
  expect(mensajes[1]).toBe(mensajes[0]);
  const correo = ultimoCorreo(admin.correo);
  expect(correo?.enlace).toContain('/admin/restablecer/');
  expect(cantidadCorreos('nadie@prueba.local')).toBe(0);
});

test('R-006 Contraseña nueva: rechazos, aceptación y enlace usado', async ({ page }) => {
  const enlace = ultimoCorreo(admin.correo, { enviar: false }).enlace;
  const probar = async (clave, confirmacion, esperado) => {
    await page.goto(enlace);
    await page.locator('#contrasena').fill(clave);
    await page.locator('#confirmacion').fill(confirmacion);
    await page.getByRole('button', { name: 'Guardar contraseña' }).click();
    await expect(alerta(page)).toContainText(esperado);
  };
  await probar(contrasenasInvalidas.corta, contrasenasInvalidas.corta, 'entre 15 y 128 caracteres');
  await probar(contrasenasInvalidas.comprometida, contrasenasInvalidas.comprometida, 'contraseñas comprometidas');
  await probar(admin.clave, admin.clave + 'x', 'La confirmación no coincide');
  await probar(admin.clave, admin.clave, 'La contraseña se actualizó. Ya puede iniciar sesión.');

  await entrar(page, admin);
  await expect(page.locator('.cuenta .nombre')).toContainText('Thoux');

  await page.goto(enlace);
  await expect(page.locator('body')).toContainText('El enlace expiró o ya no es válido');
});

test('R-007 Cierre de sesión', async ({ page }) => {
  await entrar(page, admin);
  await page.getByRole('link', { name: 'Cerrar sesión' }).click();
  await page.getByRole('button', { name: 'Cerrar sesión' }).click();
  await expect(alerta(page)).toContainText('La sesión se cerró correctamente.');
  await page.goto('/admin');
  await expect(page).toHaveURL(/\/admin\/login/);
});
