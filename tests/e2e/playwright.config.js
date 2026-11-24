// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * Pruebas de punta a punta del SIG-CCR. Recorren en orden el circuito de la prueba funcional integral
 * (docs/pruebas/prueba_funcional_integral_1_a_4.md): cada archivo continúa con los datos del anterior, por eso se
 * ejecutan en serie con un solo proceso. Cada prueba lleva en el título el identificador del caso (R-xxx).
 */
module.exports = defineConfig({
  testDir: './specs',
  outputDir: './resultados',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 90_000,
  expect: { timeout: 10_000 },
  reporter: [
    ['list'],
    ['html', { outputFolder: './informe', open: 'never' }],
    ['junit', { outputFile: './resultados/junit.xml' }],
  ],
  use: {
    baseURL: 'https://localhost',
    ignoreHTTPSErrors: true,
    locale: 'es-AR',
    timezoneId: 'America/Argentina/Buenos_Aires',
    screenshot: 'on',
    trace: 'retain-on-failure',
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1366, height: 900 } } }],
});
