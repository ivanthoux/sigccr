// Parte 3, secciones 17 y 18. Pagos electrónicos, saldos a favor y devoluciones (CU-34, CU-38, CU-90, CU-91, CU-76 a
// CU-78): R-105 a R-119.
const { test, expect } = require('../lib/fixtures');
const { clientes, cbuInvalido, cvu, fecha } = require('../lib/datos');
const { consulta, valor, spark, cerrarBase } = require('../lib/sistema');
const k = require('../lib/cartera');

const { C01: ana, C02: bruno, C03: carla } = clientes;
const exito = (page) => page.locator('.alert-success[role="status"]');
const estadoOrden = (id) => valor('SELECT estado FROM dbo.ordenes_pago_electronico WHERE id = @id', { id });
const saldoDisponible = (cliente) => valor(
  'SELECT ISNULL(SUM(s.importe_disponible), 0) FROM dbo.saldos_a_favor s JOIN dbo.clientes c ON c.id = s.cliente_id WHERE c.cuit_cuil = @c',
  { c: cliente.cuil },
);
let creditoC;

/** CU-34: genera la orden de las cuotas y devuelve { id, url }. */
async function generarOrden(page, cliente, creditoId, cuotas) {
  await page.goto(`/admin/pagos-electronicos/nueva?cliente=${await k.idCliente(cliente)}`);
  for (const n of cuotas) await page.locator(`input[name="cuotas[${creditoId}][]"][value="${n}"]`).check();
  await page.getByRole('button', { name: 'Generar la orden' }).click();
  await page.waitForURL(/pagos-electronicos\/\d+/);
  const id = Number(page.url().match(/pagos-electronicos\/(\d+)/)[1]);
  return { id, url: await valor('SELECT url_pago FROM dbo.ordenes_pago_electronico WHERE id = @id', { id }) };
}

/** Simulador de la pasarela: paga la orden con el resultado indicado. */
async function pagar(page, url, { estado = 'approved', importe = '', notificar = true, firmaInvalida = false, actualizar = '' } = {}) {
  await page.goto(url.replace(/^https?:\/\/[^/]+/, ''));
  await page.locator('#estado').selectOption(estado);
  await page.locator('#importe').fill(String(importe));
  if (actualizar) await page.locator('#id_externo').selectOption({ index: 1 });
  await page.locator('#notificar').setChecked(notificar);
  await page.locator('#firma_invalida').setChecked(firmaInvalida);
  await page.getByRole('button', { name: 'Simular el pago' }).click();
  await page.waitForLoadState();
}

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);

let orden1;

test('R-105 Generar la orden de pago (CU-34)', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  creditoC = await k.credito(carla);
  orden1 = await generarOrden(page, carla, creditoC.id, [1]);
  await expect(exito(page)).toContainText('generada por');
  await expect(exito(page)).toContainText('vence a las 00:00');
});

test('R-106 Cuota en otra orden y aviso en ventanilla (A7)', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  await page.goto(`/admin/pagos-electronicos/nueva?cliente=${await k.idCliente(carla)}`);
  await expect(page.locator(`input[name="cuotas[${creditoC.id}][]"][value="1"]`)).toBeDisabled();
  await page.goto(`/admin/cobros?cliente=${await k.idCliente(carla)}`);
  await expect(page.locator('main')).toContainText('Pago electrónico pendiente');
  await expect(page.locator('main')).toContainText('Orden N.º');
});

test('R-107 Pago aprobado y notificación repetida (RN-30)', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  await pagar(page, orden1.url);
  spark('pagos:notificaciones');
  expect(await estadoOrden(orden1.id)).toBe('Confirmada');
  expect((await k.cuota(creditoC.id, 1)).estado).toBe('Pagada');
  const cobros = await valor('SELECT COUNT(*) FROM dbo.cobros WHERE estado = \'Confirmado\'');
  await pagar(page, orden1.url, { actualizar: 'anterior' });
  spark('pagos:notificaciones');
  expect(await valor('SELECT COUNT(*) FROM dbo.cobros WHERE estado = \'Confirmado\'')).toBe(cobros);
});

test('R-108 Rechazado y en proceso', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  const rechazada = await generarOrden(page, carla, creditoC.id, [2]);
  await pagar(page, rechazada.url, { estado: 'rejected' });
  spark('pagos:notificaciones');
  expect(await estadoOrden(rechazada.id)).toBe('Rechazada');
  const proceso = await generarOrden(page, carla, creditoC.id, [2]);
  await pagar(page, proceso.url, { estado: 'in_process' });
  spark('pagos:notificaciones');
  expect(await estadoOrden(proceso.id)).toBe('EnConciliacion');
  await pagar(page, proceso.url, { estado: 'approved', actualizar: 'anterior' });
  spark('pagos:notificaciones');
  expect(await estadoOrden(proceso.id)).toBe('Confirmada');
});

let saldoCarla;

test('R-109 Importe mayor: saldo a favor (RN-57)', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  const orden = await generarOrden(page, carla, creditoC.id, [3]);
  const importe = Number(await valor('SELECT importe FROM dbo.ordenes_pago_electronico WHERE id = @id', { id: orden.id }));
  const antes = Number(await saldoDisponible(carla));
  await pagar(page, orden.url, { importe: (importe + 5000).toFixed(2) });
  spark('pagos:notificaciones');
  expect((await k.cuota(creditoC.id, 3)).estado).toBe('Pagada');
  expect(Number(await saldoDisponible(carla)) - antes).toBeCloseTo(5000, 2);
  saldoCarla = orden.id;
});

test('R-110 Firma no válida y conciliación sin notificación (CU-91)', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  const orden = await generarOrden(page, carla, creditoC.id, [4]);
  await pagar(page, orden.url, { firmaInvalida: true });
  await expect(page.locator('body')).toContainText('firma no válida');
  spark('pagos:notificaciones');
  expect(await estadoOrden(orden.id)).toBe('PendienteConfirmacion');
  // El pago existe en la pasarela aunque su aviso se descartó: la conciliación lo encuentra
  spark('pagos:conciliar');
  expect(await estadoOrden(orden.id)).toBe('Confirmada');
});

test('R-111 Cuotas ya pagadas: todo a saldo a favor (A6)', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  const orden = await generarOrden(page, carla, creditoC.id, [5]);
  await k.cobrarTotal(page, carla, creditoC.id, [5], 'Transferencia', 'TRF-5005');
  await expect(exito(page)).toContainText('registrado');
  const antes = Number(await saldoDisponible(carla));
  await pagar(page, orden.url);
  spark('pagos:notificaciones');
  const importe = Number(await valor('SELECT importe FROM dbo.ordenes_pago_electronico WHERE id = @id', { id: orden.id }));
  expect(Number(await saldoDisponible(carla)) - antes).toBeCloseTo(importe, 2);
});

test('R-112 Orden vencida (CU-89)', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  const orden = await generarOrden(page, carla, creditoC.id, [6]);
  await consulta('UPDATE dbo.ordenes_pago_electronico SET emision = DATEADD(hour, -2, SYSDATETIMEOFFSET()), vence = DATEADD(minute, -1, SYSDATETIMEOFFSET()) WHERE id = @id', { id: orden.id });
  spark('vencimientos:controlar');
  expect(await estadoOrden(orden.id)).toBe('Vencida');
  await page.goto(`/admin/pagos-electronicos/nueva?cliente=${await k.idCliente(carla)}`);
  await expect(page.locator(`input[name="cuotas[${creditoC.id}][]"][value="6"]`)).toBeEnabled();
});

test('R-113 Consultar órdenes (CU-38)', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  await page.goto('/admin/pagos-electronicos?estado=Confirmada');
  await expect(page.locator('main table tbody tr').first()).toBeVisible();
  await page.goto(`/admin/pagos-electronicos/${saldoCarla}`);
  await page.locator('form[action$="/consultar"] button[type="submit"]').click();
  await expect(exito(page)).toContainText('Pasarela consultada');
  await expect(page.locator('main')).toContainText('Importe distinto');
});

test('R-114 Aplicar un saldo a favor (CU-76)', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  const creditoA = await k.credito(ana);
  const aplicar = async ({ medio = 'Presencial, con firma del cliente', dia = fecha() } = {}) => {
    await page.goto(`/admin/saldos-favor/aplicar?cliente=${await k.idCliente(ana)}`);
    await page.locator(`input[name="cuotas[${creditoA.id}][]"][value="2"]`).check();
    await page.locator('#fecha_conformidad').fill(dia);
    await page.locator('#medio_conformidad').fill(medio);
    await page.locator('#observaciones').fill('Aplica el saldo a la cuota 2');
    await page.getByRole('button', { name: 'Aplicar el saldo' }).click();
  };
  await aplicar({ medio: '' });
  await expect(page.locator('#error-medio_conformidad')).toBeVisible();
  await aplicar({ dia: fecha(2) });
  await expect(page.locator('#error-fecha_conformidad')).toBeVisible();
  await aplicar();
  await expect(exito(page)).toContainText('del saldo a favor');
});

/** Completa el campo solo si la pantalla lo muestra (los datos de la cuenta dependen del destino). */
async function completarSiVisible(campo, texto) {
  if (await campo.isVisible()) await campo.fill(texto);
}

/** CU-77: autoriza una devolución del saldo de Carla; devuelve el id de la autorización (o null). */
async function autorizar(page, { destino = 'MedioOriginal', importe = '5000', cbu = '', cuit = '', entidad = '', evidencia = '' } = {}) {
  await page.goto(`/admin/saldos-favor/devoluciones?cliente=${await k.idCliente(carla)}`);
  const form = page.locator('form', { has: page.locator('input[name="saldo_id"]') }).filter({ has: page.locator('select[name="destino"]') }).last();
  await form.locator('select[name="destino"]').selectOption(destino);
  await form.locator('input[name="importe"]').fill(importe);
  await completarSiVisible(form.locator('input[name="cbu"]'), cbu);
  await completarSiVisible(form.locator('input[name="cuit_titular"]'), cuit);
  await completarSiVisible(form.locator('input[name="entidad"]'), entidad);
  await completarSiVisible(form.locator('input[name="evidencia"]'), evidencia);
  await form.locator('textarea[name="motivo"], input[name="motivo"]').fill('El cliente solicita el reintegro del excedente del pago electrónico');
  await form.locator('input[name="fecha_solicitud"]').fill(fecha());
  await form.locator('input[name="medio_solicitud"]').fill('Nota firmada en ventanilla');
  await form.getByRole('button', { name: 'Autorizar la devolución' }).click();
  await page.waitForLoadState();
  const m = page.url().match(/autorizaciones\/(\d+)/);
  return m ? Number(m[1]) : null;
}

async function modoPasarela(page, modo) {
  await page.goto('/simulador/devoluciones');
  await page.locator(`#modo-${modo}`).check();
  await page.getByRole('button', { name: 'Guardar' }).click();
}

async function ejecutar(page, id, datos = {}) {
  await page.goto(`/admin/devoluciones/${id}`);
  for (const [campo, v] of Object.entries(datos)) await page.locator(`#${campo}`).fill(v);
  await page.getByRole('button', { name: 'Ejecutar la devolución' }).click();
  await page.waitForLoadState();
}

test('R-115 Destinos no admitidos (RN-73)', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  const transferencia = { destino: 'Transferencia', entidad: 'Banco Credicoop', evidencia: 'Constancia de CBU emitida por el banco' };
  for (const datos of [
    { ...transferencia, cbu: cvu, cuit: carla.cuil },
    { ...transferencia, cbu: cbuInvalido, cuit: carla.cuil },
    { ...transferencia, cbu: '0150500977000000223346', cuit: bruno.cuil },
    { importe: '999999' },
  ]) {
    expect(await autorizar(page, datos), JSON.stringify(datos)).toBeNull();
    await expect(page.locator('.invalid-feedback, .alert-danger').first()).toBeVisible();
  }
});

let devolucion;

test('R-116 Autorizar por el medio original (CU-77)', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  devolucion = await autorizar(page);
  expect(devolucion).toBeTruthy();
  await expect(exito(page)).toContainText('autorizada por');
});

test('R-117 Ejecutar por la pasarela (CU-78)', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  await modoPasarela(page, 'approved');
  await ejecutar(page, devolucion);
  await expect(exito(page)).toContainText('Devolución completada');
});

test('R-118 Rechazo y respuesta incierta', async ({ comoUsuario }) => {
  const gerente = await comoUsuario('gerente');
  const cajero = await comoUsuario('cajero');
  const rechazada = await autorizar(gerente, { importe: '1000' });
  await modoPasarela(cajero, 'rejected');
  await ejecutar(cajero, rechazada);
  expect(await valor('SELECT estado FROM dbo.autorizaciones_devolucion WHERE id = @id', { id: rechazada })).toBe('Fallida');
  const incierta = await autorizar(gerente, { importe: '1000' });
  await modoPasarela(cajero, 'timeout');
  await ejecutar(cajero, incierta);
  expect(await valor('SELECT estado FROM dbo.autorizaciones_devolucion WHERE id = @id', { id: incierta })).toBe('EnConciliacion');
  await cajero.goto('/simulador/devoluciones');
  await cajero.getByRole('button', { name: 'Aprobar' }).first().click();
  spark('pagos:conciliar');
  expect(await valor('SELECT estado FROM dbo.autorizaciones_devolucion WHERE id = @id', { id: incierta })).toBe('Completada');
  await modoPasarela(cajero, 'approved');
});

test('R-119 Devolución por transferencia', async ({ comoUsuario }) => {
  const gerente = await comoUsuario('gerente');
  const cajero = await comoUsuario('cajero');
  const id = await autorizar(gerente, {
    destino: 'Transferencia', importe: '2000', cbu: '0150500977000000223346', cuit: carla.cuil, entidad: 'Banco Credicoop', evidencia: 'Constancia de CBU emitida por el banco',
  });
  expect(id).toBeTruthy();
  const datos = { numero_operacion: 'DEV-TRF-01', fecha: fecha(), cbu: '0150500977000000223346' };
  await ejecutar(cajero, id, { ...datos, importe: '1500' });
  expect(await valor('SELECT estado FROM dbo.autorizaciones_devolucion WHERE id = @id', { id })).not.toBe('Completada');
  await ejecutar(cajero, id, { ...datos, importe: '2000' });
  expect(await valor('SELECT estado FROM dbo.autorizaciones_devolucion WHERE id = @id', { id })).toBe('Completada');
});
