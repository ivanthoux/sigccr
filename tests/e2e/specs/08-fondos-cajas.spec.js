// Parte 2, sección 8. Fondos y cajas (CU-85, CU-80, CU-72, CU-31 y CU-81): R-046 a R-052.
// Las cajas las registra, asigna e inhabilita el Gerente (matriz de permisos 3.6.2).
const { test, expect } = require('../lib/fixtures');
const { usuarios, fecha, ddmmaaaa } = require('../lib/datos');
const { valor, cerrarBase } = require('../lib/sistema');

const exito = (page) => page.locator('.alert-success');
const libre = (page) => page.locator('dt', { hasText: 'Disponible libre' }).locator('xpath=following-sibling::dd[1]');
const aNumero = (texto) => Number(texto.replace(/[^\d,-]/g, '').replace(',', '.'));
const idCaja = (codigo) => valor('SELECT id FROM dbo.cajas WHERE codigo = @c', { c: codigo });
const idCuenta = (u) => valor('SELECT id FROM dbo.cuentas_acceso WHERE correo = @c', { c: u.correo });

async function movimiento(page, { tipo = 'AporteCapital', caracter = 'Ingreso', programado = false, importe, dia = fecha(), concepto }) {
  await page.goto('/admin/fondos');
  await page.locator('#tipo').selectOption(tipo);
  await page.locator(`#caracter-${caracter}`).check();
  await page.locator('#programado').setChecked(programado);
  await page.locator('#importe').fill(importe);
  await page.locator('#fecha').fill(ddmmaaaa(dia));
  await page.locator('#concepto').fill(concepto);
  await page.getByRole('button', { name: 'Registrar movimiento' }).click();
}

/** Despliega el <details> que contiene el campo (las acciones de cada caja están plegadas). */
const desplegar = (campo) => campo.evaluate((el) => { const d = el.closest('details'); if (d) d.open = true; });

const filaMovimiento = (page, concepto) => page.locator('main table tbody tr', { hasText: concepto }).first();

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);

test('R-046 Ingreso de fondos', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  await page.goto('/admin/fondos');
  const antes = aNumero(await libre(page).innerText());
  await movimiento(page, { importe: '20000000', concepto: 'Aporte de capital para la prueba integral' });
  await expect(exito(page)).toContainText('Movimiento registrado.');
  expect(aNumero(await libre(page).innerText()) - antes).toBeCloseTo(20000000, 2);
});

test('R-047 Validaciones y movimiento programado', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  await movimiento(page, { importe: '0', concepto: 'Importe cero' });
  await expect(page.locator('#error-importe')).toBeVisible();
  await movimiento(page, { importe: '1000', dia: fecha(3), concepto: 'Realizado con fecha futura' });
  await expect(page.locator('#error-fecha')).toBeVisible();
  await movimiento(page, { caracter: 'Egreso', tipo: 'GastoOperativo', programado: true, importe: '1000', dia: fecha(-3), concepto: 'Programado con fecha pasada' });
  await expect(page.locator('#error-fecha')).toBeVisible();

  await movimiento(page, { caracter: 'Egreso', tipo: 'GastoOperativo', programado: true, importe: '50000', dia: fecha(7), concepto: 'Pago de servicios' });
  await expect(exito(page)).toBeVisible();
  const fila = filaMovimiento(page, 'Pago de servicios');
  await expect(fila).toContainText('Programado');
  await fila.locator('summary', { hasText: 'Anular' }).click();
  await fila.locator('input[name="motivo"]').fill('Se pagará por otro medio');
  await fila.getByRole('button', { name: 'Anular' }).click();
  await expect(exito(page)).toBeVisible();
  await expect(filaMovimiento(page, 'Pago de servicios')).not.toContainText('Programado');
});

test('R-048 Corrección de un movimiento', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  await page.goto('/admin/fondos');
  const inicial = aNumero(await libre(page).innerText());
  await movimiento(page, { caracter: 'Egreso', tipo: 'GastoOperativo', importe: '10000', concepto: 'Gastos bancarios' });
  await expect(exito(page)).toBeVisible();
  expect(inicial - aNumero(await libre(page).innerText())).toBeCloseTo(10000, 2);

  const fila = filaMovimiento(page, 'Gastos bancarios');
  await fila.locator('summary', { hasText: 'Corregir' }).click();
  await fila.locator('input[name="motivo"]').fill('Importe cargado por error');
  await fila.getByRole('button', { name: 'Registrar el movimiento inverso' }).click();
  await expect(exito(page)).toBeVisible();
  expect(aNumero(await libre(page).innerText())).toBeCloseTo(inicial, 2);
  // Ni el corregido ni la corrección se pueden volver a corregir
  await expect(page.locator('main table tbody tr', { hasText: 'Gastos bancarios' }).locator('summary', { hasText: 'Corregir' })).toHaveCount(0);
});

test('R-049 Registrar cajas', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  const registrar = async (codigo, denominacion, ubicacion) => {
    await page.goto('/admin/cajas');
    await desplegar(page.locator('#codigo'));
    await page.locator('#codigo').fill(codigo);
    await page.locator('#denominacion').fill(denominacion);
    await page.locator('#ubicacion').fill(ubicacion);
    await page.getByRole('button', { name: 'Registrar caja' }).click();
  };
  await registrar('CAJA-01', 'Caja principal', 'Planta baja');
  await expect(exito(page)).toContainText('Caja registrada y habilitada');
  await registrar('CAJA-02', 'Caja secundaria', 'Primer piso');
  await expect(exito(page)).toContainText('Caja registrada y habilitada');
  await registrar('caja 01!', 'Código inválido', 'Planta baja');
  await expect(page.locator('#error-codigo')).toBeVisible();
  await registrar('CAJA-01', 'Repetida', 'Planta baja');
  await expect(page.locator('#error-codigo')).toBeVisible();
  expect(await valor('SELECT COUNT(*) FROM dbo.cajas')).toBe(2);
});

async function asignar(page, caja, cajero) {
  const id = await idCaja(caja);
  await page.goto('/admin/cajas');
  await desplegar(page.locator(`#cajero-${id}`));
  await page.locator(`#cajero-${id}`).selectOption(String(await idCuenta(cajero)));
  await page.locator(`#desde-${id}`).fill(ddmmaaaa(fecha()));
  await page.locator(`#cajero-${id}`).locator('xpath=ancestor::form').getByRole('button', { name: 'Confirmar asignación' }).click();
}

test('R-050 Asignar las cajas', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  await asignar(page, 'CAJA-01', usuarios.cajero);
  await expect(exito(page)).toContainText('Caja asignada');
  // Con la asignación vigente, CAJA-01 ya no ofrece asignarla a otro Cajero (A1: finalizar la vigente primero)
  const id = await idCaja('CAJA-01');
  await expect(page.locator(`#cajero-${id}`)).toHaveCount(0);
  await asignar(page, 'CAJA-02', usuarios.cajero2);
  await expect(exito(page)).toContainText('Caja asignada');
});

test('R-051 Apertura sin efectivo (D-30)', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  await page.goto('/admin/caja');
  await expect(page.locator('main input[name="importe"]')).toHaveCount(0);
  await page.locator('main form button[type="submit"]').first().click();
  await expect(exito(page)).toContainText('Caja abierta: ya puede registrar cobros por transferencia, depósito o tarjeta.');
});

test('R-052 Inhabilitar y rehabilitar cajas', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  const cambiarEstado = async (caja, motivo) => {
    const id = await idCaja(caja);
    await page.goto('/admin/cajas');
    await desplegar(page.locator(`#motivo-estado-${id}`));
    await page.locator(`#motivo-estado-${id}`).fill(motivo);
    await page.locator(`#motivo-estado-${id}`).locator('xpath=ancestor::form').locator('button[type="submit"]').click();
  };
  await cambiarEstado('CAJA-01', 'Mantenimiento del puesto');
  await expect(page.locator('main')).toContainText('La caja tiene una jornada abierta');
  await cambiarEstado('CAJA-02', 'Mantenimiento del puesto');
  await expect(exito(page)).toContainText('Caja inhabilitada');
  await cambiarEstado('CAJA-02', 'Fin del mantenimiento');
  await expect(exito(page)).toContainText('Caja rehabilitada: ya se puede asignar.');
  await asignar(page, 'CAJA-02', usuarios.cajero2);
  await expect(exito(page)).toContainText('Caja asignada');
});
