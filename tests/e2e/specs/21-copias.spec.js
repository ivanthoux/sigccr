// Iteración 4, bloque D. Copias de seguridad y restauración (CU-61 a CU-63): I-81 a I-91 de
// docs/pruebas/prueba_funcional_iteracion_4.md. La pila de pruebas no tiene el contenedor scheduler: copias:procesar
// y copias:generar se ejecutan en app, que monta el volumen de copias con escritura (docker-compose.e2e.yml).
// Es el último archivo de la batería porque la restauración reemplaza la base.
const fs = require('node:fs');
const crypto = require('node:crypto');
const { test, expect, olvidarSesion } = require('../lib/fixtures');
const { consulta, valor, spark, enApp, cerrarBase } = require('../lib/sistema');
const { usuarios } = require('../lib/datos');

const DIR = '/var/opt/sigccr/backups';
const exito = (page) => page.locator('.alert-success[role="status"]');
const copia = (id) => consulta('SELECT id, archivo, estado, origen, hash_verificacion, responsable, detalle FROM dbo.copias_seguridad WHERE id = @id', { id }).then((f) => f[0]);
const ultimaCopia = () => valor('SELECT MAX(id) FROM dbo.copias_seguridad');
const archivosDelVolumen = () => enApp('sh', '-c', `ls -1 ${DIR}`).split('\n').filter(Boolean);

/** CU-61 desde el panel: la copia queda Solicitada y la genera copias:procesar. Devuelve su id. */
async function solicitarCopia(page, descripcion) {
  await page.goto('/admin/copias');
  await page.locator('#descripcion').fill(descripcion);
  await page.getByRole('button', { name: 'Generar copia' }).click();
  await expect(exito(page)).toContainText('Copia solicitada');
  return Number(page.url().match(/copias\/(\d+)/)[1]);
}

/** CU-62: descarga la copia desde su pantalla y devuelve el SHA-256 del archivo recibido. */
async function descargar(page, id) {
  await page.goto(`/admin/copias/${id}`);
  const [descarga] = await Promise.all([page.waitForEvent('download'), page.getByRole('button', { name: 'Descargar copia' }).click()]);
  return crypto.createHash('sha256').update(fs.readFileSync(await descarga.path())).digest('hex');
}

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);

test('I-81 Acceso y destino (CU-61 paso 2)', async ({ comoUsuario }) => {
  const page = await comoUsuario('admin');
  await page.goto('/admin/copias');
  await expect(page.locator('main')).toContainText(DIR);
  await expect(page.locator('main')).toContainText('libres');
  await expect(page.getByRole('button', { name: 'Generar copia' })).toBeEnabled();
  const gerente = await comoUsuario('gerente');
  expect((await gerente.goto('/admin/copias')).status()).toBe(403);
});

let manual;

test('I-82 Generar una copia manual (CU-61)', async ({ comoUsuario }) => {
  const page = await comoUsuario('admin');
  manual = await solicitarCopia(page, 'Prueba funcional del bloque D');
  expect((await copia(manual)).estado).toBe('Solicitada');
  const salida = spark('copias:procesar');
  console.log(salida);
  const c = await copia(manual);
  expect(c.estado, c.detalle || '').toBe('Verificada');
  expect(c.hash_verificacion).toMatch(/^[0-9a-f]{64}$/);
  expect(c.detalle).toMatch(/Incluye \d+ archivo\(s\) del legajo\./);
  const archivos = archivosDelVolumen();
  expect(archivos).toContain(c.archivo);
  expect(archivos).toContain(`${c.archivo}.json`);
  // No quedan copias sin cifrar en el volumen
  expect(archivos.filter((a) => /\.(bak|zip)$/.test(a))).toEqual([]);
  await page.goto(`/admin/copias/${manual}`);
  await expect(page.locator('main')).toContainText(c.hash_verificacion);
});

test('I-83 Otra copia en curso (A1)', async ({ comoUsuario }) => {
  const page = await comoUsuario('admin');
  const id = await solicitarCopia(page, 'Segunda copia');
  await page.goto('/admin/copias');
  await expect(page.getByRole('button', { name: 'Generar copia' })).toBeDisabled();
  await expect(page.locator('main')).toContainText('(A1)');
  spark('copias:procesar');
  expect((await copia(id)).estado).toBe('Verificada');
});

let automatica;

test('I-84 Copia automática (A5) y conservación de 30 días (RNF-13)', async () => {
  const salida = spark('copias:generar');
  console.log(salida);
  automatica = await ultimaCopia();
  const c = await copia(automatica);
  expect(c).toMatchObject({ estado: 'Verificada', origen: 'Automatica', responsable: 'Proceso copias:generar' });
  // La copia manual de I-82 pasa a tener 40 días: la siguiente ejecución la depura y borra su archivo
  await consulta('UPDATE dbo.copias_seguridad SET fecha_generacion = DATEADD(day, -40, fecha_generacion) WHERE id = @id', { id: manual });
  expect(spark('copias:generar')).toContain('Copias depuradas: 1.');
  expect((await copia(manual)).estado).toBe('Depurada');
  expect(archivosDelVolumen()).not.toContain((await copia(manual)).archivo);
  automatica = await ultimaCopia();
});

test('I-85 Descargar y verificar (CU-62)', async ({ comoUsuario }) => {
  const page = await comoUsuario('admin');
  const c = await copia(automatica);
  expect(await descargar(page, automatica)).toBe(c.hash_verificacion);
  expect(await valor("SELECT COUNT(*) FROM dbo.registros_auditoria WHERE accion = 'copias.descargar' AND resultado = 'Exitoso'")).toBeGreaterThan(0);
});

test('I-86 Copia corrupta (A1)', async ({ comoUsuario }) => {
  const page = await comoUsuario('admin');
  // La segunda copia de I-83 se altera; la automática queda para restaurar
  const id = (await consulta("SELECT id FROM dbo.copias_seguridad WHERE descripcion = N'Segunda copia'"))[0].id;
  const c = await copia(id);
  enApp('sh', '-c', 'printf x >> "$1"', 'sh', `${DIR}/${c.archivo}`);
  await page.goto(`/admin/copias/${id}`);
  await page.getByRole('button', { name: 'Descargar copia' }).click();
  await expect(page.locator('.alert-danger')).toContainText('La copia está corrupta');
  expect((await copia(id)).estado).toBe('Corrupta');
  await page.goto(`/admin/copias/${id}`);
  await expect(page.getByRole('button', { name: 'Descargar copia' })).toHaveCount(0);
});

test('I-87 Confirmación de la restauración (A2)', async ({ comoUsuario }) => {
  const page = await comoUsuario('admin');
  await page.goto(`/admin/copias/${automatica}/restaurar`);
  await expect(page.locator('main')).toContainText('Se reemplazará la información actual');
  await page.locator('#confirmacion').fill('si');
  await page.getByRole('button', { name: 'Restaurar la copia' }).click();
  await expect(page.locator('#error-confirmacion')).toContainText('Para confirmar, escriba RESTAURAR');
  await expect(page).not.toHaveURL(/mantenimiento/);
});

test('I-88 e I-89 Restaurar la copia (CU-63)', async ({ comoUsuario, browser }) => {
  test.setTimeout(10 * 60_000);
  const page = await comoUsuario('admin');
  const restaurada = await copia(automatica);
  // Las descargas de I-85 e I-86 son posteriores a la copia: la restauración las descarta
  expect(await valor("SELECT COUNT(*) FROM dbo.registros_auditoria WHERE accion = 'copias.descargar'")).toBeGreaterThan(0);
  await page.goto(`/admin/copias/${automatica}/restaurar`);
  await page.locator('#confirmacion').fill('RESTAURAR');
  await page.getByRole('button', { name: 'Restaurar la copia' }).click();
  await expect(page).toHaveURL(/\/mantenimiento$/);
  await expect(page.locator('main h1')).toHaveText('Sistema en mantenimiento');
  // Paso 8: el panel y el portal responden el modo mantenimiento
  const otro = await browser.newPage();
  expect((await otro.goto('/admin/login')).status()).toBe(503);
  expect((await otro.goto('/portal/login')).status()).toBe(503);
  await otro.close();
  // La restauración pone la base fuera de línea: se cierra la conexión de las consultas de verificación
  await cerrarBase();
  const salida = spark('copias:procesar');
  console.log(salida);
  await page.goto('/mantenimiento');
  await expect(page.locator('main h1')).toHaveText('Restauración finalizada');
  await expect(page.locator('main')).toContainText(`desde ${restaurada.archivo}`);
  // Las sesiones se cerraron (también la del Administrador)
  await page.goto('/admin');
  await expect(page).toHaveURL(/\/admin\/login/);
  olvidarSesion(usuarios.admin);
  // La base volvió al estado de la copia: las descargas posteriores ya no están
  expect(await valor("SELECT COUNT(*) FROM dbo.registros_auditoria WHERE accion = 'copias.descargar'")).toBe(0);
  const [r] = await consulta('SELECT r.exitosa, r.responsable, c.archivo, p.origen AS origen_previa, p.estado AS estado_previa FROM dbo.restauraciones r JOIN dbo.copias_seguridad c ON c.id = r.copia_seguridad_id LEFT JOIN dbo.copias_seguridad p ON p.id = r.copia_previa_id');
  expect(r).toMatchObject({ exitosa: true, archivo: restaurada.archivo, origen_previa: 'PreviaRestauracion', estado_previa: 'Verificada' });
  // Catálogo rehecho: la copia restaurada figura verificada aunque en la base copiada estaba en curso
  expect(await valor('SELECT estado FROM dbo.copias_seguridad WHERE archivo = @a', { a: restaurada.archivo })).toBe('Verificada');
  expect(await valor("SELECT COUNT(*) FROM dbo.registros_auditoria WHERE accion = 'copias.restaurar' AND resultado = 'Exitoso'")).toBe(1);
  // Registro técnico fuera de la base (RN-45)
  expect(enApp('tail', '-n', '1', 'writable/logs/restauraciones.log')).toContain(restaurada.archivo);
});

test('I-89 Resultado para el Administrador', async ({ comoUsuario }) => {
  const page = await comoUsuario('admin');
  await page.goto('/admin/copias');
  await expect(page.locator('main')).toContainText('Última restauración');
  await expect(page.locator('main')).toContainText('Operaciones posteriores a la copia que deben reconstruirse');
  expect(await valor("SELECT COUNT(*) FROM dbo.alertas WHERE objeto_tipo = 'copias_seguridad' AND rol_destino = 'Administrador'")).toBeGreaterThan(0);
});

test('I-91 Descifrar una copia fuera del sistema', async () => {
  const c = await copia(automatica);
  const salida = enApp('php', 'spark', 'copias:descifrar', `${DIR}/${c.archivo}`, 'writable/copia_descifrada.zip');
  expect(salida).toContain(c.hash_verificacion);
  expect(salida).toContain('Copia descifrada');
  expect(enApp('sh', '-c', 'unzip -l writable/copia_descifrada.zip 2>/dev/null || php -r \'$z = new ZipArchive(); $z->open("writable/copia_descifrada.zip"); echo $z->getNameIndex(0);\'')).toContain('sigccr.bak');
  enApp('rm', '-f', 'writable/copia_descifrada.zip');
});
