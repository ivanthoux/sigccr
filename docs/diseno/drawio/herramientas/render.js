// Renderiza páginas de draw.io a SVG con mxGraph en Chromium headless.
const { chromium } = require('playwright');
const fs = require('fs'); const path = require('path');
(async () => {
  const [,, jsonIn, outDir] = process.argv;
  const pages = JSON.parse(fs.readFileSync(jsonIn, 'utf8'));
  const mx = fs.readFileSync(process.env.MXCLIENT, 'utf8');
  const browser = await chromium.launch(); const page = await browser.newPage();
  await page.setContent('<html><body><div id="g"></div></body></html>');
  await page.addScriptTag({ content: 'window.mxBasePath="."; window.mxLoadResources=false; window.mxLoadStylesheets=false; window.mxForceIncludes=false;' });
  await page.addScriptTag({ content: mx });
  await page.addScriptTag({ path: path.join(__dirname, 'formas.js') });
  fs.mkdirSync(outDir, { recursive: true });
  for (const p of pages) {
    const svg = await page.evaluate((xml) => {
      const c = document.getElementById('g'); c.innerHTML = '';
      const graph = new mxGraph(c); graph.setHtmlLabels(true);
      instalarEstilos(graph);
      const doc = mxUtils.parseXml(xml);
      new mxCodec(doc).decode(doc.documentElement, graph.getModel());
      const b = graph.getGraphBounds(); const pad = 10;
      const svgDoc = mxUtils.createXmlDocument();
      const root = svgDoc.createElementNS(mxConstants.NS_SVG, 'svg');
      root.setAttribute('xmlns', mxConstants.NS_SVG); root.setAttribute('xmlns:xlink', mxConstants.NS_XLINK);
      const w = Math.ceil(b.width + 2 * pad), h = Math.ceil(b.height + 2 * pad);
      root.setAttribute('width', w); root.setAttribute('height', h); root.setAttribute('viewBox', `0 0 ${w} ${h}`);
      svgDoc.appendChild(root);
      const bg = svgDoc.createElementNS(mxConstants.NS_SVG, 'rect'); bg.setAttribute('width', w); bg.setAttribute('height', h); bg.setAttribute('fill', '#ffffff'); root.appendChild(bg);
      const g = svgDoc.createElementNS(mxConstants.NS_SVG, 'g'); root.appendChild(g);
      const canvas = new mxSvgCanvas2D(g); canvas.translate(-b.x + pad, -b.y + pad);
      canvas.foEnabled = true;
      new mxImageExport().drawState(graph.getView().getState(graph.model.root), canvas);
      return mxUtils.getXml(svgDoc);
    }, p.xml);
    const nombre = p.name.replace(/[^\wáéíóúñÁÉÍÓÚÑ -]/g, '').replace(/\s+/g, '_');
    fs.writeFileSync(path.join(outDir, nombre + '.svg'), svg);
    // PNG para revisar
    const p2 = await browser.newPage({ deviceScaleFactor: 4 });
    await p2.setContent(`<html><body style="margin:0">${svg}</body></html>`);
    const el = await p2.$('svg'); await el.screenshot({ path: path.join(outDir, nombre + '.png') }); await p2.close();
    console.log('ok', nombre);
  }
  await browser.close();
})();
