// Circuito de una solicitud en el panel (CU-21 a CU-28), reutilizado por las secciones 9 a 14 y por la iteración 3.
const { expect } = require('@playwright/test');
const { fecha, ddmmaaaa } = require('./datos');
const { documento } = require('./archivos');
const { spark, valor } = require('./sistema');

const idCliente = (correo) => valor('SELECT id FROM dbo.clientes WHERE correo = @c', { c: correo });
const idLinea = (codigo) => valor('SELECT id FROM dbo.lineas_credito WHERE codigo = @c', { c: codigo });
const estadoSolicitud = (id) => valor('SELECT estado FROM dbo.solicitudes_credito WHERE id = @id', { id });
const idDeUrl = (page, patron) => Number(page.url().match(patron)[1]);

/** CU-21: simula y devuelve el id de la simulación (o null si quedó en el formulario con errores). */
async function simular(page, cliente, codigoLinea, importe, cuotas) {
  await page.goto(`/admin/simulaciones?cliente=${await idCliente(cliente.correo)}&linea=${await idLinea(codigoLinea)}`);
  await page.locator('#importe').fill(String(importe));
  await page.locator('#cantidad_cuotas').fill(String(cuotas));
  await page.getByRole('button', { name: 'Simular' }).click();
  await page.waitForLoadState();
  const m = page.url().match(/\/admin\/simulaciones\/(\d+)/);
  return m ? Number(m[1]) : null;
}

/** CU-22: formaliza la simulación y devuelve el id de la solicitud. */
async function registrarSolicitud(page, simulacionId) {
  await page.goto(`/admin/solicitudes/nueva/${simulacionId}`);
  await page.getByRole('button', { name: 'Registrar solicitud' }).click();
  await page.waitForURL(/\/admin\/solicitudes\/\d+$/);
  return idDeUrl(page, /\/admin\/solicitudes\/(\d+)/);
}

/**
 * CU-23: revisa el legajo. Por omisión marca todo Conforme y Verificada.
 * `documentos` permite otro resultado por código de tipo: { RECIBO_SUELDO: { resultado: 'Observado', motivo: 'Ilegible', observacion } }.
 */
async function verificar(page, solicitudId, { resultado = 'Verificada', documentos = {}, observaciones = '', motivoRechazo = '' } = {}) {
  await page.goto(`/admin/solicitudes/${solicitudId}/verificar`);
  for (const grupo of await page.locator('fieldset.verificacion-documento').all()) {
    const tipo = (await grupo.locator('legend').innerText()).trim();
    const especial = Object.entries(documentos).find(([nombre]) => tipo.startsWith(nombre));
    const r = especial ? especial[1] : { resultado: 'Conforme' };
    await grupo.locator(`input[value="${r.resultado}"] + label`).click();
    if (r.motivo) await grupo.locator('select').selectOption(r.motivo);
    if (r.observacion) await grupo.locator('input[type="text"]').fill(r.observacion);
  }
  await page.locator(`label[for="resultado-${resultado}"]`).click();
  if (observaciones) await page.locator('#observaciones').fill(observaciones);
  if (motivoRechazo) await page.locator('#motivo_rechazo').fill(motivoRechazo);
  await page.getByRole('button', { name: 'Registrar revisión' }).click();
  await page.waitForLoadState();
}

/** CU-86: el Temporizador evalúa las solicitudes pendientes. */
function evaluar() {
  spark('evaluacion:ejecutar');
}

/** CU-79: confirma la alternativa pedida con la conformidad del cliente. */
async function confirmarPedida(page, solicitudId, { dia = fecha(), medio = 'PresencialFirma' } = {}) {
  await page.goto(`/admin/solicitudes/${solicitudId}/alternativas`);
  await page.locator('main table tbody tr', { hasText: 'Pedida' }).getByRole('link').first().click();
  await page.locator('#fecha_conformidad').fill(dia ? ddmmaaaa(dia) : '');
  await page.locator('#medio_conformidad').selectOption(medio);
  await page.getByRole('button', { name: 'Confirmar alternativa' }).click();
  await page.waitForLoadState();
}

/** CU-103: incorpora la constancia firmada del contrato. */
async function incorporarContrato(page, solicitudId) {
  await page.goto(`/admin/contratos/${solicitudId}`);
  await page.locator('#constancia').setInputFiles(documento('contrato_firmado_prueba.pdf'));
  await page.locator('#fecha_recepcion').fill(fecha());
  await page.locator('#constancia').locator('xpath=ancestor::form').locator('button[type="submit"]').click();
  await page.waitForLoadState();
}

/** Neto a desembolsar que muestra la pantalla de liquidación («Desembolso de $ …»). */
async function netoALiquidar(page) {
  const titulo = await page.locator('h2', { hasText: 'Desembolso de' }).innerText();
  return titulo.replace('Desembolso de', '').replace('$', '').trim();
}

/** CU-27: liquida por transferencia. `datos` reemplaza los valores correctos para probar validaciones. */
async function liquidar(page, solicitudId, cliente, desembolso, datos = {}) {
  await page.goto(`/admin/liquidaciones/${solicitudId}`);
  const d = {
    documento: cliente.dni, cbu: desembolso.cbu, cuit: cliente.cuil, evidencia: desembolso.evidencia, comprobante: desembolso.operacion,
    dia: fecha(), importe: await netoALiquidar(page), conformidad: true, ...datos,
  };
  await page.locator('#documento_verificado').fill(d.documento);
  if (await page.locator('#conformidad').count()) await page.locator('#conformidad').setChecked(d.conformidad);
  await page.locator('#cbu').fill(d.cbu);
  await page.locator('#cuit_titular').fill(d.cuit);
  await page.locator('#evidencia_titularidad').fill(d.evidencia);
  await page.locator('#comprobante').fill(d.comprobante);
  await page.locator('#fecha_transferencia').fill(d.dia ? ddmmaaaa(d.dia) : '');
  await page.locator('#importe_transferencia').fill(d.importe);
  await page.getByRole('button', { name: 'Registrar el desembolso' }).click();
  await page.waitForLoadState();
}

/** Circuito completo hasta la aprobación automática: simular, registrar, verificar, evaluar y confirmar. */
async function hastaAprobacion(analista, cliente, linea, importe, cuotas) {
  const sim = await simular(analista, cliente, linea, importe, cuotas);
  expect(sim, `simulación de ${cliente.apellido}`).toBeTruthy();
  const id = await registrarSolicitud(analista, sim);
  await verificar(analista, id);
  evaluar();
  expect(await estadoSolicitud(id), `evaluación de ${cliente.apellido}`).toBe('PendienteSeleccionPlan');
  await confirmarPedida(analista, id);
  expect(await estadoSolicitud(id), `aprobación de ${cliente.apellido}`).toBe('AprobadaPendienteLiquidacion');
  return id;
}

module.exports = {
  idCliente, idLinea, estadoSolicitud, simular, registrarSolicitud, verificar, evaluar, confirmarPedida,
  incorporarContrato, netoALiquidar, liquidar, hastaAprobacion,
};
