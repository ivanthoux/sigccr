// Parte 2, secciones 11 y 12. Evaluación crediticia, alternativas y resolución (CU-86, CU-24, CU-25, CU-87, CU-64,
// CU-79 y CU-26): R-064 a R-079.
const { test, expect, enviarConfirmando } = require('../lib/fixtures');
const { clientes, parametros, fecha, ddmmaaaa } = require('../lib/datos');
const { consulta, valor, cerrarBase } = require('../lib/sistema');
const f = require('../lib/flujo');

const { C01: ana, C02: bruno, C05: elena, C06: federico, C08: hernan, C09: ines, C11: lucia } = clientes;
const exito = (page) => page.locator('.alert-success[role="status"]');
const solicitudDe = (cliente) => valor(
  'SELECT TOP 1 s.id FROM dbo.solicitudes_credito s JOIN dbo.clientes c ON c.id = s.cliente_id WHERE c.cuit_cuil = @c ORDER BY s.id DESC',
  { c: cliente.cuil },
);

/** Simula, registra y verifica con todo Conforme; luego el Temporizador evalúa. */
async function hastaEvaluacion(page, cliente, linea = 'MOTOS', importe = 500000, cuotas = 12) {
  const id = await f.registrarSolicitud(page, await f.simular(page, cliente, linea, importe, cuotas));
  await f.verificar(page, id);
  f.evaluar();
  return id;
}

async function resolver(page, id, { decision, plan, importe, cuotas, etapa, fundamento = 'Fundamento de la resolución para la prueba integral' } = {}) {
  await page.goto(`/admin/solicitudes/${id}/resolver`);
  if (decision) await page.locator(`label[for="decision-${decision}"]`).click();
  if (plan === 'definida') {
    await page.locator('#alt-definida').check();
    await page.locator('#importe').fill(String(importe));
    await page.locator('#cantidad_cuotas').fill(String(cuotas));
  }
  if (etapa) await page.locator('#etapa_retorno').selectOption(etapa);
  await page.locator('#fundamento').fill(fundamento);
  await page.getByRole('button', { name: 'Registrar la resolución' }).click();
  await page.waitForLoadState();
}

async function topeGlobal(page, tope) {
  await page.goto('/admin/parametros/estructuracion');
  for (const [campo, v] of Object.entries({ ...parametros.estructuracion, tope_global_cliente: tope })) await page.locator(`#${campo}`).fill(v);
  await page.locator('#motivo').fill(parametros.motivo);
  await enviarConfirmando(page, 'Registrar nueva versión');
  await expect(exito(page)).toBeVisible();
}

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);

test('R-064 Evaluación favorable', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  await page.goto(`/admin/solicitudes/${await solicitudDe(ana)}/evaluacion`);
  await expect(page.locator('main')).toContainText('ResultadoFavorable');
  await expect(page.locator('main')).toContainText('VigenciaVigente');
  await expect(page.locator('main')).toContainText('puntos de 100');
});

test('R-065 Situación 1 en la Central de Deudores', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const id = await hastaEvaluacion(page, elena);
  expect(await f.estadoSolicitud(id)).toBe('PendienteSeleccionPlan');
  await page.goto(`/admin/solicitudes/${id}/evaluacion`);
  await expect(page.locator('main')).toContainText('BANCO SIMULADO');
});

test('R-066 Análisis gerencial', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const id = await hastaEvaluacion(page, federico);
  expect(await f.estadoSolicitud(id)).toBe('PendienteResolucion');
  expect(await valor("SELECT COUNT(*) FROM dbo.alertas WHERE rol_destino = 'Gerente' AND objeto_tipo = 'solicitudes_credito' AND objeto_id = @id", { id: String(id) })).toBeGreaterThan(0);
});

test('R-067 Situación 5 excluyente (RN-69)', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const id = await hastaEvaluacion(page, hernan);
  expect(await f.estadoSolicitud(id)).toBe('Rechazada');
});

test('R-068 Contingencia por servicio no disponible', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const id = await hastaEvaluacion(page, ines);
  f.evaluar();
  expect(await f.estadoSolicitud(id)).toBe('PendienteContingencia');
  await page.goto('/admin/evaluaciones/contingencia');
  await expect(page.locator('main')).toContainText('Villalba');
});

test('R-069 Registrar la evaluación de contingencia (CU-25)', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const id = await solicitudDe(ines);
  const mesPasado = fecha(-31).slice(0, 7).split('-').reverse().join('/');
  const registrar = async ({ periodo = mesPasado, situacion = '1', monto = '180000', justificacion = 'Situación 1 en una sola entidad, sin atrasos, con ingresos estables' } = {}) => {
    await page.goto(`/admin/solicitudes/${id}/contingencia`);
    for (const ant of await page.locator('input[name="antecedentes[]"]').all()) if (await ant.isEnabled()) await ant.check();
    await page.locator('#detalle_antecedentes').fill('Consulta telefónica a la Central de Deudores por falla del servicio');
    await page.locator('input[name="deudas[0][entidad]"]').fill('Banco Macro');
    await page.locator('input[name="deudas[0][situacion]"]').fill(situacion);
    await page.locator('input[name="deudas[0][monto]"]').fill(monto);
    await page.locator('input[name="deudas[0][periodo]"]').fill(periodo);
    await page.locator('label[for="rec-Favorable"]').click();
    await page.locator('#justificacion').fill(justificacion);
    await page.getByRole('button', { name: 'Registrar evaluación de contingencia' }).click();
    await page.waitForLoadState();
  };
  for (const invalido of [{ periodo: '13/2025' }, { periodo: '01/2020' }, { situacion: '7' }, { monto: '' }, { justificacion: 'Ok' }]) {
    await registrar(invalido);
    await expect(page.locator('.invalid-feedback, .alert-danger').first(), JSON.stringify(invalido)).toBeVisible();
    expect(await f.estadoSolicitud(id)).toBe('PendienteContingencia');
  }
  await registrar();
  await expect(exito(page)).toContainText('Evaluación de contingencia registrada: la solicitud queda pendiente de resolución');
});

test('R-070 Sin capacidad de pago', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const id = await hastaEvaluacion(page, lucia, 'PERSONAL', 1000000, 6);
  expect(await f.estadoSolicitud(id)).toBe('PendienteResolucion');
});

test('R-071 Alternativas de Ana', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  await page.goto(`/admin/solicitudes/${await solicitudDe(ana)}/alternativas`);
  await expect(page.locator('main')).toContainText('Capacidad de pago');
  await expect(page.locator('main table tbody tr').first()).toContainText('Pedida');
});

test('R-072 Validaciones de la conformidad', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const id = await solicitudDe(ana);
  await f.confirmarPedida(page, id, { dia: '' });
  await expect(page.locator('#error-fecha_conformidad')).toBeVisible();
  await f.confirmarPedida(page, id, { dia: fecha(2) });
  await expect(page.locator('#error-fecha_conformidad')).toBeVisible();
  await f.confirmarPedida(page, id, { medio: '' });
  await expect(page.locator('#error-medio_conformidad')).toBeVisible();
  expect(await f.estadoSolicitud(id)).toBe('PendienteSeleccionPlan');
});

test('R-073 Aprobación automática (RN-49)', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const id = await solicitudDe(ana);
  await f.confirmarPedida(page, id);
  await expect(exito(page)).toContainText('Alternativa confirmada y solicitud aprobada automáticamente. La aprobación vence el');
  expect(await f.estadoSolicitud(id)).toBe('AprobadaPendienteLiquidacion');
});

test('R-074 Confirmación simultánea', async ({ comoUsuario }) => {
  const page = await comoUsuario('analista');
  const id = await hastaEvaluacion(page, bruno, 'MOTOS', 600000, 12);
  expect(await f.estadoSolicitud(id)).toBe('PendienteSeleccionPlan');
  await page.goto(`/admin/solicitudes/${id}/alternativas`);
  const url = await page.locator('main table tbody tr', { hasText: 'Pedida' }).getByRole('link').first().getAttribute('href');
  const otra = await page.context().newPage();
  for (const p of [page, otra]) {
    await p.goto(url);
    await p.locator('#fecha_conformidad').fill(ddmmaaaa(fecha()));
    await p.locator('#medio_conformidad').selectOption('PresencialFirma');
  }
  await page.getByRole('button', { name: 'Confirmar alternativa' }).click();
  await expect(exito(page)).toContainText('aprobada automáticamente');
  await otra.getByRole('button', { name: 'Confirmar alternativa' }).click();
  await expect(otra.locator('.alert-success[role="status"]')).toHaveCount(0);
  const [r] = await consulta(
    "SELECT COUNT(*) AS n FROM dbo.alternativas_financiacion a JOIN dbo.estructuraciones e ON e.id = a.estructuracion_id WHERE e.solicitud_id = @id AND a.estado = 'Confirmada'",
    { id },
  );
  expect(r.n).toBe(1);
  await otra.close();
});

test('R-075 Bandeja del Gerente', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  await page.goto('/admin/resoluciones');
  for (const apellido of ['Acosta', 'Villalba', 'Martínez']) await expect(page.locator('main')).toContainText(apellido);
});

test('R-076 Validaciones de la resolución', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  const id = await solicitudDe(federico);
  await resolver(page, id, {});
  await expect(page.locator('#error-decision')).toBeVisible();
  await resolver(page, id, { decision: 'DevueltaRevision' });
  await expect(page.locator('#error-etapa_retorno')).toBeVisible();
  // La pantalla propone el importe y las cuotas pedidos: «sin plan» es borrarlos
  await resolver(page, id, { decision: 'Aprobada', plan: 'definida', importe: '', cuotas: '' });
  await expect(page.locator('#error-importe, #error-cantidad_cuotas, #error-plan').first()).toBeVisible();
  expect(await f.estadoSolicitud(id)).toBe('PendienteResolucion');
});

test('R-077 Aprobar con el plan del Gerente', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  const id = await solicitudDe(federico);
  await resolver(page, id, { decision: 'Aprobada', plan: 'definida', importe: 450000, cuotas: 12, fundamento: 'Se reduce el importe por el análisis de su endeudamiento' });
  await expect(exito(page)).toContainText('Solicitud aprobada:');
  expect(await f.estadoSolicitud(id)).toBe('AprobadaPendienteLiquidacion');
});

test('R-078 Tope global por cliente', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  const id = await solicitudDe(ines);
  await topeGlobal(page, '500000');
  await resolver(page, id, { decision: 'Aprobada', plan: 'definida', importe: 600000, cuotas: 12 });
  await expect(page.locator('main')).toContainText('supera el tope global por cliente');
  await topeGlobal(page, '');
  await resolver(page, id, { decision: 'Aprobada', plan: 'definida', importe: 400000, cuotas: 12 });
  await expect(exito(page)).toContainText('Solicitud aprobada:');
});

test('R-079 Devolver, corregir y rechazar', async ({ comoUsuario }) => {
  const gerente = await comoUsuario('gerente');
  const analista = await comoUsuario('analista');
  const id = await solicitudDe(lucia);
  await resolver(gerente, id, { decision: 'DevueltaRevision', etapa: 'Verificacion', fundamento: 'Revisar la verificación del legajo' });
  await expect(gerente.locator('.alert-success[role="status"]')).toContainText('Solicitud devuelta para revisión');
  await analista.goto(`/admin/solicitudes/${id}/corregir`);
  await analista.locator('#aclaracion').fill('Se verificó nuevamente el recibo de sueldo con el empleador');
  await analista.getByRole('button', { name: 'Registrar corrección' }).click();
  await expect(analista.locator('.alert-success[role="status"]')).toContainText('pendiente de verificación');
  await f.verificar(analista, id);
  f.evaluar();
  expect(await f.estadoSolicitud(id)).toBe('PendienteResolucion');
  await resolver(gerente, id, { decision: 'Rechazada', fundamento: 'Ingresos insuficientes para la cuota mínima' });
  await expect(gerente.locator('.alert-success[role="status"]')).toContainText('Solicitud rechazada.');
});
