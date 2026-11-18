// Iteración 4, bloque C. Tablero, reportes, informe de caja diaria, auditoría y régimen informativo (CU-55 a CU-57,
// CU-65 a CU-71, CU-105): I-63 a I-80 de docs/pruebas/prueba_funcional_iteracion_4.md.
const { test, expect } = require('../lib/fixtures');
const { consulta, valor, spark, cerrarBase } = require('../lib/sistema');
const { usuarios } = require('../lib/datos');

const exito = (page) => page.locator('.alert-success[role="status"]');
const REPORTES = {
  cartera: ['CU-65', 'Saldo deudor total'],
  morosidad: ['CU-66', 'Índice de morosidad (RN-74)'],
  cobranzas: ['CU-67', 'Total cobrado'],
  solicitudes: ['CU-68', 'Solicitudes registradas'],
  refinanciaciones: ['CU-69', 'Propuestas registradas'],
  proyeccion: ['CU-70', 'Disponibilidad proyectada'],
  campanas: ['CU-71', 'Ofertas emitidas'],
};
/** Ninguna pantalla muestra detalles técnicos de un error (RNF-12). */
const sinErrores = async (page, ruta) => expect(await page.locator('body').innerText(), ruta).not.toMatch(/Exception|SQLSTATE|Stack trace|Whoops/);

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);

test('I-63 Tablero gerencial (CU-55)', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  await page.goto('/admin/tablero');
  for (const grupo of ['Flujo de caja', 'Cartera crediticia', 'Morosidad', 'Cobranzas y recupero', 'Solicitudes y otorgamientos', 'Refinanciaciones', 'Campañas crediticias', 'Proyección de fondos']) {
    await expect(page.getByRole('heading', { name: grupo }), grupo).toBeVisible();
  }
  await expect(page.getByRole('link', { name: /Ver el reporte/ })).toHaveCount(8);
  // A1: un día sin operaciones
  await page.goto('/admin/tablero?tipo=dia&fecha=2020-01-05');
  await expect(page.locator('main')).toContainText('No se registraron operaciones en el período');
  // A2: rango incoherente
  await page.goto('/admin/tablero?tipo=rango&desde=2026-11-10&hasta=2026-11-01');
  await expect(page.locator('main')).toContainText('La fecha final no puede ser anterior a la inicial');
  expect(await valor("SELECT COUNT(*) FROM dbo.registros_auditoria WHERE accion = 'tablero.consultar'")).toBeGreaterThan(0);
});

test('I-64 Índice de reportes y acceso por rol', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  await page.goto('/admin/reportes');
  for (const [cu] of Object.values(REPORTES)) await expect(page.locator('main'), cu).toContainText(cu);
  const analista = await comoUsuario('analista');
  expect((await analista.goto('/admin/reportes/cartera')).status()).toBe(403);
  expect((await analista.goto('/admin/tablero')).status()).toBe(403);
});

test('I-65 a I-71 Generar cada reporte de gestión', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  for (const [clave, [cu, indicador]] of Object.entries(REPORTES)) {
    const ruta = `/admin/reportes/${clave}?generar=1`;
    expect((await page.goto(ruta)).status(), ruta).toBe(200);
    await sinErrores(page, ruta);
    await expect(page.locator('main'), clave).toContainText(cu);
    await expect(page.locator('main'), clave).toContainText(indicador);
    await expect(page.getByRole('link', { name: /Exportar a Excel/ }), clave).toBeVisible();
    expect(await valor('SELECT COUNT(*) FROM dbo.registros_auditoria WHERE accion = @a', { a: `reportes.${clave}` }), clave).toBeGreaterThan(0);
  }
});

test('I-65 y I-66 Filtros de cartera y morosidad', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  const motos = await valor("SELECT id FROM dbo.lineas_credito WHERE codigo = 'MOTOS'");
  await page.goto(`/admin/reportes/cartera?generar=1&linea=${motos}&estado=EnMora`);
  await expect(page.locator('main')).toContainText('Criterios: Línea: Motos · Estado: EnMora');
  await page.goto('/admin/reportes/cartera?generar=1&cliente=persona-que-no-existe');
  await expect(page.locator('main')).toContainText('(A1)');
  await page.goto('/admin/reportes/morosidad?generar=1&antiguedad=31-60');
  await sinErrores(page, 'morosidad 31-60');
  await expect(page.locator('main')).toContainText('Antigüedad: 31 a 60 días');
  // Solo las cuotas del tramo: ningún atraso del detalle supera los 60 días
  const atrasos = await page.locator('main section').filter({ hasText: 'Punitorios por crédito y cuota' }).locator('tbody tr td:nth-child(6)').allInnerTexts();
  for (const a of atrasos) expect(Number(a.replace(/\D/g, ''))).toBeGreaterThanOrEqual(31);
  for (const a of atrasos) expect(Number(a.replace(/\D/g, ''))).toBeLessThanOrEqual(60);
});

test('I-70 Proyección: período pasado (A2)', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  await page.goto('/admin/reportes/proyeccion?generar=1&tipo=rango&desde=2020-01-01&hasta=2020-01-31');
  await expect(page.locator('main')).toContainText('La proyección no puede empezar antes de hoy');
});

test('I-72 Exportar a Excel', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  for (const clave of Object.keys(REPORTES)) {
    const r = await page.request.get(`/admin/reportes/${clave}?generar=1&formato=xlsx`);
    expect(r.status(), clave).toBe(200);
    expect(r.headers()['content-type'], clave).toContain('spreadsheetml');
    expect(r.headers()['content-disposition'], clave).toContain(`reporte_${clave}_`);
    const cuerpo = await r.body();
    expect(cuerpo.subarray(0, 2).toString(), clave).toBe('PK');
  }
});

test('I-93 Impresión formal: cabecera, emisión y Página N de M (D-57)', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  await page.goto('/admin/reportes/cartera?generar=1');
  // Cada hoja: entidad y título arriba; emisión, usuario y «Página N de M» abajo (cajas de margen de @page)
  const estilo = await page.locator('style[media="print"]').innerHTML();
  expect(estilo).toContain('counter(page)');
  expect(estilo).toContain('counter(pages)');
  expect(estilo).toMatch(/Emitido el \d{2}\/\d{2}\/\d{4} \d{2}:\d{2} por /);
  // La primera hoja lleva la cabecera formal: solo se ve al imprimir
  const cabecera = page.locator('.cabecera-impresion');
  await expect(cabecera).toBeHidden();
  await page.emulateMedia({ media: 'print' });
  await expect(cabecera).toBeVisible();
  await expect(cabecera).toContainText('Emitido el');
  await expect(page.locator('.lateral')).toBeHidden();
  // El PDF que genera el navegador numera las hojas
  const pdf = await page.pdf({ format: 'A4' });
  expect(pdf.subarray(0, 4).toString()).toBe('%PDF');
  // El contrato tiene su propio encabezado: sin la cabecera del informe, con la cabecera y el pie de cada hoja
  const analista = await comoUsuario('analista');
  await analista.emulateMedia({ media: 'print' });
  await analista.goto(`/admin/contratos/${await valor('SELECT TOP 1 solicitud_id FROM dbo.contratos_prestamo ORDER BY id')}`);
  await expect(analista.locator('#titulo-contrato')).toBeVisible();
  await expect(analista.locator('.cabecera-impresion')).toBeHidden();
  expect(await analista.locator('style[media="print"]').innerHTML()).toContain('counter(pages)');
});

test('I-73 Informe de caja diaria como Gerente (CU-56)', async ({ comoUsuario }) => {
  const page = await comoUsuario('gerente');
  await page.goto('/admin/caja-diaria');
  await sinErrores(page, 'caja diaria');
  await expect(page.locator('main h1')).toHaveText('Informe de caja diaria');
  await expect(page.locator('#cajero')).toBeVisible();
  await page.goto('/admin/caja-diaria?desde=2020-01-06');
  await expect(page.locator('main')).toContainText('No se registraron jornadas');
});

test('I-74 Informe de caja diaria como Cajero (A2)', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  const otro = await valor('SELECT u.id FROM dbo.usuarios_internos u JOIN dbo.cuentas_acceso a ON a.id = u.id WHERE a.correo = @c', { c: usuarios.cajero2.correo });
  await page.goto(`/admin/caja-diaria?cajero=${otro}`);
  await expect(page.locator('main')).toContainText('Solo puede consultar sus propias jornadas');
  await expect(page.locator('#cajero')).toHaveCount(0);
});

test('I-75 Registro de auditoría (CU-57)', async ({ comoUsuario }) => {
  const page = await comoUsuario('admin');
  await page.goto('/admin/auditoria?categoria=automaticas');
  await sinErrores(page, 'auditoría');
  await expect(page.locator('main tbody')).toContainText('(automática)');
  await page.locator('main tbody a').first().click();
  await expect(page.locator('main')).toContainText('Proceso automático');
  await expect(page.locator('main')).toContainText('(A4)');
  // D-48: el detalle de una modificación presenta el valor anterior y el nuevo de cada campo (línea Personal, R-034)
  const modificacion = await valor("SELECT MIN(id) FROM dbo.registros_auditoria WHERE accion = 'lineas.modificar' AND JSON_VALUE(detalle, '$.cambios.tna.nuevo') = '72,00 %'");
  await page.goto(`/admin/auditoria/${modificacion}`);
  const tabla = page.getByRole('table', { name: 'Valor anterior y valor nuevo de cada campo modificado' });
  await expect(page.getByRole('heading', { name: 'Campos modificados' })).toBeVisible();
  await expect(tabla.getByRole('row', { name: /TNA\s+70,00 %\s+72,00 %/ })).toBeVisible();
  await page.goto('/admin/auditoria?responsable=nadie-con-este-nombre');
  await expect(page.locator('main')).toContainText('(A1)');
  await page.goto('/admin/auditoria?desde=2026-11-10&hasta=2026-11-01');
  await expect(page.locator('main')).toContainText('La fecha final no puede ser anterior a la inicial');
  const gerente = await comoUsuario('gerente');
  expect((await gerente.goto('/admin/auditoria')).status()).toBe(200);
  const cajero = await comoUsuario('cajero');
  expect((await cajero.goto('/admin/auditoria')).status()).toBe(403);
});

let regimenId;

test('I-76 Régimen informativo: generación automática (CU-105)', async () => {
  const salida = spark('regimen:generar');
  console.log(salida);
  // Desde el primer día hábil del mes se genera el mes anterior; el primer día del mes puede no ser hábil
  test.skip(salida.includes('No hay un período a generar'), 'Hoy todavía no es el primer día hábil del mes.');
  expect(salida).toMatch(/Régimen \d{4}-\d{2} generado: \d+ deudores\./);
  regimenId = await valor("SELECT TOP 1 id FROM dbo.regimenes_informativos WHERE estado = 'Vigente' ORDER BY id DESC");
  expect(spark('regimen:generar')).toContain('No hay un período a generar.');
  expect(await valor("SELECT COUNT(*) FROM dbo.alertas WHERE objeto_tipo = 'regimenes_informativos'")).toBeGreaterThan(0);
});

test('I-77 Consulta y descarga del archivo', async ({ comoUsuario }) => {
  test.skip(!regimenId, 'No se generó el régimen en I-76.');
  const page = await comoUsuario('gerente');
  await page.goto('/admin/regimen');
  await expect(page.locator('main')).toContainText('Vigente');
  await page.goto(`/admin/regimen/${regimenId}`);
  await expect(page.locator('main')).toContainText('Deudores por situación (RN-84)');
  const hash = await valor('SELECT hash_archivo FROM dbo.regimenes_informativos WHERE id = @id', { id: regimenId });
  await expect(page.locator('main')).toContainText(hash);
  const r = await page.request.get(`/admin/regimen/${regimenId}/archivo`);
  expect(r.status()).toBe(200);
  const texto = (await r.body()).toString('utf8');
  expect(texto).toMatch(/^CAB;\d{4}-\d{2};1;\d{4}-\d{2}-\d{2};\d+;[\d.]+\r\n/);
  expect(require('node:crypto').createHash('sha256').update(await r.body()).digest('hex')).toBe(hash.trim());
});

test('I-78 Rectificación y período abierto', async ({ comoUsuario }) => {
  test.skip(!regimenId, 'No se generó el régimen en I-76.');
  const page = await comoUsuario('gerente');
  const periodo = await valor('SELECT periodo FROM dbo.regimenes_informativos WHERE id = @id', { id: regimenId });
  const generar = async (p, motivo) => {
    await page.goto('/admin/regimen');
    await page.locator('#periodo').fill(p);
    await page.locator('#motivo').fill(motivo);
    await page.getByRole('button', { name: 'Generar o rectificar' }).click();
  };
  await generar(periodo, '');
  await expect(page.locator('main')).toContainText('Indique el motivo de la rectificación.');
  await generar(periodo, 'Prueba de rectificación');
  await expect(exito(page)).toContainText('Rectificación registrada (versión 2)');
  expect(await valor('SELECT estado FROM dbo.regimenes_informativos WHERE id = @id', { id: regimenId })).toBe('Rectificado');
  const mesActual = new Date().toLocaleDateString('sv-SE', { timeZone: 'America/Argentina/Buenos_Aires' }).slice(0, 7);
  await generar(mesActual, 'Mes en curso');
  await expect(page.locator('main')).toContainText('Seleccione un mes ya cerrado.');
});

test('I-80 Auditoría del bloque C', async () => {
  const acciones = (await consulta('SELECT DISTINCT accion FROM dbo.registros_auditoria')).map((f) => f.accion);
  for (const a of ['tablero.consultar', 'reportes.cartera', 'reportes.proyeccion', 'cajas.informe-diario', 'auditoria.consultar']) expect(acciones, a).toContain(a);
});
