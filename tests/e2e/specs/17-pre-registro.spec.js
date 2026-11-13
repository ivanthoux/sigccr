// Iteración 4, bloque A. Pre-registro en el portal (CU-104) y alta desde el pre-registro (CU-11): I-01 a I-11 de
// docs/pruebas/prueba_funcional_iteracion_4.md. Usa los datos de demostración que cargó 16-demostracion.
const { test, expect } = require('../lib/fixtures');
const { ultimoCorreo, consulta, valor, spark, cerrarBase } = require('../lib/sistema');
const { documento } = require('../lib/archivos');
const { elegirDomicilio } = require('../lib/clientes');
const { demo, personas } = require('../lib/portal');

const { sofia, mateo } = personas;
const error = (page, campo) => page.locator(`#error-${campo}`);
const preRegistros = (cuil) => consulta('SELECT id, estado, fecha_verificacion FROM dbo.pre_registros WHERE cuit_cuil = @c ORDER BY id', { c: cuil });

/** Completa y envía el formulario de CU-104; `cambios` reemplaza datos y `sinPolitica` deja sin aceptar la política. */
async function preRegistrar(page, persona, { cambios = {}, sinPolitica = false } = {}) {
  const p = { ...persona, ...cambios };
  await page.goto('/portal/pre-registro');
  await page.locator('#nombre').fill(p.nombre);
  await page.locator('#apellido').fill(p.apellido);
  await page.locator('#tipo_documento').selectOption('DNI');
  await page.locator('#nro_documento').fill(p.dni);
  await page.locator('#cuit_cuil').fill(p.cuil);
  await page.locator('#correo').fill(p.correo);
  await page.locator('#telefono').fill(p.telefono);
  if (!sinPolitica) await page.locator('#acepta_politica').check();
  await page.getByRole('button', { name: 'Pre-registrarme' }).click();
}

/** Abre el enlace del correo de verificación y confirma (pasos 6 y 7). */
async function verificar(page, enlace) {
  await page.goto(enlace);
  await page.getByRole('button', { name: 'Verificar mi correo' }).click();
}

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);
// Las pruebas anteriores ya usaron parte del límite de intentos por dirección (RN-62), que comparten el inicio de
// sesión, la activación y el pre-registro
test.beforeAll(() => spark('cache:clear'));

test('I-01 Acceso al pre-registro', async ({ page }) => {
  await page.goto('/portal/login');
  await page.getByRole('link', { name: /Pre-registrarse/ }).click();
  await expect(page).toHaveURL(/\/portal\/pre-registro$/);
  for (const campo of ['nombre', 'apellido', 'nro_documento', 'cuit_cuil', 'correo', 'telefono', 'acepta_politica']) {
    await expect(page.locator(`#${campo}`), campo).toBeVisible();
  }
});

test('I-02 Validaciones', async ({ page }) => {
  await page.goto('/portal/pre-registro');
  await page.getByRole('button', { name: 'Pre-registrarme' }).click();
  await expect(page.locator('.alert-danger')).toContainText('Revise los datos marcados.');
  for (const campo of ['nombre', 'apellido', 'nro_documento', 'cuit_cuil', 'correo']) await expect(error(page, campo), campo).toBeVisible();
  await preRegistrar(page, sofia, { sinPolitica: true });
  await expect(error(page, 'acepta_politica')).toContainText('acepte la política');
  await preRegistrar(page, sofia, { cambios: { cuil: '27415551059' } });
  await expect(error(page, 'cuit_cuil')).toBeVisible();
  await preRegistrar(page, sofia, { cambios: { correo: 'sofia@' } });
  await expect(error(page, 'correo')).toBeVisible();
  expect(await preRegistros(sofia.cuil)).toHaveLength(0);
});

test('I-03 Persona que ya es cliente (A1)', async ({ page }) => {
  const franco = { nombre: 'Franco', apellido: 'Molina', dni: demo.franco.dni, cuil: demo.franco.cuil, correo: 'otro.franco@prueba.local', telefono: '' };
  await preRegistrar(page, franco);
  await expect(page.locator('.alert-danger')).toContainText('Usted ya es cliente de la entidad');
  expect(await preRegistros(franco.cuil)).toHaveLength(0);
});

test('I-04 Correo de otro cliente (A2)', async ({ page }) => {
  await preRegistrar(page, sofia, { cambios: { correo: demo.camila.correo } });
  await expect(error(page, 'correo')).toContainText('Ese correo ya está registrado para otro cliente');
});

let enlaceSofia;

test('I-05 Pre-registro válido', async ({ page }) => {
  await preRegistrar(page, sofia);
  await expect(page.locator('h1')).toHaveText('Verifique su correo');
  await expect(page.locator('main')).toContainText(sofia.correo);
  const correo = ultimoCorreo(sofia.correo);
  expect(correo.enlace).toContain('/portal/pre-registro/verificar/');
  enlaceSofia = correo.enlace;
  expect(await preRegistros(sofia.cuil)).toMatchObject([{ estado: 'PendienteVerificacion', fecha_verificacion: null }]);
});

test('I-06 Verificación del correo (RN-03)', async ({ page }) => {
  await verificar(page, enlaceSofia);
  await expect(page.locator('main')).toContainText('Su correo quedó verificado. Acérquese a la sucursal');
  expect((await preRegistros(sofia.cuil))[0].estado).toBe('PendienteValidacion');
  await verificar(page, enlaceSofia);
  await expect(page.locator('main')).toContainText('El enlace de verificación expiró o ya no es válido');
});

test('I-07 Reinicio sin verificar (A3)', async ({ page }) => {
  await preRegistrar(page, mateo);
  const primero = ultimoCorreo(mateo.correo).enlace;
  await preRegistrar(page, mateo);
  const segundo = ultimoCorreo(mateo.correo).enlace;
  expect(segundo).not.toBe(primero);
  expect(await preRegistros(mateo.cuil)).toHaveLength(1);
  await verificar(page, primero);
  await expect(page.locator('main')).toContainText('expiró o ya no es válido');
  await verificar(page, segundo);
  await expect(page.locator('main')).toContainText('Su correo quedó verificado.');
});

test('I-08 Bandeja de pre-registros y alertas', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  await page.goto('/admin/pre-registros');
  await expect(page.locator('main')).toContainText('Benítez');
  await expect(page.locator('main')).toContainText('Acosta');
  expect(await valor("SELECT COUNT(*) FROM dbo.alertas WHERE objeto_tipo = 'pre_registros'")).toBeGreaterThanOrEqual(2);
  const cajero = await comoUsuario('cajero');
  expect((await cajero.goto('/admin/pre-registros')).status()).toBe(403);
});

test('I-09 Completar el alta desde el pre-registro', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const [pre] = await preRegistros(sofia.cuil);
  await page.goto(`/admin/pre-registros/${pre.id}`);
  await page.getByRole('link', { name: 'Completar el alta' }).click();
  await expect(page).toHaveURL(new RegExp(`/admin/clientes/nuevo\\?pre=${pre.id}`));
  await expect(page.locator('#nombre')).toHaveValue(sofia.nombre);
  await expect(page.locator('#cuit_cuil')).toHaveValue(/27-?41555104-?0/);
  // A9: el padrón propone los datos del CUIL
  await page.getByRole('button', { name: 'Consultar el padrón de ARCA' }).click();
  await expect(page.locator('[data-padron-resultado]')).toContainText('Datos propuestos por el padrón');
  await page.locator('#fecha_nacimiento').fill('1999-04-12');
  await page.locator('#estado_civil').selectOption('Soltero');
  await page.locator('#nacionalidad').fill('Argentina');
  await page.locator('#ocupacion').fill('Recepcionista');
  await page.locator('#empleador').fill('Clínica Apóstoles');
  await page.locator('#categoria_ingresos').selectOption('RelacionDependencia');
  await page.locator('#ingresos_declarados').fill('1200000');
  await elegirDomicilio(page, 'Av. San Martín 1505');
  await page.locator('#pep-no').check();
  await page.locator('#acepta_politica').check();
  await page.locator('#acepta_comunicaciones').check();
  await page.locator('#doc-DOC_IDENTIDAD').setInputFiles([documento('DNI_Benitez.pdf')]);
  await page.locator('#doc-RECIBO_SUELDO').setInputFiles([documento('recibo_sueldo_Benitez.pdf')]);
  await page.getByRole('button', { name: 'Registrar cliente' }).click();
  await expect(page.locator('.alert-success[role="status"]')).toContainText('Cliente registrado.');
  const [completado] = await preRegistros(sofia.cuil);
  expect(completado.estado).toBe('Completado');
  expect(await valor('SELECT COUNT(*) FROM dbo.clientes WHERE cuit_cuil = @c', { c: sofia.cuil })).toBe(1);
});

test('I-10 Padrón sin respuesta, sin datos o con un CUIL inválido', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  await page.goto('/admin/clientes/nuevo');
  await page.locator('label[for="tipo-PersonaHumana"]').click();
  const resultado = page.locator('[data-padron-resultado]');
  for (const [cuil, texto] of [['20990000019', 'no responde'], ['20980000010', 'no tiene datos']]) {
    await page.locator('#cuit_cuil').fill(cuil);
    await page.getByRole('button', { name: 'Consultar el padrón de ARCA' }).click();
    await expect(resultado, cuil).toContainText(texto);
  }
  await page.locator('#cuit_cuil').fill('20111111110');
  await page.getByRole('button', { name: 'Consultar el padrón de ARCA' }).click();
  await expect(resultado).toHaveClass(/text-danger/);
  await expect(page.locator('#nombre')).toHaveValue('');
});

test('I-11 Descartar un pre-registro', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const [pre] = await preRegistros(mateo.cuil);
  await page.goto(`/admin/pre-registros/${pre.id}`);
  await page.locator('#motivo').fill('abc');
  await page.getByRole('button', { name: 'Descartar' }).click();
  await expect(error(page, 'motivo')).toContainText('al menos 5 caracteres');
  await page.locator('#motivo').fill('No se presentó a verificar su identidad.');
  await page.getByRole('button', { name: 'Descartar' }).click();
  await expect(page.locator('.alert-success[role="status"]')).toContainText('Pre-registro descartado.');
  expect((await preRegistros(mateo.cuil))[0].estado).toBe('Descartado');
});
