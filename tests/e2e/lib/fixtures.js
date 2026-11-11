// Extensión de @playwright/test: oculta la barra de depuración de CodeIgniter y ofrece sesiones por rol.
const base = require('@playwright/test');
const { usuarios } = require('./datos');

// La barra de depuración (entorno development) se superpone al pie de las pantallas.
const ocultarBarra = () => {
  const aplicar = () => {
    const s = document.createElement('style');
    s.textContent = '#debug-bar, #debug-icon, #toolbarContainer { display: none !important; }';
    document.head?.appendChild(s);
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', aplicar);
  else aplicar();
};

/** Estado de sesión guardado por correo, para no repetir el inicio de sesión (RN-62 limita los intentos por origen). */
const sesiones = new Map();

/** CU-01 en el panel: completa el formulario e inicia sesión. */
async function entrar(page, usuario, clave = usuario.clave) {
  await page.goto('/admin/login');
  await page.locator('#correo').fill(usuario.correo);
  await page.locator('#contrasena').fill(clave);
  await page.getByRole('button', { name: 'Iniciar sesión' }).click();
}

/** Ante una falla, deja en la salida la dirección y el texto visible de cada página abierta (el informe tiene las capturas). */
async function diagnosticar(paginas, testInfo) {
  if (testInfo.status === testInfo.expectedStatus) return;
  for (const p of paginas) {
    if (p.isClosed()) continue;
    const texto = await p.locator('body').innerText({ timeout: 2000 }).catch(() => '(sin texto)');
    console.log(`\n--- Página al fallar ${testInfo.title}: ${p.url()}\n${texto.replace(/\n{2,}/g, '\n').slice(0, 2500)}\n---`);
  }
}

/** Acepta las confirmaciones del navegador (data-confirmar: cobros, cambios de estado, anulaciones). */
const aceptarDialogos = (contexto) => {
  contexto.on('page', (p) => p.on('dialog', (d) => d.accept()));
  contexto.pages().forEach((p) => p.on('dialog', (d) => d.accept()));
};

const test = base.test.extend({
  context: async ({ context }, use) => {
    await context.addInitScript(ocultarBarra);
    aceptarDialogos(context);
    await use(context);
  },
  page: async ({ page }, use, testInfo) => {
    await use(page);
    await diagnosticar(page.context().pages(), testInfo);
  },
  /**
   * Abre una página con la sesión del usuario indicado (clave de usuarios en datos.js o el objeto).
   * Reutiliza la sesión guardada; si expiró o se cerró, vuelve a ingresar.
   */
  comoUsuario: async ({ browser }, use, testInfo) => {
    const abiertos = [];
    await use(async (u) => {
      const usuario = typeof u === 'string' ? usuarios[u] : u;
      const contexto = await browser.newContext({ storageState: sesiones.get(usuario.correo) });
      await contexto.addInitScript(ocultarBarra);
      aceptarDialogos(contexto);
      abiertos.push(contexto);
      const page = await contexto.newPage();
      await page.goto('/admin');
      if (page.url().includes('/admin/login')) {
        await entrar(page, usuario);
        await base.expect(page.locator('.cuenta .nombre')).toBeVisible();
        sesiones.set(usuario.correo, await contexto.storageState());
      }
      return page;
    });
    await diagnosticar(abiertos.flatMap((c) => c.pages()), testInfo);
    for (const c of abiertos) await c.close();
  },
});

/** Olvida la sesión guardada de un usuario (después de cerrarla, cambiarle el rol o desactivarlo). */
function olvidarSesion(usuario) {
  sesiones.delete(usuario.correo);
}

/**
 * Envía un formulario con el botón indicado y, si el servidor pide confirmar el cambio por las solicitudes en trámite
 * (D-24), marca la confirmación y lo vuelve a enviar.
 */
async function enviarConfirmando(page, boton) {
  await page.getByRole('button', { name: boton }).click();
  await page.waitForLoadState();
  if (await page.locator('#confirmar_cambio').count()) {
    await page.locator('#confirmar_cambio').check();
    await page.getByRole('button', { name: boton }).click();
    await page.waitForLoadState();
  }
}

module.exports = { test, expect: base.expect, entrar, olvidarSesion, enviarConfirmando };
