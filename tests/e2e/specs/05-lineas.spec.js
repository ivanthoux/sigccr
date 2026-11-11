// Parte 1, sección 5. Líneas de crédito (CU-16 a CU-20, CU-73, D-23 y D-28): R-032 a R-035.
const { test, expect } = require('../lib/fixtures');
const { lineas, fecha } = require('../lib/datos');
const { valor, cerrarBase } = require('../lib/sistema');

const exito = (page) => page.locator('.alert-success[role="status"]');
const error = (page, campo) => page.locator(`#error-${campo}`);
const idLinea = (codigo) => valor('SELECT id FROM dbo.lineas_credito WHERE codigo = @c', { c: codigo });

async function registrarLinea(page, linea, cambios = {}) {
  const l = { ...linea, ...cambios };
  await page.goto('/admin/lineas/nueva');
  await page.locator('#codigo').fill(l.codigo);
  await page.locator('#nombre').fill(l.nombre);
  await page.locator('#descripcion').fill(l.descripcion);
  await page.locator('#monto_minimo').fill(l.montoMin);
  await page.locator('#monto_maximo').fill(l.montoMax);
  await page.locator('#cuotas_minimas').fill(l.cuotasMin);
  await page.locator('#cuotas_maximas').fill(l.cuotasMax);
  await page.locator('#tna').fill(l.tna);
  await page.locator('#gasto_otorgamiento_pct').fill(l.otorgamiento);
  await page.locator(`#tipo-gasto-${l.gastoTipo}`).check();
  await page.locator(l.gastoTipo === 'Fijo' ? '#gasto_administrativo_cuota' : '#gasto_administrativo_pct').fill(l.gasto);
  await page.locator('#admite_persona_humana').setChecked(l.humana);
  await page.locator('#admite_persona_juridica').setChecked(l.juridica);
  if (l.humana) {
    await page.locator('#edad_minima').fill(l.edadMin);
    await page.locator('#edad_maxima').fill(l.edadMax);
  }
  if (l.juridica) await page.locator('#antiguedad_minima_meses').fill(l.antiguedad);
  for (const cat of l.categorias) await page.locator(`#cat-${cat}`).check();
  await page.locator('#vigencia_inicio').fill(fecha());
  await page.locator('#vigencia_fin').fill(fecha(730));
  await page.getByRole('button', { name: 'Registrar línea' }).click();
}

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);

test('R-032 Validaciones de la línea (RN-14, D-23, D-28)', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  const motos = lineas.L01;
  await registrarLinea(page, motos, { montoMin: '500000', montoMax: '500000' });
  await expect(error(page, 'monto_maximo')).toContainText('El importe máximo debe ser mayor que el mínimo (RN-14, D-23).');
  await registrarLinea(page, motos, { cuotasMin: '12', cuotasMax: '12' });
  await expect(error(page, 'cuotas_maximas')).toBeVisible();
  await registrarLinea(page, motos, { edadMin: '40', edadMax: '40' });
  await expect(error(page, 'edad_maxima')).toBeVisible();
  await registrarLinea(page, motos, { gastoTipo: 'Porcentual', gasto: '15' });
  await expect(error(page, 'gasto_administrativo_pct')).toBeVisible();
  expect(await valor('SELECT COUNT(*) FROM dbo.lineas_credito')).toBe(0);
});

test('R-033 Registrar las tres líneas', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  for (const linea of Object.values(lineas)) {
    await registrarLinea(page, linea);
    await expect(exito(page), `alta de ${linea.codigo}`).toContainText('Línea registrada con su versión 1 de condiciones');
    if (linea.codigo === 'PERSONAL') await expect(page.locator('main')).toContainText('0,50 % del capital por cuota');
  }
});

test('R-034 Modificar la TNA con una versión nueva', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  const id = await idLinea('PERSONAL');
  const modificar = async (vigencia) => {
    await page.goto(`/admin/lineas/${id}/editar`);
    await page.locator('#tna').fill('72');
    await page.locator('#fecha_vigencia').fill(vigencia);
    await page.locator('#motivo').fill('Actualización de la tasa de mercado');
    await page.getByRole('button', { name: 'Registrar nueva versión' }).click();
  };
  await modificar(fecha(-1));
  await expect(error(page, 'fecha_vigencia')).toBeVisible();
  await modificar(fecha());
  await expect(exito(page)).toBeVisible();
  expect(await valor('SELECT COUNT(*) FROM dbo.versiones_condiciones WHERE linea_credito_id = @id', { id })).toBe(2);
});

test('R-035 Suspender, consultar y reactivar; el Analista solo consulta', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  const id = await idLinea('PYME-CT');
  const operar = async (operacion, motivo) => {
    await page.goto(`/admin/lineas/${id}/${operacion}`);
    await page.locator('#motivo').fill(motivo);
    await page.locator('main form button[type="submit"]').click();
  };
  await operar('suspender', 'Suspensión temporal por revisión de la cartera');
  await expect(exito(page)).toBeVisible();
  await expect(page.locator('main')).toContainText('Suspendida');
  await operar('reactivar', 'Fin de la revisión de la cartera');
  await expect(exito(page)).toContainText('Línea reactivada con la versión de condiciones vigente.');

  const analista = await comoUsuario('analista');
  await analista.goto(`/admin/lineas/${id}`);
  await expect(analista.locator('main')).toContainText('Capital de trabajo Pyme');
  await expect(analista.locator(`a[href$="/admin/lineas/${id}/editar"]`)).toHaveCount(0);
  const respuesta = await analista.goto(`/admin/lineas/${id}/editar`);
  expect(respuesta.status()).toBe(403);
});
