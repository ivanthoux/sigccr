// Formulario de clientes (CU-11) para las pruebas: completa el alta con los datos del Anexo A.
const { expect } = require('@playwright/test');
const { documento } = require('./archivos');

const DOCUMENTOS_POR_CATEGORIA = {
  RelacionDependencia: ['RECIBO_SUELDO'],
  JubiladoPensionado: ['RECIBO_HABERES'],
  Monotributista: ['CONST_MONOTRIBUTO', 'PAGOS_MONOTRIBUTO'],
  Empresa: ['ESTATUTO', 'CONST_CUIT', 'ESTADOS_CONTABLES', 'PODER_REPRESENTACION'],
};

/** Documentos de un cliente: identificación, los de su categoría y la declaración PEP, como los archivos del Anexo B. */
function documentosDe(c) {
  const sufijo = c.apellido || c.cuit;
  const docs = { DOC_IDENTIDAD: [documento(`DNI_${c.tipo === 'PersonaJuridica' ? 'representante_' + c.cuit : sufijo}.pdf`)] };
  for (const codigo of DOCUMENTOS_POR_CATEGORIA[c.categoria || 'Empresa']) docs[codigo] = [documento(`${codigo.toLowerCase()}_${sufijo}.pdf`)];
  // D-55: la declaración PEP firmada se adjunta en el alta, salvo en los clientes que la incorporan después (sinDeclaracionPep)
  if (!c.sinDeclaracionPep) docs.DDJJ_PEP = [documento(`ddjj_pep_${sufijo}.pdf`)];
  return docs;
}

async function elegirDomicilio(page, domicilio) {
  await page.locator('#provincia_id').selectOption({ label: 'Misiones' });
  await expect(page.locator('#localidad_id option', { hasText: /^Apóstoles$/ })).toHaveCount(1);
  await page.locator('#localidad_id').selectOption({ label: 'Apóstoles' });
  await page.locator('#codigo_postal').fill('3350');
  await page.locator('#domicilio').fill(domicilio);
}

/**
 * Completa y envía el alta. `cambios` reemplaza datos del cliente (por ejemplo { nacionalidad: '' } para omitirla);
 * `documentos` reemplaza los archivos; `sinPolitica` deja sin aceptar la política de datos.
 */
async function registrarCliente(page, cliente, { cambios = {}, documentos, sinPolitica = false } = {}) {
  const c = { ...cliente, ...cambios };
  await page.goto('/admin/clientes/nuevo');
  await page.locator(`label[for="tipo-${c.tipo}"]`).click();
  if (c.tipo === 'PersonaHumana') {
    await page.locator('#nombre').fill(c.nombre);
    await page.locator('#apellido').fill(c.apellido);
    await page.locator('#nro_documento').fill(c.dni);
    await page.locator('#fecha_nacimiento').fill(c.nacimiento);
    await page.locator('#estado_civil').selectOption(c.estadoCivil);
    await page.locator('#nacionalidad').fill(c.nacionalidad);
    await page.locator('#ocupacion').fill(c.ocupacion);
    await page.locator('#empleador').fill(c.empleador);
    await page.locator('#categoria_ingresos').selectOption(c.categoria);
    if (c.categoria === 'Monotributista') await page.locator('#categoria_monotributo').selectOption(c.monotributo || '');
  } else {
    await page.locator('#razon_social').fill(c.razonSocial);
    await page.locator('#fecha_constitucion').fill(c.constitucion);
    await page.locator('#actividad_principal').fill(c.actividad);
    await page.locator('#representante_nombre').fill(c.representante);
    await page.locator('#representante_documento').fill(c.representanteDni);
    await page.locator('#representante_cargo').fill(c.cargo);
  }
  await page.locator('#cuit_cuil').fill(c.cuil || c.cuit || '');
  await page.locator('#correo').fill(c.correo);
  await page.locator('#telefono').fill(c.telefono);
  await elegirDomicilio(page, c.domicilio);
  await page.locator('#ingresos_declarados').fill(c.ingresos);
  if (c.pep !== null) await page.locator(c.pep ? '#pep-si' : '#pep-no').check();
  if (!sinPolitica) await page.locator('#acepta_politica').check();
  await page.locator('#acepta_comunicaciones').setChecked(c.comunicaciones);
  for (const [codigo, archivos] of Object.entries(documentos || documentosDe(c))) {
    await page.locator(`#doc-${codigo}`).setInputFiles(archivos);
  }
  await page.getByRole('button', { name: 'Registrar cliente' }).click();
}

module.exports = { registrarCliente, documentosDe, elegirDomicilio };
