// Operaciones de la iteración 3 sobre créditos liquidados: cobro en ventanilla, paso del tiempo y consultas.
const { expect } = require('@playwright/test');
const { consulta, valor, spark } = require('./sistema');

const idCliente = (cliente) => valor('SELECT id FROM dbo.clientes WHERE cuit_cuil = @c', { c: cliente.cuil || cliente.cuit });

/** Crédito liquidado más antiguo del cliente (los de A.10), o el de origen indicado. */
async function credito(cliente, origen = null) {
  const filas = await consulta(
    'SELECT TOP 1 c.id, c.numero, c.estado FROM dbo.creditos c JOIN dbo.clientes cl ON cl.id = c.cliente_id '
      + 'WHERE cl.cuit_cuil = @c AND (@o IS NULL OR c.origen = @o) ORDER BY c.id',
    { c: cliente.cuil, o: origen },
  );
  return filas[0];
}

const cuota = async (creditoId, numero) => (await consulta('SELECT * FROM dbo.cuotas WHERE credito_id = @c AND numero = @n', { c: creditoId, n: numero }))[0];

/**
 * C.1: adelanta los vencimientos del crédito los días indicados y ejecuta el cálculo de punitorios, como si el tiempo
 * hubiera pasado (el proceso usa la fecha de hoy).
 */
async function atrasar(creditoId, dias) {
  await consulta('UPDATE dbo.cuotas SET vencimiento = DATEADD(day, -@d, vencimiento) WHERE credito_id = @c', { d: dias, c: creditoId });
  spark('cartera:punitorios');
}

/**
 * CU-33: abre el cobro del cliente, marca las cuotas y completa los medios. `medios` es una lista de
 * { medio, importe, operacion }; `importe` vacío cobra el total de las cuotas.
 */
async function cobrar(page, cliente, creditoId, cuotas, { importe = '', medios = [] } = {}) {
  await page.goto(`/admin/cobros?cliente=${await idCliente(cliente)}`);
  for (const n of cuotas) await page.locator(`input[name="cuotas[${creditoId}][]"][value="${n}"]`).check();
  await page.locator('#importe').fill(String(importe));
  for (let i = 0; i < 3; i++) {
    const m = medios[i] || { medio: 'Transferencia', importe: '', operacion: '' };
    await page.locator(`#medio-${i}`).selectOption(m.medio);
    await page.locator(`#medio-importe-${i}`).fill(String(m.importe ?? ''));
    await page.locator(`#medio-operacion-${i}`).fill(m.operacion || '');
  }
  await page.getByRole('button', { name: 'Registrar el cobro' }).click();
  await page.waitForLoadState();
}

/** Total exigible de las cuotas marcadas, tal como lo muestra la pantalla («$ 1.234,56» → «1.234,56»). */
async function totalSeleccionado(page) {
  return (await page.locator('[data-cobro-total]').innerText()).replace('$', '').trim();
}

/** Marca las cuotas, lee el total y cobra ese total con un solo medio. */
async function cobrarTotal(page, cliente, creditoId, cuotas, medio, operacion) {
  await page.goto(`/admin/cobros?cliente=${await idCliente(cliente)}`);
  for (const n of cuotas) await page.locator(`input[name="cuotas[${creditoId}][]"][value="${n}"]`).check();
  const total = await totalSeleccionado(page);
  await cobrar(page, cliente, creditoId, cuotas, { medios: [{ medio, importe: total, operacion }] });
  return total;
}

async function esperarExito(page, texto) {
  await expect(page.locator('.alert-success[role="status"]')).toContainText(texto);
}

module.exports = { idCliente, credito, cuota, atrasar, cobrar, cobrarTotal, totalSeleccionado, esperarExito };
