// Dibuja las páginas de draw.io con mxGraph en Chromium sin interfaz y guarda un SVG y un PNG (escala 4) por página.
// Uso: node render.js <paginas.json> <carpeta de salida>  (ambas dentro de la carpeta actual)
const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');

function dentroDeLaCarpetaActual(ruta) {
  const base = fs.realpathSync(process.cwd());
  const abs = path.resolve(base, ruta);
  const rel = path.relative(base, abs);
  if (rel.startsWith('..') || path.isAbsolute(rel)) throw new Error(`Ruta fuera de ${base}: ${ruta}`);
  return abs;
}

function dibujarPagina(xml) {
  const { mxGraph: Grafo, mxCodec: Codec, mxUtils: utilidades, mxConstants: K,
    mxSvgCanvas2D: LienzoSvg, mxImageExport: Exportador } = window;
  const c = document.getElementById('g'); c.innerHTML = '';
  const graph = new Grafo(c); graph.setHtmlLabels(true);
  instalarEstilos(graph);
  const doc = utilidades.parseXml(xml);
  new Codec(doc).decode(doc.documentElement, graph.getModel());
  const b = graph.getGraphBounds(); const pad = 10;
  const svgDoc = utilidades.createXmlDocument();
  const root = svgDoc.createElementNS(K.NS_SVG, 'svg');
  root.setAttribute('xmlns', K.NS_SVG); root.setAttribute('xmlns:xlink', K.NS_XLINK);
  const w = Math.ceil(b.width + 2 * pad), h = Math.ceil(b.height + 2 * pad);
  root.setAttribute('width', w); root.setAttribute('height', h); root.setAttribute('viewBox', `0 0 ${w} ${h}`);
  svgDoc.appendChild(root);
  const bg = svgDoc.createElementNS(K.NS_SVG, 'rect');
  bg.setAttribute('width', w); bg.setAttribute('height', h); bg.setAttribute('fill', '#ffffff'); root.appendChild(bg);
  const g = svgDoc.createElementNS(K.NS_SVG, 'g'); root.appendChild(g);
  const canvas = new LienzoSvg(g); canvas.translate(-b.x + pad, -b.y + pad);
  canvas.foEnabled = true;
  new Exportador().drawState(graph.getView().getState(graph.model.root), canvas);
  return utilidades.getXml(svgDoc);
}

async function main() {
  const [, , jsonIn, outArg] = process.argv;
  const pages = JSON.parse(fs.readFileSync(dentroDeLaCarpetaActual(jsonIn), 'utf8'));
  const outDir = dentroDeLaCarpetaActual(outArg);
  const mx = fs.readFileSync(process.env.MXCLIENT, 'utf8');
  const browser = await chromium.launch();
  const page = await browser.newPage();
  const vista = await browser.newPage({ deviceScaleFactor: 4 });
  await page.setContent('<html><body><div id="g"></div></body></html>');
  await page.addScriptTag({ content: 'window.mxBasePath="."; window.mxLoadResources=false; window.mxLoadStylesheets=false; window.mxForceIncludes=false;' });
  await page.addScriptTag({ content: mx });
  await page.addScriptTag({ path: path.join(__dirname, 'formas.js') });
  fs.mkdirSync(outDir, { recursive: true });
  // Las páginas se dibujan una por vez porque comparten el mismo contenedor del navegador.
  for (const p of pages) {
    const svg = await page.evaluate(dibujarPagina, p.xml); // NOSONAR: secuencial a propósito
    const nombre = p.name.replace(/[^\wáéíóúñÁÉÍÓÚÑ -]/g, '').replace(/\s+/g, '_');
    fs.writeFileSync(path.join(outDir, nombre + '.svg'), svg);
    await vista.setContent(`<html><body style="margin:0">${svg}</body></html>`); // NOSONAR
    const el = await vista.$('svg'); // NOSONAR
    await el.screenshot({ path: path.join(outDir, nombre + '.png') }); // NOSONAR
    console.log('ok', nombre);
  }
  await browser.close();
}

main().catch((e) => { console.error(e); process.exitCode = 1; });
