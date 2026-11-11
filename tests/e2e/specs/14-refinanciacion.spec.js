// Parte 3, sección 19. Refinanciación y condonación (CU-39 a CU-44, CU-74 y CU-96): R-120 a R-128.
const { test, expect } = require('../lib/fixtures');
const { clientes, parametros, fecha } = require('../lib/datos');
const { consulta, valor, spark, cerrarBase } = require('../lib/sistema');
const k = require('../lib/cartera');

const { C01: ana, C02: bruno } = clientes;
const exito = (page) => page.locator('.alert-success');
const estadoPropuesta = (id) => valor('SELECT estado FROM dbo.propuestas_refinanciacion WHERE id = @id', { id });
let creditoB;
let propuesta;

/** CU-39: simula sobre los créditos del cliente; devuelve el id de la simulación o null. */
async function simular(page, cliente, creditoIds, tasa, cuotas) {
  await page.goto(`/admin/refinanciaciones/simular?cliente=${await k.idCliente(cliente)}`);
  for (const id of creditoIds) await page.locator(`input[name="creditos[]"][value="${id}"]`).check();
  await page.locator('#tasa').fill(String(tasa));
  await page.locator('#cuotas').fill(String(cuotas));
  await page.getByRole('button', { name: 'Simular' }).click();
  await page.waitForLoadState();
  const m = page.url().match(/refinanciaciones\/simulaciones\/(\d+)/);
  return m ? Number(m[1]) : null;
}

/** CU-40: registra la simulación como propuesta; devuelve el id de la propuesta (o null si se rechazó). */
async function registrarPropuesta(page, simulacionId) {
  await page.goto(`/admin/refinanciaciones/simulaciones/${simulacionId}`);
  await page.getByRole('button', { name: 'Registrar como propuesta' }).click();
  await page.waitForLoadState();
  const m = page.url().match(/refinanciaciones\/(\d+)$/);
  return m ? Number(m[1]) : null;
}

async function resolver(page, id, decision, fundamento) {
  await page.goto(`/admin/refinanciaciones/${id}/resolver`);
  await page.locator(`label[for="decision-${decision}"]`).click();
  await page.locator('#fundamento').fill(fundamento);
  await page.getByRole('button', { name: 'Registrar la resolución' }).click();
  await page.waitForLoadState();
}

async function cancelar(page, id, { motivo, dia = '', medio = '' }) {
  await page.goto(`/admin/refinanciaciones/${id}/cancelar`);
  await page.locator('#motivo').fill(motivo);
  if (await page.locator('#fecha_desistimiento').count()) await page.locator('#fecha_desistimiento').fill(dia);
  if (await page.locator('#medio_desistimiento').count()) await page.locator('#medio_desistimiento').fill(medio);
  await page.getByRole('button', { name: 'Cancelar la propuesta' }).click();
  await page.waitForLoadState();
}

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);

test('R-120 Créditos admitidos y límites (RN-70)', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  creditoB = await k.credito(bruno);
  expect(await simular(page, bruno, [creditoB.id], 20, 12)).toBeNull();
  await expect(page.locator('#error-tasa')).toContainText('30');
  expect(await simular(page, bruno, [creditoB.id], 90, 12)).toBeNull();
  await expect(page.locator('#error-tasa')).toBeVisible();
  expect(await simular(page, bruno, [creditoB.id], 60, 48)).toBeNull();
  await expect(page.locator('#error-cuotas')).toContainText('36');
});

let simulacion;

test('R-121 Simular la refinanciación (CU-39)', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const deuda = await valor('SELECT SUM(capital_pendiente) FROM dbo.cuotas WHERE credito_id = @c', { c: creditoB.id });
  simulacion = await simular(page, bruno, [creditoB.id], 60, 12);
  expect(simulacion).toBeTruthy();
  await expect(exito(page)).toContainText('No modifica la deuda ni genera una propuesta (RN-36)');
  expect(await valor('SELECT SUM(capital_pendiente) FROM dbo.cuotas WHERE credito_id = @c', { c: creditoB.id })).toBe(deuda);
});

test('R-122 Registrar la propuesta y duplicado (A3)', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  propuesta = await registrarPropuesta(page, simulacion);
  expect(propuesta).toBeTruthy();
  await expect(exito(page)).toContainText('registrada, pendiente de resolución gerencial');
  const otra = await simular(page, bruno, [creditoB.id], 60, 12);
  if (otra) {
    expect(await registrarPropuesta(page, otra)).toBeNull();
    await expect(page.locator('body')).toContainText('vigente');
  } else {
    await expect(page.locator('main')).toContainText('propuesta');
  }
});

test('R-123 Observar y nueva versión', async ({ comoUsuario }) => {
  const gerente = await comoUsuario('gerente');
  await resolver(gerente, propuesta, 'Observada', 'Revisar la cantidad de cuotas con el cliente');
  expect(await estadoPropuesta(propuesta)).toBe('Observada');
  const analista = await comoUsuario('analista');
  const sim = await simular(analista, bruno, [creditoB.id], 60, 12);
  propuesta = await registrarPropuesta(analista, sim);
  await expect(analista.locator('.alert-success')).toContainText('(versión 2) registrada');
});

test('R-124 Aprobar y plan definitivo con los punitorios posteriores', async ({ comoUsuario }) => {
  const gerente = await comoUsuario('gerente');
  await resolver(gerente, propuesta, 'Aprobada', 'Plan acorde a la capacidad de pago actual');
  await expect(gerente.locator('.alert-success')).toContainText('Propuesta aprobada');
  spark('cartera:punitorios');
  const analista = await comoUsuario('analista');
  await analista.goto(`/admin/refinanciaciones/${propuesta}/aplicar`);
  await expect(analista.getByRole('button', { name: 'Registrar la aceptación y aplicar' })).toBeEnabled();
});

test('R-125 Aplicar la refinanciación (CU-74)', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const fondos = await valor("SELECT COUNT(*) FROM dbo.movimientos_fondos");
  await page.goto(`/admin/refinanciaciones/${propuesta}/aplicar`);
  await page.locator('#fecha_constancia').fill(fecha());
  await page.locator('#medio').fill('Firma presencial');
  await page.getByRole('button', { name: 'Registrar la aceptación y aplicar' }).click();
  await expect(exito(page)).toContainText('Refinanciación aplicada: el crédito N.º');
  expect(await valor('SELECT estado FROM dbo.creditos WHERE id = @id', { id: creditoB.id })).toBe('Refinanciado');
  expect(await valor("SELECT COUNT(*) FROM dbo.cuotas WHERE credito_id = @id AND estado <> 'Sustituida'", { id: creditoB.id })).toBe(0);
  expect(await valor("SELECT COUNT(*) FROM dbo.movimientos_fondos")).toBe(fondos);
});

let creditoA;

test('R-126 Observación por un pago (RN-36)', async ({ comoUsuario }) => {
  const analista = await comoUsuario('analista');
  creditoA = await k.credito(ana);
  await k.atrasar(creditoA.id, 40);
  const p = await registrarPropuesta(analista, await simular(analista, ana, [creditoA.id], 60, 12));
  expect(p).toBeTruthy();
  const cajero = await comoUsuario('cajero');
  const pendiente = await valor("SELECT MIN(numero) FROM dbo.cuotas WHERE credito_id = @c AND estado IN ('Pendiente', 'Vencida')", { c: creditoA.id });
  await k.cobrarTotal(cajero, ana, creditoA.id, [pendiente], 'Transferencia', 'TRF-7001');
  expect(await estadoPropuesta(p)).toBe('Observada');
});

test('R-127 Rechazar, cancelar y vencer propuestas', async ({ comoUsuario }) => {
  const analista = await comoUsuario('analista');
  const gerente = await comoUsuario('gerente');
  const nueva = async () => registrarPropuesta(analista, await simular(analista, ana, [creditoA.id], 60, 12));

  const rechazada = await nueva();
  await resolver(gerente, rechazada, 'Rechazada', 'La propuesta no se ajusta a la política de la entidad');
  expect(await estadoPropuesta(rechazada)).toBe('Rechazada');

  const desistida = await nueva();
  await cancelar(analista, desistida, { motivo: 'El cliente decidió pagar la deuda con fondos propios' });
  expect(await estadoPropuesta(desistida)).not.toBe('Cancelada');
  await cancelar(analista, desistida, { motivo: 'El cliente decidió pagar la deuda con fondos propios', dia: fecha(), medio: 'Nota firmada' });
  await expect(analista.locator('.alert-success')).toContainText('desistimiento');
  expect(await estadoPropuesta(desistida)).toBe('Cancelada');

  const administrativa = await nueva();
  await cancelar(gerente, administrativa, { motivo: 'Propuesta registrada por error' });
  await expect(gerente.locator('.alert-success')).toContainText('administrativa');

  const vencida = await nueva();
  await consulta('UPDATE dbo.propuestas_refinanciacion SET vence = DATEADD(minute, -1, SYSDATETIMEOFFSET()) WHERE id = @id', { id: vencida });
  await resolver(gerente, vencida, 'Aprobada', 'Plan acorde a la capacidad de pago actual');
  expect(await estadoPropuesta(vencida)).not.toBe('AprobadaPendienteAceptacion');
});

async function limiteCondonacion(page, limite) {
  await page.goto('/admin/parametros/morosidad');
  for (const [campo, v] of Object.entries({ ...parametros.morosidad, limite_condonacion: limite })) await page.locator(`#${campo}`).fill(v);
  await page.locator('#motivo').fill(parametros.motivo);
  await page.getByRole('button', { name: 'Registrar nueva versión' }).click();
  await expect(exito(page)).toBeVisible();
}

test('R-128 Condonación de punitorios (CU-44)', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  await limiteCondonacion(page, '50');
  const condonar = async (importe, fundamento) => {
    await page.goto(`/admin/punitorios/condonar?cliente=${await k.idCliente(ana)}`);
    await page.locator('input[name="cuotas[]"]').first().check();
    await page.locator('#importe').fill(importe);
    await page.locator('#fundamento').fill(fundamento);
    await page.getByRole('button', { name: 'Registrar la condonación' }).click();
    await page.waitForLoadState();
  };
  await condonar('999999', 'Bonificación por regularización voluntaria de la deuda');
  await expect(page.locator('main')).toContainText('supera el máximo condonable');
  await condonar('1', '');
  await expect(page.locator('#error-fundamento')).toBeVisible();
  await condonar('1', 'Bonificación por regularización voluntaria de la deuda');
  await expect(exito(page)).toContainText('Condonación registrada por');
  await limiteCondonacion(page, '100');
});
