// Acceso a la pila de pruebas fuera del navegador: comandos de spark, correos simulados y consultas a la base.
const { execFileSync } = require('node:child_process');
const sql = require('mssql');

const proyecto = process.env.E2E_PROYECTO || 'sigccr_e2e';
const contenedorApp = `${proyecto}-app-1`;

/** Ejecuta un comando dentro del contenedor app como www-data (dueño de writable/). */
function enApp(...args) {
  return execFileSync('docker', ['exec', '-u', 'www-data', '-w', '/var/www/html', contenedorApp, ...args], {
    encoding: 'utf8',
    maxBuffer: 20 * 1024 * 1024,
  });
}

/** php spark <comando> (por ejemplo, un proceso del Temporizador sin esperar a cron). */
function spark(...args) {
  return enApp('php', 'spark', ...args);
}

/** Decodifica las entidades HTML con que esc(…, 'attr') escribe el enlace (&#x3A; por «:», &amp; por «&»). */
function decodificar(texto) {
  return texto
    .replace(/&#x([0-9a-f]+);/gi, (_, h) => String.fromCodePoint(parseInt(h, 16)))
    .replace(/&#([0-9]+);/g, (_, d) => String.fromCodePoint(Number(d)))
    .replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
}

/**
 * Último correo simulado enviado a un destinatario. Antes procesa la cola (CU-95), como haría el Temporizador.
 * @returns {{ asunto: string, html: string, enlace: string|null }|null}
 */
function ultimoCorreo(destino, { enviar = true } = {}) {
  if (enviar) spark('comunicaciones:enviar');
  const archivo = enApp('sh', '-c', `ls -t writable/correos/*.html 2>/dev/null | xargs -r grep -l -i -F "Para: ${destino} |" | head -n 1`).trim();
  if (!archivo) return null;
  const html = enApp('cat', archivo);
  const asunto = (html.match(/Asunto: (.*?) -->/) || [])[1] || '';
  const enlace = (html.match(/<a href="([^"]+)"/) || [])[1] || null;
  return { asunto, html, enlace: enlace && decodificar(enlace) };
}

/** Cantidad de correos simulados enviados a un destinatario. */
function cantidadCorreos(destino) {
  const salida = enApp('sh', '-c', `grep -l -i -F "Para: ${destino} |" writable/correos/*.html 2>/dev/null | wc -l`);
  return Number(salida.trim());
}

let pool;
async function base() {
  if (!pool) {
    pool = await sql.connect({
      server: 'db',
      user: 'sa',
      password: process.env.MSSQL_SA_PASSWORD,
      database: 'sigccr',
      options: { encrypt: false, trustServerCertificate: true },
      pool: { max: 2 },
    });
  }
  return pool;
}

/** Consulta con parámetros nombrados (@p). Devuelve las filas. */
async function consulta(texto, parametros = {}) {
  const req = (await base()).request();
  for (const [k, v] of Object.entries(parametros)) req.input(k, v);
  return (await req.query(texto)).recordset;
}

/** Primer valor de la primera fila. */
async function valor(texto, parametros = {}) {
  const filas = await consulta(texto, parametros);
  return filas.length ? Object.values(filas[0])[0] : null;
}

async function cerrarBase() {
  if (pool) await pool.close();
  pool = undefined;
}

module.exports = { spark, enApp, ultimoCorreo, cantidadCorreos, consulta, valor, cerrarBase };
