// Parte 2, sección 7. Parámetros y calendario (CU-58, CU-59, CU-60 y CU-82): R-041 a R-045.
const { test, expect } = require('../lib/fixtures');
const { parametros, fecha, ddmmaaaa } = require('../lib/datos');
const { valor, cerrarBase } = require('../lib/sistema');

const exito = (page) => page.locator('.alert-success');
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

// Fechas relativas a hoy, para que la prueba no dependa del día en que se ejecuta
const feriado1 = fecha(28);
const feriado2 = fecha(45);
const cierre = fecha(44);
const anioProximo = Number(fecha().slice(0, 4)) + 1;

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
