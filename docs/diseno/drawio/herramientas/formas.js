// Formas de draw.io que no trae mxGraph y estilo por defecto equivalente al de draw.io.
function instalarEstilos(graph) {
  const st = graph.getStylesheet().getDefaultVertexStyle();
  st[mxConstants.STYLE_FONTFAMILY] = 'Helvetica'; st[mxConstants.STYLE_FONTSIZE] = 12; st[mxConstants.STYLE_FONTCOLOR] = '#000000';
  st[mxConstants.STYLE_STROKECOLOR] = '#000000'; st[mxConstants.STYLE_FILLCOLOR] = '#ffffff';
  const ed = graph.getStylesheet().getDefaultEdgeStyle();
  ed[mxConstants.STYLE_STROKECOLOR] = '#000000'; ed[mxConstants.STYLE_FONTCOLOR] = '#000000'; ed[mxConstants.STYLE_FONTSIZE] = 11;
  ed[mxConstants.STYLE_LABEL_BACKGROUNDCOLOR] = '#ffffff'; ed[mxConstants.STYLE_ENDARROW] = 'classic';
}
function UmlActor() { mxShape.call(this); }
mxUtils.extend(UmlActor, mxShape);
UmlActor.prototype.paintVertexShape = function (c, x, y, w, h) {
  c.ellipse(x + w / 4, y, w / 2, h / 4); c.fillAndStroke();
  c.begin(); c.moveTo(x + w / 2, y + h / 4); c.lineTo(x + w / 2, y + 2 * h / 3);
  c.moveTo(x + w / 2, y + h / 3); c.lineTo(x, y + h / 3); c.moveTo(x + w / 2, y + h / 3); c.lineTo(x + w, y + h / 3);
  c.moveTo(x + w / 2, y + 2 * h / 3); c.lineTo(x, y + h); c.moveTo(x + w / 2, y + 2 * h / 3); c.lineTo(x + w, y + h); c.end(); c.stroke();
};
mxCellRenderer.registerShape('umlActor', UmlActor);
function estilosConNombre(graph) {
  const s = graph.getStylesheet();
  s.putCellStyle('ellipse', { shape: 'ellipse', perimeter: mxPerimeter.EllipsePerimeter });
  s.putCellStyle('rhombus', { shape: 'rhombus', perimeter: mxPerimeter.RhombusPerimeter });
  s.putCellStyle('triangle', { shape: 'triangle', perimeter: mxPerimeter.TrianglePerimeter });
  s.putCellStyle('text', { fillColor: 'none', strokeColor: 'none', gradientColor: 'none', align: 'center', verticalAlign: 'middle' });
  s.putCellStyle('label', { fontStyle: 1, align: 'left', verticalAlign: 'middle', spacing: 2, spacingLeft: 52, imageWidth: 42, imageHeight: 42, rounded: 1 });
  s.putCellStyle('swimlane', { shape: 'swimlane', fontSize: 12, fontStyle: 1, startSize: 23 });
  s.putCellStyle('group', { verticalAlign: 'top', fillColor: 'none', strokeColor: 'none', gradientColor: 'none', pointerEvents: 0 });
  s.putCellStyle('line', { shape: 'line', strokeWidth: 2, labelBackgroundColor: 'none', verticalAlign: 'top', spacingTop: 8 });
}
const _inst = instalarEstilos;
instalarEstilos = function (graph) { _inst(graph); estilosConNombre(graph); };
