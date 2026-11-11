// Parte 2, secciones 9 y 10. Simulación, comparación, solicitud, verificación y corrección (CU-21 a CU-23, CU-84, CU-99):
// R-053 a R-063.
const { test, expect } = require('../lib/fixtures');
const { clientes } = require('../lib/datos');
const { documento } = require('../lib/archivos');
const { valor, cerrarBase } = require('../lib/sistema');
const f = require('../lib/flujo');

const { C01: ana, C02: bruno, C07: gabriela } = clientes;
const exito = (page) => page.locator('.alert-success[role="status"]');
const estado = (page) => page.locator('main');
const solicitudDe = (cliente) => valor(
  "SELECT TOP 1 s.id FROM dbo.solicitudes_credito s JOIN dbo.clientes c ON c.id = s.cliente_id WHERE c.cuit_cuil = @c AND s.estado NOT IN ('Cancelada', 'Rechazada') ORDER BY s.id DESC",
  { c: cliente.cuil },
);

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);

let simulacionAna;

test('R-053 Simular', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  simulacionAna = await f.simular(page, ana, 'MOTOS', 500000, 12);
  expect(simulacionAna).toBeTruthy();
  await expect(exito(page)).toContainText('Simulación registrada. Es preliminar');
  for (const texto of ['TEA', 'CFTEA']) await expect(page.locator('main')).toContainText(texto);
});

test('R-054 Validaciones de la simulación', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  for (const [importe, cuotas, campo] of [['', 12, 'importe'], ['abc', 12, 'importe'], ['500000', '6.5', 'cantidad_cuotas'], ['100000', 12, 'importe'], ['500000', 30, 'cantidad_cuotas']]) {
    expect(await f.simular(page, ana, 'MOTOS', importe, cuotas)).toBeNull();
    await expect(page.locator(`#error-${campo}`), `${importe} en ${cuotas}`).toBeVisible();
  }
});

test('R-055 Línea que no admite al cliente', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  await page.goto(`/admin/simulaciones?cliente=${await f.idCliente(ana)}&linea=${await f.idLinea('PYME-CT')}`);
  // La pantalla no ofrece simular en la línea: la lista con su motivo (RN-13)
  await expect(page.locator('#importe')).toHaveCount(0);
  await expect(page.locator('main')).toContainText('Capital de trabajo Pyme: La línea no admite personas humanas.');
});

test('R-056 Gasto administrativo porcentual', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  expect(await f.simular(page, bruno, 'PERSONAL', 800000, 12)).toBeTruthy();
  await expect(page.locator('main')).toContainText('4.000,00');
});

test('R-057 Comparar líneas (CU-99)', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  for (const criterio of ['Monto', 'Costo']) {
    await page.goto(`/admin/simulaciones/comparar?cliente=${await f.idCliente(ana)}`);
    await page.locator('#importe').fill('500000');
    await page.locator('#cantidad_cuotas').fill('12');
    await page.locator(`#criterio-${criterio}`).check();
    await page.getByRole('button', { name: 'Comparar líneas' }).click();
    await expect(page.locator('main')).toContainText('Motos');
    await expect(page.locator('main')).toContainText('Capital de trabajo Pyme');
  }
});

test('R-058 Registrar la solicitud', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  await f.registrarSolicitud(page, simulacionAna);
  await expect(exito(page)).toContainText('registrada: queda pendiente de verificación del legajo');
});

test('R-059 Solicitud repetida en la misma línea', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const sim = await f.simular(page, ana, 'MOTOS', 400000, 12);
  await page.goto(`/admin/solicitudes/nueva/${sim}`);
  await page.getByRole('button', { name: 'Registrar solicitud' }).click();
  await expect(estado(page)).toContainText('ya tiene la solicitud N.º');
});

test('R-060 Legajo observado', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const id = await solicitudDe(ana);
  const recibo = (observacion) => ({ Recibos: { resultado: 'Observado', motivo: 'Ilegible', observacion } });
  await f.verificar(page, id, { resultado: 'Observada', documentos: recibo('Ilegible'), observaciones: 'Ilegible' });
  expect(await f.estadoSolicitud(id)).toBe('PendienteVerificacion');
  const completa = 'El recibo de sueldo de septiembre está incompleto: falta la segunda hoja';
  await f.verificar(page, id, { resultado: 'Observada', documentos: recibo(completa), observaciones: completa });
  expect(await f.estadoSolicitud(id)).toBe('PendienteDocumentacion');
});

test('R-061 Corrección con el recibo nuevo (CU-84)', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const id = await solicitudDe(ana);
  await page.goto(`/admin/solicitudes/${id}/corregir`);
  await page.locator('#doc-RECIBO_SUELDO').setInputFiles(documento('recibos_sueldo_Gomez.pdf'));
  if (await page.locator('#aclaracion').count()) await page.locator('#aclaracion').fill('Se incorporan los recibos completos');
  await page.getByRole('button', { name: 'Registrar corrección' }).click();
  await expect(exito(page)).toContainText('Corrección registrada: la solicitud pasó a pendiente de verificación');
});

test('R-062 Verificar y evaluar', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const id = await solicitudDe(ana);
  await f.verificar(page, id);
  expect(['PendienteEvaluacion', 'EnEvaluacion', 'PendienteSeleccionPlan']).toContain(await f.estadoSolicitud(id));
  f.evaluar();
  expect(await f.estadoSolicitud(id)).toBe('PendienteSeleccionPlan');
});

test('R-063 Rechazo documental (RN-19)', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const id = await f.registrarSolicitud(page, await f.simular(page, gabriela, 'MOTOS', 500000, 12));
  await f.verificar(page, id, { resultado: 'Rechazada', documentos: { Recibos: { resultado: 'Rechazado', motivo: 'NoCorresponde' } } });
  await expect(page.locator('#error-motivo_rechazo')).toBeVisible();
  await f.verificar(page, id, { resultado: 'Rechazada', documentos: { Recibos: { resultado: 'Rechazado', motivo: 'NoCorresponde' } }, motivoRechazo: 'La documentación presentada no corresponde al titular de la solicitud' });
  expect(await f.estadoSolicitud(id)).toBe('Rechazada');
});
