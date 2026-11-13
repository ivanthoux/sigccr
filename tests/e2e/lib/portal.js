// Portal del cliente para las pruebas de la iteración 4: activación del acceso (CU-04) e inicio de sesión (CU-01).
const { expect } = require('@playwright/test');
const { ultimoCorreo } = require('./sistema');

/** Contraseña del portal de los clientes de demostración (guía de la prueba funcional de la iteración 4). */
const CLAVE_DEMO = 'Portal-Prueba-2026';

/** Clientes del seeder de demostración que usa la prueba funcional de la iteración 4. */
const demo = {
  franco: { nombre: 'Franco Molina', dni: '35666406', cuil: '20356664060', correo: 'franco.molina@demo.local', correoNuevo: 'franco.nuevo@demo.local' },
  joaquin: { nombre: 'Joaquín Medina', dni: '38111404', cuil: '20381114040', correo: 'joaquin.medina@demo.local' },
  camila: { correo: 'camila.herrera@demo.local' },
};

/** Personas que se pre-registran (CU-104); sus CUIL son válidos para el simulador del padrón. */
const personas = {
  sofia: { nombre: 'Sofía', apellido: 'Benítez', dni: '41555104', cuil: '27415551040', correo: 'sofia.benitez@prueba.local', telefono: '3758 43-1104' },
  mateo: { nombre: 'Mateo', apellido: 'Acosta', dni: '40222309', cuil: '20402223090', correo: 'mateo.acosta@prueba.local', telefono: '' },
};

async function entrarPortal(page, correo, clave = CLAVE_DEMO) {
  await page.goto('/portal/login');
  await page.locator('#correo').fill(correo);
  await page.locator('#contrasena').fill(clave);
  await page.getByRole('button', { name: 'Iniciar sesión' }).click();
  await expect(page).toHaveURL(/\/portal\/?$/);
}

/** CU-04: pide el enlace con el documento y el correo, lo abre y define la contraseña. */
async function activarPortal(page, dni, correo, clave = CLAVE_DEMO) {
  await page.goto('/portal/activar');
  await page.locator('#tipo_documento').selectOption('DNI');
  await page.locator('#numero').fill(dni);
  await page.locator('#correo').fill(correo);
  await page.getByRole('button', { name: 'Enviar instrucciones' }).click();
  const enlace = ultimoCorreo(correo).enlace;
  expect(enlace, `enlace de activación de ${correo}`).toContain('/portal/activar/');
  await page.goto(enlace);
  await page.locator('#contrasena').fill(clave);
  await page.locator('#confirmacion').fill(clave);
  await page.getByRole('button', { name: 'Activar mi acceso' }).click();
  await expect(page.locator('.alert-success[role="status"]')).toContainText('Su acceso al portal quedó activado.');
}

/** Abre una página del portal en un contexto propio, con la sesión del cliente. */
async function comoCliente(browser, correo, clave = CLAVE_DEMO) {
  const contexto = await browser.newContext();
  contexto.on('page', (p) => p.on('dialog', (d) => d.accept()));
  const page = await contexto.newPage();
  await entrarPortal(page, correo, clave);
  return page;
}

/** Ancho del documento: sin desplazamiento horizontal a 360 px (RNF-03). */
const anchoDocumento = (page) => page.evaluate(() => document.documentElement.scrollWidth);

module.exports = { CLAVE_DEMO, demo, personas, entrarPortal, activarPortal, comoCliente, anchoDocumento };
