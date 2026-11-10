// Parte 1, secciones 5 (catálogos: CU-100 y CU-101) y 6 (comunicaciones y alertas: CU-83 y CU-95): R-036 a R-040.
const { test, expect } = require('../lib/fixtures');
const { clientes, fecha } = require('../lib/datos');
const { spark, consulta, valor, cerrarBase } = require('../lib/sistema');

const exito = (page) => page.locator('.alert-success');
const error = (page, campo) => page.locator(`#error-${campo}`);
const TOPES = { A: '13000000', B: '19000000', C: '26500000', D: '33000000', E: '39000000', F: '48500000', G: '58000000', H: '88000000', I: '98500000', J: '113000000', K: '136000000' };

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);

test('R-036 Documentación exigible (CU-100)', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  await page.goto('/admin/documentacion');
  await page.getByRole('button', { name: 'Nuevo tipo de documento' }).click();
  await page.locator('#nuevo-tipo #nombre').fill('Certificado de servicios del empleador');
  await page.locator('#nuevo-tipo #aplica_a').selectOption('PersonaHumana');
  await page.getByRole('button', { name: 'Registrar tipo' }).click();
  await expect(exito(page)).toBeVisible();
  const [tipo] = await consulta("SELECT id, codigo, activo FROM dbo.tipos_documentacion WHERE nombre = 'Certificado de servicios del empleador'");
  expect(tipo.activo).toBe(true);

  await page.locator(`#sug-RelacionDependencia-${tipo.id}`).check();
  await page.getByRole('button', { name: 'Guardar la documentación propuesta' }).click();
  await expect(exito(page)).toBeVisible();
  expect(await valor(
    "SELECT COUNT(*) FROM dbo.documentacion_sugerida WHERE categoria_ingresos = 'RelacionDependencia' AND tipo_documentacion_id = @id",
    { id: tipo.id },
  )).toBe(1);

  // El documento de identidad no se puede desactivar (D-14)
  const idIdentidad = await valor("SELECT id FROM dbo.tipos_documentacion WHERE codigo = 'DOC_IDENTIDAD'");
  const casilla = page.locator(`#activo-${idIdentidad}`);
  if (await casilla.isEnabled()) {
    await casilla.uncheck();
    await casilla.locator('xpath=ancestor::form').getByRole('button', { name: 'Guardar' }).click();
  }
  expect(await valor("SELECT activo FROM dbo.tipos_documentacion WHERE codigo = 'DOC_IDENTIDAD'")).toBe(true);
});

test('R-037 Escala del monotributo (CU-101)', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  const registrar = async (desde, cambios = {}) => {
    await page.goto('/admin/monotributo');
    await page.locator('#vigente_desde').fill(desde);
    for (const [c, tope] of Object.entries({ ...TOPES, ...cambios })) await page.locator(`#tope_${c}`).fill(tope);
    await page.getByRole('button', { name: 'Registrar escala' }).click();
  };
  await registrar(fecha(-1));
  await expect(error(page, 'vigente_desde')).toBeVisible();
  await registrar('2027-02-01', { B: '12000000' });
  await expect(error(page, 'tope_B')).toBeVisible();
  await registrar('2027-02-01');
  await expect(exito(page)).toBeVisible();
  await expect(page.locator('main')).toContainText('Próxima');
});

test('R-038 Comunicación fallida, reintentos e incierta (CU-95)', async () => {
  const { C07: definitiva, C08: transitoria, C09: incierta } = clientes;
  // Los reintentos se programan a 1, 3 y 5 minutos: se adelantan para no esperarlos
  for (let i = 0; i < 5; i++) {
    await consulta("UPDATE dbo.comunicaciones SET programada_para = DATEADD(SECOND, -5, SYSDATETIMEOFFSET()) WHERE estado = 'ReintentoProgramado'");
    spark('comunicaciones:enviar');
  }
  const estado = (destino) => valor('SELECT TOP 1 estado FROM dbo.comunicaciones WHERE destino = @d ORDER BY id DESC', { d: destino });
  expect(await estado(definitiva.correo)).toBe('Fallida');
  expect(await estado(transitoria.correo)).toBe('Fallida');
  expect(await valor('SELECT TOP 1 intentos FROM dbo.comunicaciones WHERE destino = @d ORDER BY id DESC', { d: transitoria.correo })).toBe(4);
  expect(await estado(incierta.correo)).toBe('EnRevision');
  for (const c of [definitiva, transitoria, incierta]) {
    const cantidad = await valor("SELECT COUNT(*) FROM dbo.alertas WHERE causa LIKE '%' + @d + '%'", { d: c.correo });
    expect(cantidad, `alerta por ${c.correo}`).toBeGreaterThanOrEqual(1);
  }
});

let urlAlerta;

test('R-039 Consultar alertas', async ({ comoUsuario }) => {
  const page = await comoUsuario('admin');
  await page.goto(`/admin/alertas?estado=Todas&desde=${fecha()}&hasta=${fecha()}`);
  const filas = page.locator('table tbody tr');
  await expect(filas.first()).toBeVisible();
  const enlace = filas.first().getByRole('link').first();
  urlAlerta = await enlace.getAttribute('href');
  await enlace.click();
  await expect(page.locator('main')).toContainText('Acción pendiente');
  const id = Number(urlAlerta.split('/').pop());
  expect(await valor(
    "SELECT COUNT(*) FROM dbo.alertas_vistas v JOIN dbo.cuentas_acceso c ON c.id = v.usuario_id WHERE v.alerta_id = @id AND c.correo = 'admin@prueba.local'",
    { id },
  )).toBe(1);
});

test('R-040 Alerta de otro rol', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  await page.goto(urlAlerta);
  await expect(page.locator('body')).toContainText('La alerta no existe o no está dirigida a usted.');
});
