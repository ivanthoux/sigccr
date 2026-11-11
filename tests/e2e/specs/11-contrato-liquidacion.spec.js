// Parte 2, secciones 13 y 14. Contrato, liquidación, cancelación y consultas (CU-103, CU-27, CU-28, CU-29, CU-30 y
// D-24): R-080 a R-091. Al terminar quedan liquidados los créditos A a E de la iteración 3 (A.10).
const { test, expect } = require('../lib/fixtures');
const { clientes, desembolsos, cbuInvalido, cvu, fecha } = require('../lib/datos');
const { valor, cerrarBase } = require('../lib/sistema');
const f = require('../lib/flujo');

const { C01: ana, C02: bruno, C05: elena, C06: federico, C12: marcos } = clientes;
const exito = (page) => page.locator('.alert-success');
const solicitudDe = (cliente) => valor(
  "SELECT TOP 1 s.id FROM dbo.solicitudes_credito s JOIN dbo.clientes c ON c.id = s.cliente_id WHERE c.correo = @c AND s.estado NOT IN ('Rechazada', 'Cancelada') ORDER BY s.id DESC",
  { c: cliente.correo },
);

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);

test('R-080 Contratos generados', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  await page.goto('/admin/contratos');
  for (const apellido of ['Gómez', 'Fernández', 'Acosta']) await expect(page.locator('main')).toContainText(apellido);
  await page.goto(`/admin/contratos/${await solicitudDe(ana)}`);
  await expect(page.locator('main')).toContainText('CFTEA');
});

test('R-081 Liquidar sin la constancia firmada (RN-25)', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  await page.goto(`/admin/liquidaciones/${await solicitudDe(ana)}`);
  await expect(page.locator('main')).toContainText('Falta la constancia firmada del contrato de préstamo en el legajo');
});

test('R-082 Incorporar la constancia firmada (CU-103)', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  await f.incorporarContrato(page, await solicitudDe(ana));
  await expect(exito(page)).toContainText('Constancia firmada incorporada al legajo: la solicitud ya puede liquidarse');
});

test('R-083 Validaciones del desembolso', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  const id = await solicitudDe(ana);
  const casos = [
    [{ documento: bruno.dni }, 'documento_verificado'],
    [{ cbu: cbuInvalido }, 'cbu'],
    [{ cbu: cvu }, 'cbu'],
    [{ evidencia: '' }, 'evidencia_titularidad'],
    [{ comprobante: '' }, 'comprobante'],
    [{ dia: fecha(2) }, 'fecha_transferencia'],
    [{ importe: '1000' }, 'importe_transferencia'],
  ];
  for (const [datos, campo] of casos) {
    await f.liquidar(page, id, ana, desembolsos.C01, datos);
    await expect(page.locator(`#error-${campo}`), JSON.stringify(datos)).toBeVisible();
  }
  await expect(page.locator('main')).toContainText('El importe transferido debe ser el neto a desembolsar.');
  expect(await f.estadoSolicitud(id)).toBe('AprobadaPendienteLiquidacion');
});

test('R-084 Liquidar el crédito A', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  const id = await solicitudDe(ana);
  await f.liquidar(page, id, ana, desembolsos.C01);
  await expect(exito(page)).toContainText('Crédito liquidado: se desembolsaron');
  expect(await f.estadoSolicitud(id)).toBe('Liquidada');
  await page.goto(`/admin/liquidaciones/${id}`);
  await expect(page.locator('body')).toContainText('ya fue liquidada');
});

test('R-085 Plan del Gerente sin la conformidad del cliente (RN-22)', async ({ comoUsuario }) => {
  const analista = await comoUsuario('analista');
  const cajero = await comoUsuario('cajero');
  const id = await solicitudDe(federico);
  await f.incorporarContrato(analista, id);
  await f.liquidar(cajero, id, federico, desembolsos.C06, { conformidad: false });
  await expect(cajero.locator('main')).toContainText('registre la conformidad del cliente antes de liquidar (RN-22)');
  await f.liquidar(cajero, id, federico, desembolsos.C06);
  await expect(cajero.locator('.alert-success')).toContainText('Crédito liquidado');
});

test('R-086 Créditos B a E de la iteración 3', async ({ comoUsuario }) => {
  const analista = await comoUsuario('analista');
  const cajero = await comoUsuario('cajero');
  for (const codigo of ['C02', 'C03', 'C04', 'C10']) {
    const cliente = clientes[codigo];
    let id = await solicitudDe(cliente);
    if (!id || await f.estadoSolicitud(id) !== 'AprobadaPendienteLiquidacion') {
      id = await f.hastaAprobacion(analista, cliente, 'MOTOS', desembolsos[codigo].importe, 12);
    }
    await f.incorporarContrato(analista, id);
    await f.liquidar(cajero, id, cliente, desembolsos[codigo]);
    await expect(cajero.locator('.alert-success'), `liquidación de ${cliente.apellido}`).toContainText('Crédito liquidado');
  }
  expect(await valor("SELECT COUNT(*) FROM dbo.creditos WHERE estado = 'Activo'")).toBe(6);
});

test('R-087 Cancelar una solicitud aprobada (CU-28)', async ({ comoUsuario }) => {
  const analista = await comoUsuario('analista');
  const id = await solicitudDe(elena);
  await f.confirmarPedida(analista, id);
  expect(await f.estadoSolicitud(id)).toBe('AprobadaPendienteLiquidacion');
  const cancelar = async (solicitud) => {
    await analista.goto(`/admin/solicitudes/${solicitud}/cancelar`);
    if (await analista.locator('#motivo').count() === 0) return;
    await analista.locator('#motivo').fill('El cliente desiste del crédito');
    if (await analista.locator('#confirma_reserva').count()) await analista.locator('#confirma_reserva').check();
    await analista.getByRole('button', { name: 'Cancelar la solicitud' }).click();
  };
  await cancelar(id);
  await expect(analista.locator('.alert-success')).toContainText('Solicitud cancelada.');
  await cancelar(await valor("SELECT TOP 1 s.id FROM dbo.solicitudes_credito s JOIN dbo.clientes c ON c.id = s.cliente_id WHERE c.correo = @c AND s.estado = 'Liquidada'", { c: ana.correo }));
  await expect(analista.locator('body')).toContainText('La solicitud ya finalizó: no se puede cancelar.');
});

test('R-088 Buscar solicitudes', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const filas = page.locator('main table tbody tr');
  await page.goto(`/admin/solicitudes?documento=${ana.dni}`);
  await expect(filas.first()).toContainText('Gómez');
  await page.goto('/admin/solicitudes?estado=Liquidada');
  await expect(filas).toHaveCount(6);
  await page.goto(`/admin/solicitudes?desde=${fecha(1)}&hasta=${fecha(-1)}`);
  await expect(page.locator('main')).toContainText('(A2)');
});

test('R-089 Ficha completa de la solicitud', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  await page.goto(`/admin/solicitudes/${await valor("SELECT TOP 1 s.id FROM dbo.solicitudes_credito s JOIN dbo.clientes c ON c.id = s.cliente_id WHERE c.correo = @c AND s.estado = 'Liquidada'", { c: ana.correo })}`);
  for (const texto of ['Liquidada', 'Favorable', 'Contrato']) await expect(page.locator('main')).toContainText(texto);
});

test('R-090 Búsqueda del Cajero', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  const respuesta = await page.goto('/admin/solicitudes');
  expect(respuesta.status()).toBeLessThan(400);
});

test('R-091 Confirmación de cambios con solicitudes en trámite (D-24)', async ({ comoUsuario }) => {
  const analista = await comoUsuario('analista');
  const gerente = await comoUsuario('gerente');
  const sol = await f.registrarSolicitud(analista, await f.simular(analista, marcos, 'MOTOS', 500000, 12));
  const version = () => valor('SELECT version_condiciones_id FROM dbo.solicitudes_credito WHERE id = @id', { id: sol });
  const antes = await version();
  const id = await f.idLinea('MOTOS');
  await gerente.goto(`/admin/lineas/${id}/editar`);
  await gerente.locator('#tna').fill('62');
  await gerente.locator('#fecha_vigencia').fill(fecha());
  await gerente.locator('#motivo').fill('Actualización de la tasa de mercado');
  await gerente.getByRole('button', { name: 'Registrar nueva versión' }).click();
  // El servidor pide la confirmación y vuelve a mostrar el formulario con los datos cargados
  await expect(gerente.locator('main')).toContainText('en trámite');
  await gerente.locator('#confirmar_cambio').check();
  await gerente.getByRole('button', { name: 'Registrar nueva versión' }).click();
  await expect(gerente.locator('.alert-success')).toBeVisible();
  expect(await version()).toBe(antes);
});
