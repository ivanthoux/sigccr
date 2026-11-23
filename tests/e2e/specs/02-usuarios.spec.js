// Parte 1, sección 2. Usuarios internos (CU-05 a CU-10 y CU-53): R-008 a R-014.
const { test, expect, entrar, olvidarSesion } = require('../lib/fixtures');
const { usuarios, internos } = require('../lib/datos');
const { ultimoCorreo, consulta, valor, cerrarBase } = require('../lib/sistema');

const { admin, gerente, cajero2, cajero3 } = usuarios;
const exito = (page) => page.locator('.alert-success[role="status"]');
const idDe = (u) => valor('SELECT id FROM dbo.cuentas_acceso WHERE correo = @c', { c: u.correo });
const estadoDe = (u) => valor('SELECT estado FROM dbo.cuentas_acceso WHERE correo = @c', { c: u.correo });
const cantidadUsuarios = () => valor('SELECT COUNT(*) FROM dbo.usuarios_internos');

/** Completa el alta (CU-05) y la envía. */
async function registrar(page, u) {
  await page.goto('/admin/usuarios/nuevo');
  await page.locator('#nombre').fill(u.nombre);
  await page.locator('#apellido').fill(u.apellido);
  await page.locator('#nro_documento').fill(u.dni);
  await page.locator('#correo').fill(u.correo);
  if (u.rol) await page.locator(`label[for="rol-${u.rol}"]`).click();
  await page.getByRole('button', { name: 'Registrar usuario' }).click();
}

/** Confirmación con motivo de una operación sobre un usuario (desactivar, reactivar). */
async function operar(page, id, operacion, motivo) {
  await page.goto(`/admin/usuarios/${id}/${operacion}`);
  await page.locator('#motivo').fill(motivo);
  await page.locator('main form button[type="submit"]').click();
}

async function asignarRol(page, id, rol, motivo) {
  await page.goto(`/admin/usuarios/${id}/rol`);
  await page.locator(`label[for="rol-${rol}"]`).click();
  await page.locator('#motivo').fill(motivo);
  await page.getByRole('button', { name: 'Asignar rol' }).click();
}

test.describe.configure({ mode: 'serial' });
test.afterAll(cerrarBase);

test('R-008 Registrar los cinco usuarios internos', async ({ comoUsuario }) => {
  const page = await comoUsuario('admin');
  for (const clave of internos) {
    const u = usuarios[clave];
    await registrar(page, u);
    await expect(exito(page)).toContainText('Usuario registrado en estado Pendiente de activación');
    await expect(page.locator('main')).toContainText(u.rolNombre);
    expect(await estadoDe(u)).toBe('PendienteActivacion');
    const correo = ultimoCorreo(u.correo);
    expect(correo?.enlace, `enlace de activación para ${u.correo}`).toBeTruthy();
  }
});

test('R-009 Validaciones del alta', async ({ comoUsuario }) => {
  const page = await comoUsuario('admin');
  const antes = await cantidadUsuarios();
  const nuevo = { nombre: 'Laura', apellido: 'Prueba', dni: '26111223', correo: 'laura.prueba@prueba.local', rol: 'Gerente' };
  const casos = [
    [{ nombre: '' }, 'nombre'],
    [{ correo: 'laura@' }, 'correo'],
    [{ dni: '26A11222' }, 'nro_documento'],
    [{ dni: '26111222.' }, 'nro_documento'],
    [{ correo: gerente.correo }, 'correo'],
    [{ dni: gerente.dni }, 'nro_documento'],
  ];
  for (const [cambio, campo] of casos) {
    await registrar(page, { ...nuevo, ...cambio });
    await expect(page.locator(`#error-${campo}`), `error en ${campo} con ${JSON.stringify(cambio)}`).toBeVisible();
    await expect(page.locator(`#${campo}`)).toHaveClass(/is-invalid/);
  }
  expect(await cantidadUsuarios()).toBe(antes);
});

test('R-010 Definición inicial de la contraseña y menú de cada rol', async ({ browser }) => {
  for (const clave of internos) {
    const u = usuarios[clave];
    const contexto = await browser.newContext();
    const page = await contexto.newPage();
    await page.goto(ultimoCorreo(u.correo, { enviar: false }).enlace);
    await page.locator('#contrasena').fill(u.clave);
    await page.locator('#confirmacion').fill(u.clave);
    await page.getByRole('button', { name: 'Guardar contraseña' }).click();
    await expect(exito(page)).toBeVisible();
    await entrar(page, u);
    await expect(page.locator('.cuenta')).toContainText(u.rolNombre);
    const menu = page.locator('nav.menu-lateral');
    if (u.rol === 'Administrador') await expect(menu).toContainText('Usuarios');
    else await expect(menu).not.toContainText('Usuarios');
    expect(await estadoDe(u)).toBe('Habilitada');
    await contexto.close();
  }
});

test('R-011 Localizar y edición simultánea', async ({ comoUsuario }) => {
  const page = await comoUsuario('admin');
  const filas = page.locator('main table tbody tr');

  await page.goto('/admin/usuarios?texto=Silva');
  await expect(filas).toHaveCount(1);
  await expect(filas.first()).toContainText(cajero2.correo);
  // D-64 (ASVS V14.2.1): el documento se busca por POST y no queda en la dirección
  await page.goto('/admin/usuarios');
  await page.locator('#documento').fill(gerente.dni);
  await page.getByRole('button', { name: 'Buscar' }).click();
  expect(page.url()).not.toContain(gerente.dni);
  await expect(filas).toHaveCount(1);
  await expect(filas.first()).toContainText('Pérez');
  await page.goto('/admin/usuarios?rol=Cajero');
  await expect(filas).toHaveCount(3);
  await page.goto('/admin/usuarios?estado=Habilitada');
  await expect(filas).toHaveCount(6);

  const id = await idDe(cajero2);
  const otra = await page.context().newPage();
  await page.goto(`/admin/usuarios/${id}/editar`);
  await otra.goto(`/admin/usuarios/${id}/editar`);
  await page.locator('#nombre').fill('Tomás Martín');
  await page.getByRole('button', { name: 'Guardar cambios' }).click();
  await expect(exito(page)).toContainText('Datos actualizados.');
  await otra.locator('#nombre').fill('Tomás Ariel');
  await otra.getByRole('button', { name: 'Guardar cambios' }).click();
  await expect(otra.locator('main')).toContainText('fue modificado por otra operación');
  await otra.close();

  await page.goto(`/admin/usuarios/${id}/editar`);
  await page.locator('#nombre').fill(cajero2.nombre);
  await page.getByRole('button', { name: 'Guardar cambios' }).click();
  await expect(exito(page)).toContainText('Datos actualizados.');
});

test('R-012 Asignar rol con la sesión del usuario abierta', async ({ comoUsuario }) => {
  const tomas = await comoUsuario('cajero2');
  await expect(tomas.locator('.cuenta')).toContainText('Cajero');
  const page = await comoUsuario('admin');
  const id = await idDe(cajero2);

  await asignarRol(page, id, 'AnalistaCredito', 'Cobertura temporal de análisis');
  await expect(exito(page)).toContainText('Rol actualizado.');
  await tomas.reload();
  await expect(tomas).toHaveURL(/\/admin\/login/);
  await entrar(tomas, cajero2);
  await expect(tomas.locator('.cuenta')).toContainText('Analista de Crédito');

  await asignarRol(page, id, 'Cajero', 'Fin de la cobertura temporal');
  await expect(exito(page)).toContainText('Rol actualizado.');
  await tomas.reload();
  await expect(tomas).toHaveURL(/\/admin\/login/);
  await entrar(tomas, cajero2);
  await expect(tomas.locator('.cuenta')).toContainText('Cajero');
  olvidarSesion(cajero2);
});

test('R-013 Desactivar y reactivar', async ({ comoUsuario, browser }) => {
  const page = await comoUsuario('admin');
  const idRocio = await idDe(cajero3);

  await operar(page, idRocio, 'desactivar', 'Licencia prolongada');
  await expect(exito(page)).toContainText('Usuario desactivado.');
  const otro = await (await browser.newContext()).newPage();
  await entrar(otro, cajero3);
  await expect(otro).toHaveURL(/\/admin\/login/);

  await operar(page, await idDe(admin), 'desactivar', 'Prueba de desactivarse a sí mismo');
  await expect(page.locator('.alert-danger, .invalid-feedback').first()).toBeVisible();
  expect(await estadoDe(admin)).toBe('Habilitada');

  await operar(page, idRocio, 'reactivar', 'Regreso de la licencia');
  await expect(exito(page)).toContainText('Usuario reactivado: ya puede iniciar sesión.');
  await entrar(otro, cajero3);
  await expect(otro.locator('.cuenta')).toContainText('Cajero');
  await otro.context().close();
});

test('R-014 Sin permiso: el Cajero no accede a usuarios', async ({ comoUsuario }) => {
  const page = await comoUsuario('cajero');
  const respuesta = await page.goto('/admin/usuarios');
  expect(respuesta.status()).toBe(403);
  await expect(page.locator('main')).toContainText('Acceso no autorizado');
  await expect(page.locator('main')).not.toContainText(gerente.correo);
});
