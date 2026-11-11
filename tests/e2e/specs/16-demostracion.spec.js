// Datos de demostración de la morosidad (DemostracionSeeder): R-148 a R-150. Se ejecuta al final de la batería, sobre
// la base que dejó la prueba integral, igual que lo haría el usuario sobre su instalación.
const { test, expect } = require('../lib/fixtures');
const { consulta, sembrar, cerrarBase } = require('../lib/sistema');

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);

const CLIENTES = {
  '27412223050': { atraso: -20, estado: 'Activo' },
  '20381114040': { atraso: 2, estado: 'EnMora' },
  '27395551030': { atraso: 4, estado: 'EnMora' },
  '20364442050': { atraso: 5, estado: 'EnMora' },
  '27407773050': { atraso: 15, estado: 'EnMora' },
  '20356664060': { atraso: 45, estado: 'EnMora' },
  '27378885170': { atraso: 75, estado: 'EnMora' },
  '20349996090': { atraso: 130, estado: 'EnMora' },
};

const creditos = () =>
  consulta(
    `SELECT c.cuit_cuil, k.id, k.estado, DATEDIFF(day, q.vencimiento, CAST(SYSDATETIMEOFFSET() AT TIME ZONE 'Argentina Standard Time' AS date)) AS atraso,
            q.punitorio_pendiente, (SELECT COUNT(*) FROM dbo.cuotas v WHERE v.credito_id = k.id AND v.estado = 'Vencida') AS vencidas,
            (SELECT COUNT(*) FROM dbo.tramos_punitorios t WHERE t.cuota_id = q.id) AS tramos
       FROM dbo.creditos k JOIN dbo.clientes c ON c.id = k.cliente_id JOIN dbo.cuotas q ON q.credito_id = k.id AND q.numero = 1
      WHERE c.correo LIKE '%@demo.local'`,
  );

test('R-148 El seeder de demostración carga líneas, clientes y créditos liquidados', async () => {
  const salida = sembrar('DemostracionSeeder');
  expect(salida).toContain('Datos de demostración cargados');
  const lineas = await consulta("SELECT codigo FROM dbo.lineas_credito WHERE codigo IN ('ELECTRO', 'HOGAR', 'MOTOS', 'PERSONAL') AND estado = 'Activa'");
  expect(lineas).toHaveLength(4);
  expect(await creditos()).toHaveLength(8);
});

test('R-149 Cada crédito muestra el atraso y los punitorios de su etapa de morosidad', async () => {
  for (const c of await creditos()) {
    const esperado = CLIENTES[c.cuit_cuil];
    expect(esperado, c.cuit_cuil).toBeTruthy();
    expect(c.atraso, c.cuit_cuil).toBe(esperado.atraso);
    expect(c.estado, c.cuit_cuil).toBe(esperado.estado);
    // El proceso nocturno calcula hasta ayer: un tramo por día de atraso menos el de hoy
    expect(c.tramos, c.cuit_cuil).toBe(Math.max(esperado.atraso - 1, 0));
    // Superada la tolerancia de 3 días al cierre de ayer, los punitorios son exigibles desde el primer día (RN-53)
    expect(Number(c.punitorio_pendiente) > 0, c.cuit_cuil).toBe(esperado.atraso - 1 > 3);
    if (esperado.atraso >= 45) expect(c.vencidas, c.cuit_cuil).toBeGreaterThan(1);
  }
});

test('R-150 Una segunda ejecución no duplica los datos', async ({ comoUsuario }) => {
  expect(sembrar('DemostracionSeeder')).toContain('ya están cargados');
  expect(await creditos()).toHaveLength(8);
  const page = await comoUsuario('cajero');
  // La pantalla de cobro devenga hasta hoy (D-33): el crédito de 130 días muestra punitorios exigibles
  const id = (await consulta("SELECT id FROM dbo.clientes WHERE cuit_cuil = '20349996090'"))[0].id;
  await page.goto(`/admin/cobros?cliente=${id}`);
  await expect(page.locator('main')).toContainText('Suárez');
  await expect(page.locator('main')).toContainText('Punitorio');
});
