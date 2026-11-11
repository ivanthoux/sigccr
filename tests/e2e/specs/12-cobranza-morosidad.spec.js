// Parte 3, secciones 15 y 16. Cobranza en ventanilla y morosidad (CU-33, CU-35, CU-32, CU-37, CU-60 y CU-88):
// R-092 a R-104.
const { test, expect } = require('../lib/fixtures');
const { clientes } = require('../lib/datos');
const { valor, spark, cerrarBase } = require('../lib/sistema');
const k = require('../lib/cartera');

const { C01: ana, C02: bruno } = clientes;
const exito = (page) => page.locator('.alert-success[role="status"]');
const motivoAnulacion = 'El cliente informó que la transferencia fue rechazada por su banco';
let creditoA;
let cobroR095;

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);

test('R-092 Deuda del cliente en ventanilla', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  creditoA = await k.credito(ana);
  await page.goto(`/admin/cobros?cliente=${await k.idCliente(ana)}`);
  await expect(page.locator('main')).toContainText(`Crédito N.º ${creditoA.numero}`);
  await expect(page.locator(`input[name="cuotas[${creditoA.id}][]"]`)).toHaveCount(12);
});

test('R-093 Sin caja abierta', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero2');
  await page.goto(`/admin/cobros?cliente=${await k.idCliente(ana)}`);
  await expect(page.locator('main')).toContainText('No tiene una caja abierta');
  await expect(page.getByRole('button', { name: 'Registrar el cobro' })).toBeDisabled();
});

test('R-094 Selección y medios inválidos (RN-29, RN-77)', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  const antes = await valor('SELECT COUNT(*) FROM dbo.cobros');
  const casos = [
    [[2], {}, 'comenzar por la cuota 1'],
    [[1], { importe: '99999999', medios: [{ medio: 'Transferencia', importe: '99999999', operacion: 'TRF-1001' }] }, 'no puede superar'],
    [[1], { importe: '0', medios: [{ medio: 'Transferencia', importe: '0', operacion: 'TRF-1001' }] }, 'mayor que cero'],
    [[1], { importe: '1000', medios: [{ medio: 'Transferencia', importe: '1000', operacion: '' }] }, 'número de operación'],
    [[1], { importe: '1000', medios: [{ medio: 'Transferencia', importe: '500', operacion: 'TRF-1001' }, { medio: 'Transferencia', importe: '500', operacion: 'TRF-1001' }] }, 'repetido'],
    [[1], { importe: '1000', medios: [{ medio: 'Transferencia', importe: '600', operacion: 'TRF-1001' }] }, 'importe'],
  ];
  for (const [cuotas, datos, texto] of casos) {
    await k.cobrar(page, ana, creditoA.id, cuotas, datos);
    await expect(page.locator('main .alert-danger, main .invalid-feedback, main .text-danger').first(), JSON.stringify(datos)).toContainText(texto);
  }
  expect(await valor('SELECT COUNT(*) FROM dbo.cobros')).toBe(antes);
});

test('R-095 Cobro total de la cuota 1', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  await k.cobrarTotal(page, ana, creditoA.id, [1], 'Transferencia', 'TRF-1001');
  await expect(exito(page)).toContainText('1 cuota(s) pagada(s)');
  await expect(page.locator('main')).toContainText('TRF-1001');
  cobroR095 = Number(page.url().match(/cobros\/(\d+)\/comprobante/)[1]);
  expect((await k.cuota(creditoA.id, 1)).estado).toBe('Pagada');
});

test('R-096 Cobro parcial con dos medios (D-12)', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  await k.cobrar(page, ana, creditoA.id, [2], {
    importe: '20000',
    medios: [{ medio: 'TarjetaDebito', importe: '12000', operacion: 'DEB-2001' }, { medio: 'Transferencia', importe: '8000', operacion: 'TRF-1002' }],
  });
  await expect(exito(page)).toContainText('la cuota 2 con saldo pendiente (D-12)');
  for (const op of ['DEB-2001', 'TRF-1002']) await expect(page.locator('main')).toContainText(op);
});

test('R-097 Comprobantes y movimientos de caja', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  await page.goto(`/admin/cobros/comprobantes?texto=${encodeURIComponent('Gómez')}`);
  await expect(page.locator('main table tbody tr').first()).toBeVisible();
  await page.goto(`/admin/cobros/${cobroR095}/comprobante`);
  await page.locator('form[action$="/reemitir"] button[type="submit"]').click();
  await expect(page.locator('main')).toContainText('reemisiones: 1');
  expect(await valor('SELECT cantidad_reemisiones FROM dbo.comprobantes_pago WHERE cobro_id = @id', { id: cobroR095 })).toBe(1);
  await page.goto('/admin/cajas/movimientos');
  await expect(page.locator('main')).toContainText('CAJA-01');
});

test('R-098 Anular con cobros dependientes (CU-37)', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  await page.goto(`/admin/cobros/${cobroR095}/anular`);
  await page.locator('#motivo').fill(motivoAnulacion);
  await page.getByRole('button', { name: 'Anular el cobro' }).click();
  await expect(page.locator('main')).toContainText('cobro(s) posteriores sobre las mismas cuotas');
  await page.locator('#continuar').check();
  await page.locator('#motivo').fill(motivoAnulacion);
  await page.getByRole('button', { name: 'Anular el cobro' }).click();
  await expect(exito(page)).toContainText('Cobro anulado: 1 cuota(s) recuperaron su saldo');
});

test('R-099 Efectos de la anulación y repetición (A1)', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  expect((await k.cuota(creditoA.id, 1)).estado).not.toBe('Pagada');
  expect(await valor('SELECT estado FROM dbo.cobros WHERE id = @id', { id: cobroR095 })).toBe('Anulado');
  await page.goto(`/admin/cobros/${cobroR095}/anular`);
  await expect(page.locator('main')).toContainText('ya fue anulado');
});

test('R-100 Volver a cobrar la cuota 1', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  await k.cobrarTotal(page, ana, creditoA.id, [1], 'TarjetaCredito', 'CRE-3001');
  await expect(exito(page)).toContainText('registrado');
  expect(await valor('SELECT SUM(importe_disponible) FROM dbo.saldos_a_favor s JOIN dbo.clientes c ON c.id = s.cliente_id WHERE c.cuit_cuil = @c', { c: ana.cuil })).toBeGreaterThan(0);
});

let creditoB;

test('R-101 Mora dentro de la tolerancia', async () => {
  creditoB = await k.credito(bruno);
  await k.atrasar(creditoB.id, 32);
  expect((await k.cuota(creditoB.id, 1)).estado).toBe('Vencida');
  expect(await valor('SELECT estado FROM dbo.creditos WHERE id = @id', { id: creditoB.id })).toBe('EnMora');
  expect(Number((await k.cuota(creditoB.id, 1)).punitorio_pendiente)).toBe(0);
});

test('R-102 Superada la tolerancia: punitorios exigibles (RN-34)', async () => {
  await k.atrasar(creditoB.id, 8);
  expect(Number((await k.cuota(creditoB.id, 1)).punitorio_pendiente)).toBeGreaterThan(0);
});

test('R-103 Proceso repetido sin cambios (A2)', async () => {
  const antes = await valor('SELECT COUNT(*) FROM dbo.tramos_punitorios t JOIN dbo.cuotas q ON q.id = t.cuota_id WHERE q.credito_id = @c', { c: creditoB.id });
  const importe = (await k.cuota(creditoB.id, 1)).punitorio_pendiente;
  spark('cartera:punitorios');
  expect(await valor('SELECT COUNT(*) FROM dbo.tramos_punitorios t JOIN dbo.cuotas q ON q.id = t.cuota_id WHERE q.credito_id = @c', { c: creditoB.id })).toBe(antes);
  expect((await k.cuota(creditoB.id, 1)).punitorio_pendiente).toBe(importe);
});

test('R-104 Atraso en el cobro de Bruno', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  await page.goto(`/admin/cobros?cliente=${await k.idCliente(bruno)}`);
  await expect(page.locator('main')).toContainText('días');
  await expect(page.locator('main')).toContainText('En mora');
});
