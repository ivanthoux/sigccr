// Parte 2, sección 7. Parámetros y calendario (CU-58, CU-59, CU-60 y CU-82): R-041 a R-045.
const { test, expect } = require('../lib/fixtures');
const { parametros, fecha, ddmmaaaa } = require('../lib/datos');
const { valor, cerrarBase } = require('../lib/sistema');

const exito = (page) => page.locator('.alert-success[role="status"]');
const versiones = (tipo) => valor('SELECT COUNT(*) FROM dbo.versiones_parametros WHERE tipo = @t', { t: tipo });

/** Completa un formulario de parámetros con los valores dados y registra la versión. */
async function guardarParametros(page, ruta, valores) {
  await page.goto(`/admin/parametros/${ruta}`);
  for (const [campo, v] of Object.entries(valores)) await page.locator(`#${campo}`).fill(v);
  await page.locator('#motivo').fill(parametros.motivo);
  await page.getByRole('button', { name: 'Registrar nueva versión' }).click();
}

async function probarInvalidos(page, ruta, tipo, validos, invalidos) {
  const antes = await versiones(tipo);
  for (const cambio of invalidos) {
    await guardarParametros(page, ruta, { ...validos, ...cambio });
    const campo = Object.keys(cambio).pop();
    await expect(page.locator(`#error-${campo}`), `error en ${campo} con ${JSON.stringify(cambio)}`).toBeVisible();
  }
  expect(await versiones(tipo)).toBe(antes);
}

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);

test('R-041 Parámetros de evaluación', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  const tipo = 'Evaluacion';
  const antes = await versiones(tipo);
  await page.goto('/admin/parametros/evaluacion?sugeridos=1');
  await page.locator('#motivo').fill(parametros.motivo);
  await page.getByRole('button', { name: 'Registrar nueva versión' }).click();
  await expect(exito(page)).toContainText('de los parámetros de evaluación');
  expect(await versiones(tipo)).toBe(antes + 1);

  await page.locator('input[name^="pesos["]').first().fill('1,5');
  await page.locator('#motivo').fill(parametros.motivo);
  await page.getByRole('button', { name: 'Registrar nueva versión' }).click();
  await expect(page.locator('.invalid-feedback').first()).toBeVisible();
  expect(await versiones(tipo)).toBe(antes + 1);
});

test('R-042 Parámetros de estructuración', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  const validos = parametros.estructuracion;
  await probarInvalidos(page, 'estructuracion', 'Estructuracion', validos, [
    { porcentaje_afectacion: '0' }, { porcentaje_afectacion: '120' }, { alicuota_iva: '150' },
    { vigencia_alternativas_dias: '0' }, { vigencia_alternativas_dias: '45' },
    { escalones: '75 50' }, { escalones: '100 50 75' }, { escalones: '100 90 80 70 60 50 40' },
  ]);
  await guardarParametros(page, 'estructuracion', validos);
  await expect(exito(page)).toContainText('de los parámetros de estructuración');
});

test('R-043 Parámetros de morosidad', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  const validos = parametros.morosidad;
  await guardarParametros(page, 'morosidad', { ...validos, tasa_punitoria_diaria: '2' });
  await expect(page.locator('#error-tasa_punitoria_diaria')).toContainText('La tasa punitoria diaria debe estar entre 0 % y 1 % por día.');
  await probarInvalidos(page, 'morosidad', 'Morosidad', validos, [
    { tope_sobre_tna: '60' }, { tolerancia_dias: '45' }, { umbral_morosidad_avanzada_dias: '2' }, { limite_condonacion: '150' },
    { refinanciacion_tasa_minima: '30', refinanciacion_tasa_maxima: '20' }, { refinanciacion_cuotas_maximas: '200' },
  ]);
  await guardarParametros(page, 'morosidad', validos);
  await expect(exito(page)).toContainText('de los parámetros de morosidad');
});

// Fechas relativas a hoy, para que la prueba no dependa del día en que se ejecuta. No pueden coincidir con las fechas
// de la carga anual del año próximo: desde mediados de noviembre, hoy + 45 días puede caer el 01/01
const anioProximo = Number(fecha().slice(0, 4)) + 1;
const DE_LA_CARGA = ['01-01', '02-15', '02-16', '03-24', '04-02', '05-01', '05-25', '06-10'];
const usadas = new Set();
const libre = (dias) => {
  let d = dias;
  while (DE_LA_CARGA.includes(fecha(d).slice(5)) || usadas.has(d)) d++;
  usadas.add(d);
  return fecha(d);
};
const feriado1 = libre(28);
const cierre = libre(44);
const feriado2 = libre(45);

test('R-152 Modelo de contrato (CU-106, D-56)', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  await page.goto('/admin/parametros/contrato');
  await expect(page.locator('main')).toContainText('Vigente: modelo inicial.');
  const clausulas = page.locator('[data-clausula]');
  await expect(clausulas).toHaveCount(11);
  // Una variable mal escrita se rechaza y el formulario conserva lo escrito
  await page.getByRole('button', { name: 'Agregar cláusula' }).click();
  await expect(clausulas).toHaveCount(12);
  await page.locator('#clausula-12-titulo').fill('Seguro');
  await page.locator('#clausula-12-texto').fill('Seguro de vida sobre saldo deudor del préstamo de {monto}.');
  await page.locator('#motivo').fill('Cláusula de seguro de vida pedida por la entidad.');
  await page.getByRole('button', { name: 'Registrar nueva versión' }).click();
  await expect(page.locator('#clausula-12-texto')).toHaveClass(/is-invalid/);
  await expect(page.locator('main')).toContainText('Variable desconocida: {monto}');
  // Se corrige, se sube un lugar y se registra la versión 1
  await page.locator('#clausula-12-texto').fill('Seguro de vida sobre saldo deudor del préstamo de {importe}.');
  await page.locator('[data-clausula]').nth(11).getByRole('button', { name: 'Subir la cláusula' }).click();
  await expect(page.locator('#clausula-11-titulo')).toHaveValue('Seguro');
  await page.locator('#motivo').fill('Cláusula de seguro de vida pedida por la entidad.');
  await page.getByRole('button', { name: 'Registrar nueva versión' }).click();
  await expect(exito(page)).toContainText('Se registró la versión 1 del modelo de contrato');
  expect(await valor("SELECT COUNT(*) FROM dbo.versiones_parametros WHERE tipo = 'Contrato'")).toBe(1);
  // Lo exigido por la ley no se puede quitar: sin el plan de cuotas no se registra
  await page.locator('[data-clausula]', { has: page.locator('textarea', { hasText: '{plan_cuotas}' }) }).getByRole('button', { name: 'Quitar la cláusula' }).click();
  await page.locator('#motivo').fill('Prueba de una cláusula obligatoria.');
  await page.getByRole('button', { name: 'Registrar nueva versión' }).click();
  await expect(page.locator('main')).toContainText('Falta {plan_cuotas}');
  // Se vuelve al modelo inicial para el resto de la batería
  await page.goto('/admin/parametros/contrato?inicial=1');
  await page.locator('#motivo').fill('Se vuelve al modelo inicial.');
  await page.getByRole('button', { name: 'Registrar nueva versión' }).click();
  await expect(exito(page)).toContainText('Se registró la versión 2 del modelo de contrato');
  const analista = await comoUsuario('analista');
  expect((await analista.goto('/admin/parametros/contrato')).status()).toBe(403);
});

test('R-044 Calendario operativo', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  const agregar = async (dia, descripcion, tipo = 'Feriado', carga = '') => {
    await page.goto('/admin/calendario');
    await page.locator('#fecha').fill(dia);
    await page.locator('#tipo').selectOption(tipo);
    await page.locator('#descripcion').fill(descripcion);
    await page.locator('#carga').fill(carga);
    await page.getByRole('button', { name: 'Registrar', exact: true }).click();
  };
  await agregar(ddmmaaaa(feriado1), 'Feriado de prueba uno');
  await expect(exito(page)).toContainText('Se registraron 1 día(s) no hábil(es).');
  await agregar(ddmmaaaa(feriado2), 'Feriado de prueba dos');
  await expect(exito(page)).toContainText('Se registraron 1');
  await agregar(ddmmaaaa(cierre), 'Cierre anticipado de fin de año', 'CierreEntidad');
  await expect(exito(page)).toContainText('Se registraron 1');

  await agregar(ddmmaaaa(feriado1), 'Feriado repetido');
  await expect(page.locator('main')).toContainText('ya está registrada (A2)');
  await agregar(ddmmaaaa(fecha(-9)), 'Fecha pasada');
  await expect(page.locator('main')).toContainText('es una fecha pasada (A1)');

  const carga = [
    `01/01/${anioProximo}; Año Nuevo; Feriado`,
    `15/02/${anioProximo}; Carnaval; Feriado`,
    `16/02/${anioProximo}; Carnaval; Feriado`,
    `24/03/${anioProximo}; Día Nacional de la Memoria por la Verdad y la Justicia; Feriado`,
    `02/04/${anioProximo}; Día del Veterano y de los Caídos en la Guerra de Malvinas; Feriado`,
    `01/05/${anioProximo}; Día del Trabajador; Feriado`,
    `25/05/${anioProximo}; Día de la Revolución de Mayo; Feriado`,
    `31/02/${anioProximo}; Fecha inexistente; Feriado`,
    `10/06/${anioProximo}; ; Feriado`,
  ].join('\n');
  await page.goto('/admin/calendario');
  await page.locator('#carga').fill(carga);
  await page.getByRole('button', { name: 'Registrar', exact: true }).click();
  await expect(exito(page)).toContainText('Se registraron 7 día(s)');
  await expect(page.locator('.alert-warning')).toContainText('Fechas rechazadas:');
  await expect(page.locator('.alert-warning')).toContainText('31/02');
});

test('R-045 Baja de un día no hábil y nueva carga', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  const anio = feriado2.slice(0, 4);
  await page.goto(`/admin/calendario?anio=${anio}`);
  const fila = page.locator('main table tbody tr', { hasText: ddmmaaaa(feriado2) });
  await fila.getByRole('button').click();
  await expect(exito(page)).toContainText('Día no hábil dado de baja.');
  await page.locator('#fecha').fill(ddmmaaaa(feriado2));
  await page.locator('#descripcion').fill('Feriado de prueba dos');
  await page.getByRole('button', { name: 'Registrar', exact: true }).click();
  await expect(exito(page)).toContainText('Se registraron 1');
});
