// CU-51: formulario de parámetros de campañas, compartido por los specs que registran una versión.
const { fecha } = require('./datos');

/**
 * Completa el formulario de parámetros de campañas y registra la versión.
 * `base` son los valores propios de cada prueba y `cambios` los que ese caso modifica.
 */
async function registrarParametrosCampanas(page, base, cambios = {}) {
  const p = { fecha_vigencia: fecha(), ...base, ...cambios };
  await page.goto('/admin/parametros/campanas');
  for (const k of ['umbral_disponibilidad', 'periodo_proyeccion_dias', 'factor_cobrabilidad', 'reserva_minima', 'capital_maximo', 'cantidad_maxima_ofertas', 'antiguedad_minima_meses', 'morosidad_maxima_dias', 'duracion_dias']) {
    await page.locator(`#${k}`).fill(p[k]);
  }
  if (p.linea === '') await page.locator('#linea_id').selectOption('');
  else await page.locator('#linea_id').selectOption({ label: p.linea });
  for (const caja of await page.locator('input[name="categorias_admitidas[]"]').all()) {
    await caja.setChecked(p.categorias.includes(await caja.getAttribute('value')));
  }
  await page.locator('#canal-Correo').check();
  await page.locator('#admite_obligaciones_vigentes').setChecked(p.admite_obligaciones_vigentes);
  await page.locator('#cierre_por_disponibilidad').setChecked(p.cierre_por_disponibilidad);
  await page.locator('#mensaje').fill(p.mensaje);
  await page.locator('#fecha_vigencia').fill(p.fecha_vigencia);
  await page.locator('#motivo').fill(p.motivo);
  await page.getByRole('button', { name: 'Registrar nueva versión' }).click();
}

module.exports = { registrarParametrosCampanas };
