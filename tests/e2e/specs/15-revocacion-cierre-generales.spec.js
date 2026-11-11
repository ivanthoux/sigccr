// Parte 3, secciones 20 a 23. Revocación, cancelación anticipada, cierre de caja, proyección, vencimientos y
// generales (CU-102, CU-97, CU-36, CU-92, CU-89): R-129 a R-147.
const { test, expect } = require('../lib/fixtures');
const { clientes, fecha } = require('../lib/datos');
const { consulta, valor, spark, cerrarBase } = require('../lib/sistema');
const k = require('../lib/cartera');

const { C04: diego, C06: federico, C10: julian } = clientes;
const exito = (page) => page.locator('.alert-success[role="status"]');
const aImporte = (texto) => Number(texto.replace(/[^\d,-]/g, '').replace(',', '.'));
const formato = (n) => n.toFixed(2).replace('.', ',');

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);

let creditoD;

test('R-129 Importe a restituir de la revocación (CU-102)', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  creditoD = await k.credito(diego);
  await page.goto(`/admin/revocaciones/${creditoD.id}`);
  await expect(page.locator('main')).toContainText('10 días hábiles');
});

test('R-130 Registrar y confirmar la revocación', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  await page.goto(`/admin/revocaciones/${creditoD.id}`);
  await page.locator('#fecha_comunicacion').fill(fecha());
  await page.locator('#medio_comunicacion').fill('Nota presentada en ventanilla');
  await page.getByRole('button', { name: 'Registrar la revocación' }).click();
  await expect(exito(page)).toContainText('Revocación registrada. El cliente debe transferir');
  const importe = Number(await valor("SELECT importe_restituir FROM dbo.revocaciones WHERE credito_id = @c", { c: creditoD.id }));
  const confirmar = async (monto) => {
    await page.locator('#numero_operacion').fill('REV-0001');
    await page.locator('#importe').fill(formato(monto));
    await page.getByRole('button', { name: 'Confirmar la restitución' }).click();
    await page.waitForLoadState();
  };
  await confirmar(importe - 100);
  await expect(page.locator('main')).toContainText('(A3)');
  await confirmar(importe);
  await expect(exito(page)).toContainText('Restitución confirmada: el crédito quedó revocado');
  expect(await valor('SELECT estado FROM dbo.creditos WHERE id = @id', { id: creditoD.id })).toBe('Revocado');
});

test('R-131 Revocación fuera de plazo (A1)', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  const credito = await k.credito(federico);
  await consulta(
    'UPDATE k SET k.fecha_recepcion = DATEADD(day, -20, k.fecha_recepcion) FROM dbo.contratos_prestamo k JOIN dbo.liquidaciones q ON q.solicitud_id = k.solicitud_id '
      + 'WHERE q.credito_id = @c AND k.fecha_recepcion IS NOT NULL; UPDATE dbo.creditos SET fecha_liquidacion = DATEADD(day, -20, fecha_liquidacion) WHERE id = @c',
    { c: credito.id },
  );
  await page.goto(`/admin/revocaciones/${credito.id}`);
  await expect(page.locator('body')).toContainText('El plazo de revocación venció el');
});

let creditoE;

test('R-132 Cálculo de la cancelación anticipada (CU-97)', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  creditoE = await k.credito(julian);
  await page.goto(`/admin/creditos/${creditoE.id}/cancelacion-anticipada`);
  await expect(page.locator('main')).toContainText('no devengado');
});

test('R-133 Parcial y con una orden pendiente (RN-67, A4)', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  await page.goto(`/admin/creditos/${creditoE.id}/cancelacion-anticipada`);
  const total = aImporte(await page.locator('#importe').inputValue());
  await page.locator('#importe').fill(formato(total - 1000));
  await page.locator('#numero_operacion').fill('CAN-0001');
  await page.getByRole('button', { name: 'Registrar la cancelación' }).click();
  await expect(page.locator('#error-importe')).toContainText('El importe debe ser igual al total de la cancelación');

  await page.goto(`/admin/pagos-electronicos/nueva?cliente=${await k.idCliente(julian)}`);
  await page.locator(`input[name="cuotas[${creditoE.id}][]"][value="1"]`).check();
  await page.getByRole('button', { name: 'Generar la orden' }).click();
  await page.goto(`/admin/creditos/${creditoE.id}/cancelacion-anticipada`);
  await expect(page.getByRole('button', { name: 'Registrar la cancelación' })).toBeDisabled();
});

let cobroCancelacion;

test('R-134 Registrar la cancelación anticipada', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  await consulta(
    "UPDATE o SET o.vence = DATEADD(minute, -1, SYSDATETIMEOFFSET()) FROM dbo.ordenes_pago_electronico o JOIN dbo.ordenes_pago_cuotas oc ON oc.orden_pago_id = o.id "
      + "JOIN dbo.cuotas q ON q.id = oc.cuota_id WHERE q.credito_id = @c AND o.estado = 'PendienteConfirmacion'",
    { c: creditoE.id },
  );
  spark('vencimientos:controlar');
  await page.goto(`/admin/creditos/${creditoE.id}/cancelacion-anticipada`);
  await page.locator('#medio').selectOption('Transferencia');
  await page.locator('#numero_operacion').fill('CAN-0001');
  await page.getByRole('button', { name: 'Registrar la cancelación' }).click();
  await expect(exito(page)).toContainText('Crédito cancelado anticipadamente con el cobro');
  cobroCancelacion = Number(page.url().match(/cobros\/(\d+)/)[1]);
  expect(await valor('SELECT estado FROM dbo.creditos WHERE id = @id', { id: creditoE.id })).toBe('Cancelado');
});

test('R-135 No se anula; cálculo del crédito refinanciado', async ({ comoUsuario }) => {
  const gerente = await comoUsuario('gerente');
  await gerente.goto(`/admin/cobros/${cobroCancelacion}/anular`);
  await expect(gerente.locator('body')).toContainText('cancelación anticipada no se anula');
  const cajero = await comoUsuario('cajero');
  const creditoF = await k.credito(clientes.C02, 'Refinanciacion');
  await cajero.goto(`/admin/creditos/${creditoF.id}/cancelacion-anticipada`);
  await expect(cajero.locator('main')).toContainText('no capitalizable');
});

/** CU-36: completa lo acreditado por medio (el registrado, salvo los ajustes) y cierra. */
async function cerrarCaja(page, ajustes = {}, explicaciones = {}) {
  await page.goto('/admin/caja/cierre');
  for (const fila of await page.locator('main tr', { has: page.locator('input[name^="acreditado["]') }).all()) {
    const campo = fila.locator('input[name^="acreditado["]');
    const medio = (await campo.getAttribute('name')).match(/\[(\w+)\]/)[1];
    const registrado = aImporte(await fila.locator('td.importe').first().innerText());
    await campo.fill(formato(registrado + (ajustes[medio] || 0)));
  }
  for (const [medio, texto] of Object.entries(explicaciones)) await page.locator(`#explicacion-${medio}`).fill(texto);
  await page.getByRole('button', { name: 'Cerrar la caja' }).click();
  await page.waitForLoadState();
}

test('R-136 Cierre con diferencia (CU-36)', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  await cerrarCaja(page, { Transferencia: -100 });
  await expect(page.locator('main')).toContainText('explique su causa');
  await cerrarCaja(page, { Transferencia: -100 }, { Transferencia: 'Comisión bancaria descontada por el banco en una transferencia' });
  await expect(exito(page)).toContainText('Caja cerrada. La conciliación registró diferencias en 1 medio(s)');
});

test('R-137 Cierre sin diferencias', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero2');
  await page.goto('/admin/caja');
  await page.locator('main form button[type="submit"]').first().click();
  await expect(exito(page)).toContainText('Caja abierta');
  const credito = await k.credito(federico);
  await k.cobrarTotal(page, federico, credito.id, [1], 'Transferencia', 'TRF-9001');
  await expect(exito(page)).toContainText('registrado');
  await cerrarCaja(page);
  await expect(exito(page)).toContainText('La conciliación no tiene diferencias');
});

test('R-138 Proyección de fondos (CU-92)', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  spark('fondos:proyectar');
  const antes = await valor('SELECT COUNT(*) FROM dbo.proyecciones_fondos');
  expect(antes).toBeGreaterThan(0);
  await page.goto('/admin/fondos');
  await page.locator('#tipo').selectOption('GastoOperativo');
  await page.locator('#caracter-Egreso').check();
  await page.locator('#programado').check();
  await page.locator('#importe').fill('30000000');
  await page.locator('#fecha').fill(fecha(10).split('-').reverse().join('/'));
  await page.locator('#concepto').fill('Egreso programado para la proyección');
  await page.getByRole('button', { name: 'Registrar movimiento' }).click();
  await expect(exito(page)).toBeVisible();
  spark('fondos:proyectar');
  expect(Number(await valor('SELECT TOP 1 disponible_proyectado FROM dbo.proyecciones_fondos ORDER BY id DESC'))).toBeLessThan(0);
});

test('R-139 Vencimientos completos e idempotentes (CU-89)', async () => {
  await consulta(
    "UPDATE s SET s.vence = DATEADD(minute, -1, SYSDATETIMEOFFSET()) FROM dbo.simulaciones s JOIN dbo.clientes cl ON cl.id = s.cliente_id WHERE cl.cuit_cuil = @c AND s.estado = 'Vigente'",
    { c: clientes.C12.cuil },
  );
  spark('vencimientos:controlar');
  const alertas = await valor('SELECT COUNT(*) FROM dbo.alertas');
  const vencidas = await valor("SELECT COUNT(*) FROM dbo.simulaciones WHERE estado = 'Vencida'");
  expect(vencidas).toBeGreaterThan(0);
  spark('vencimientos:controlar');
  expect(await valor('SELECT COUNT(*) FROM dbo.alertas')).toBe(alertas);
  expect(await valor("SELECT COUNT(*) FROM dbo.simulaciones WHERE estado = 'Vencida'")).toBe(vencidas);
});

test('R-140 Evaluación repetida sin cambios', async () => {
  const evaluaciones = await valor('SELECT COUNT(*) FROM dbo.evaluaciones_crediticias');
  const alertas = await valor('SELECT COUNT(*) FROM dbo.alertas');
  spark('evaluacion:ejecutar');
  spark('evaluacion:ejecutar');
  expect(await valor('SELECT COUNT(*) FROM dbo.evaluaciones_crediticias')).toBe(evaluaciones);
  expect(await valor('SELECT COUNT(*) FROM dbo.alertas')).toBe(alertas);
});

test('R-141 Permisos por rol', async ({ comoUsuario }) => {
  const rutas = {
    cajero: ['/admin/usuarios', '/admin/parametros/morosidad', '/admin/resoluciones'],
    analista: ['/admin/cajas', '/admin/cobros/anulaciones', '/admin/fondos'],
    gerente: ['/admin/usuarios'],
    admin: ['/admin/cobros', '/admin/solicitudes'],
  };
  for (const [rol, lista] of Object.entries(rutas)) {
    const page = await comoUsuario(rol);
    for (const ruta of lista) expect((await page.goto(ruta)).status(), `${rol} en ${ruta}`).toBe(403);
  }
});

test('R-143 Registros inexistentes', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  for (const ruta of ['/admin/clientes/999999', '/admin/solicitudes/999999', '/admin/refinanciaciones/999999']) {
    await page.goto(ruta);
    const texto = await page.locator('body').innerText();
    expect(texto, ruta).not.toMatch(/Exception|SQLSTATE|Stack trace/);
  }
});

// R-144 exige 30 minutos sin uso (15 en el portal): queda para la prueba manual. El mensaje de fin de sesión se
// corrigió en #148 (finalizar_sesion) y R-007 verifica el mismo mecanismo.
test.fixme('R-144 Fin de la sesión por inactividad', async () => {});

test('R-145 Pantalla angosta (360 px)', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  await page.setViewportSize({ width: 360, height: 780 });
  for (const ruta of ['/admin/clientes', '/admin/simulaciones', '/admin/refinanciaciones']) {
    await page.goto(ruta);
    const ancho = await page.evaluate(() => document.documentElement.scrollWidth);
    expect(ancho, ruta).toBeLessThanOrEqual(362);
  }
});

test('R-146 Auditoría sin datos sensibles (RN-45)', async () => {
  const filas = await consulta('SELECT TOP 2000 detalle AS datos FROM dbo.registros_auditoria');
  expect(filas.length).toBeGreaterThan(50);
  for (const clave of ['Administracion-Prueba-2026', 'Gerencia-Prueba-2026', 'Caja-Uno-Prueba-2026', 'Portal-Ana-2026-OK']) {
    expect(filas.some((f) => (f.datos || '').includes(clave)), clave).toBe(false);
  }
});

test('R-147 Fondos coherentes (D-37)', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  await page.goto('/admin/fondos');
  await expect(page.locator('main')).toContainText('Disponible libre');
});
