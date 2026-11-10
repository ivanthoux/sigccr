// Parte 1, sección 3. Clientes (CU-11 a CU-15, CU-54, RN-80 y D-26): R-015 a R-026.
const { test, expect } = require('../lib/fixtures');
const { clientes, empresas } = require('../lib/datos');
const { registrarCliente, documentosDe } = require('../lib/clientes');
const { documento, grande, noAdmitido } = require('../lib/archivos');
const { valor, cerrarBase } = require('../lib/sistema');

const { C01: ana, C02: bruno, C12: marcos, C14: sofia } = clientes;
const pyme = empresas.P01;
const exito = (page) => page.locator('.alert-success');
const cantidadClientes = () => valor('SELECT COUNT(*) FROM dbo.clientes');
const idCliente = (correo) => valor('SELECT id FROM dbo.clientes WHERE correo = @c', { c: correo });
const error = (page, campo) => page.locator(`#error-${campo}`);

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);

test('R-015 Registrar persona humana', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  await registrarCliente(page, ana);
  await expect(exito(page)).toContainText('Cliente registrado.');
  await expect(page.locator('main')).toContainText('Gómez');
  expect(await valor('SELECT ca.estado FROM dbo.cuentas_acceso ca WHERE ca.correo = @c', { c: ana.correo })).toBe('PendienteActivacion');
});

test('R-016 Validaciones del alta', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const antes = await cantidadClientes();
  const valida = { nacimiento: '1990-05-15' };
  const casos = [
    [{ cambios: {} }, 'fecha_nacimiento'],
    [{ cambios: { ...valida, cuil: '27480000001' } }, 'cuit_cuil'],
    [{ cambios: valida, sinPolitica: true }, 'acepta_politica'],
    [{ cambios: valida, documentos: {} }, 'doc_DOC_IDENTIDAD'],
    [{ cambios: valida, documentos: { DOC_IDENTIDAD: [grande()] } }, 'doc_DOC_IDENTIDAD'],
    [{ cambios: valida, documentos: { DOC_IDENTIDAD: [noAdmitido()] } }, 'doc_DOC_IDENTIDAD'],
    [{ cambios: { ...valida, dni: ana.dni, cuil: ana.cuil } }, 'nro_documento'],
    [{ cambios: { ...valida, correo: ana.correo } }, 'correo'],
  ];
  for (const [opciones, campo] of casos) {
    await registrarCliente(page, sofia, opciones);
    await expect(error(page, campo), `error en ${campo} con ${JSON.stringify(opciones.cambios)}`).toBeVisible();
  }
  expect(await cantidadClientes()).toBe(antes);
});

test('R-017 CUIL según el prefijo', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  await registrarCliente(page, bruno, { cambios: { cuil: '27312223350' } });
  await expect(error(page, 'cuit_cuil')).toContainText('dígito');
  await registrarCliente(page, bruno, { cambios: { cuil: '30312223350' } });
  await expect(error(page, 'cuit_cuil')).toBeVisible();
});

test('R-018 Identificación UIF (RN-80)', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  await registrarCliente(page, bruno, { cambios: { nacionalidad: '', ocupacion: '', pep: null } });
  await expect(error(page, 'persona_expuesta')).toContainText('Registre la declaración jurada sobre la condición de persona expuesta políticamente (RN-80).');
  await expect(error(page, 'nacionalidad')).toBeVisible();
  await expect(error(page, 'ocupacion')).toBeVisible();
  await registrarCliente(page, bruno);
  await expect(exito(page)).toContainText('Cliente registrado.');
});

test('R-019 Resto de los clientes y alerta por persona expuesta', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  for (const codigo of ['C03', 'C04', 'C05', 'C06', 'C07', 'C08', 'C09', 'C10', 'C11', 'C13']) {
    await registrarCliente(page, clientes[codigo]);
    await expect(exito(page), `alta de ${codigo}`).toContainText('Cliente registrado.');
  }
  const alertas = await valor("SELECT COUNT(*) FROM dbo.alertas WHERE rol_destino = 'Gerente' AND causa LIKE '%Kowalski%persona expuesta%'");
  expect(alertas).toBe(1);
  const gerente = await comoUsuario('gerente');
  await gerente.goto('/admin/alertas');
  await expect(gerente.locator('main')).toContainText('Kowalski');
});

test('R-020 Monotributista', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  await registrarCliente(page, marcos, { cambios: { monotributo: '' } });
  await expect(error(page, 'categoria_monotributo')).toContainText('Seleccione la categoría del monotributo.');
  await registrarCliente(page, marcos, { cambios: { monotributo: 'A' } });
  await expect(error(page, 'categoria_monotributo')).toContainText('superan el tope de la categoría A');
  await registrarCliente(page, marcos);
  await expect(exito(page)).toContainText('Cliente registrado.');
});

test('R-021 Persona jurídica', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  await registrarCliente(page, pyme);
  await expect(exito(page)).toContainText('Cliente registrado.');
  await expect(page.locator('main')).toContainText(pyme.razonSocial);
  const docs = await valor('SELECT COUNT(*) FROM dbo.documentos_legajo WHERE cliente_id = @id', { id: await idCliente(pyme.correo) });
  expect(docs).toBe(Object.values(documentosDe(pyme)).flat().length);
});

test('R-022 Legajo: incorporar y descargar', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const id = await idCliente(ana.correo);
  await page.goto(`/admin/clientes/${id}`);
  await page.locator('#archivo-nuevo').setInputFiles(documento('otro_documento_prueba.pdf'));
  await page.getByRole('button', { name: 'Incorporar' }).click();
  await expect(exito(page)).toContainText('Documento incorporado al legajo.');
  const fila = page.locator('table tr', { hasText: 'otro_documento_prueba.pdf' });
  await expect(fila).toHaveCount(1);
  const [descarga] = await Promise.all([page.waitForEvent('download'), fila.getByRole('link').click()]);
  expect(descarga.suggestedFilename()).toBe('otro_documento_prueba.pdf');
});

test('R-023 Localizar clientes', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const filas = page.locator('table tbody tr');
  await page.goto('/admin/clientes');
  await expect(page.locator('table')).toHaveCount(0);
  await page.goto('/admin/clientes?texto=Góm');
  await expect(filas).toHaveCount(1);
  await expect(filas.first()).toContainText('Gómez');
  await page.goto(`/admin/clientes?documento=${bruno.dni}`);
  await expect(filas).toHaveCount(1);
  await page.goto(`/admin/clientes?cuit=${pyme.cuit}`);
  await expect(filas).toHaveCount(1);
  await expect(filas.first()).toContainText(pyme.razonSocial);
  await page.goto('/admin/clientes?tipo=PersonaJuridica');
  await expect(filas).toHaveCount(1);
  await page.goto('/admin/clientes?estado=Activo&tipo=PersonaHumana');
  await expect(filas).toHaveCount(13);
});

test('R-024 Modificar y cambiar el correo (RN-58)', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const id = await idCliente(ana.correo);
  const guardar = () => page.getByRole('button', { name: 'Guardar cambios' }).click();

  await page.goto(`/admin/clientes/${id}/editar`);
  await page.locator('#domicilio').fill('Av. San Martín 150');
  await page.locator('#telefono').fill('3758 41-2001');
  await guardar();
  await expect(exito(page)).toContainText('Datos actualizados.');

  await page.goto(`/admin/clientes/${id}/editar`);
  await page.locator('#correo').fill('ana.gomez2@prueba.local');
  await guardar();
  await expect(error(page, 'evidencia_identidad')).toBeVisible();

  await page.goto(`/admin/clientes/${id}/editar`);
  await page.locator('#correo').fill('ana.gomez2@prueba.local');
  await page.locator('#evidencia_identidad').fill('DNI exhibido en ventanilla el día de hoy');
  await guardar();
  await expect(exito(page)).toContainText('Datos actualizados.');
  await expect(page.locator('main')).toContainText('Correo nuevo pendiente de verificación: ana.gomez2@prueba.local');
  expect(await idCliente(ana.correo)).toBe(id);
});

test('R-025 Historial y comunicaciones comerciales', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const id = await idCliente(ana.correo);
  await page.goto(`/admin/clientes/${id}/historial`);
  await expect(page.locator('main')).toContainText('no tiene antecedentes crediticios en la entidad');
  await page.goto(`/admin/clientes/${id}/editar`);
  await page.locator('#acepta_comunicaciones').uncheck();
  await page.getByRole('button', { name: 'Guardar cambios' }).click();
  await expect(exito(page)).toBeVisible();
  await expect(page.locator('main')).toContainText('Comunicaciones comerciales: no acepta');
});

test('R-026 Desactivar y reactivar un cliente', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const id = await idCliente(pyme.correo);
  const operar = async (operacion, motivo) => {
    await page.goto(`/admin/clientes/${id}/${operacion}`);
    await page.locator('#motivo').fill(motivo);
    await page.locator('main form button[type="submit"]').click();
  };
  await operar('desactivar', 'Pedido de baja de la empresa');
  await expect(exito(page)).toContainText('Cliente desactivado.');
  await operar('reactivar', 'La empresa retoma la relación comercial');
  await expect(exito(page)).toContainText('Cliente reactivado. Para volver a usar el portal debe activar o rehabilitar su acceso (CU-04).');
});
