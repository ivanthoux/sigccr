# -*- coding: utf-8 -*-
"""Modelo de datos completo del SIG-CCR en un único diagrama: Graphviz (disposición), draw.io y Mermaid."""
import os, sys, json, subprocess, html
sys.path.insert(0, os.path.dirname(__file__))
from spec import TABLES, SS
from er import label, pkcols, TB

OUT = os.path.join(os.path.dirname(__file__), 'out', 'full')
os.makedirs(OUT, exist_ok=True)
# Color del encabezado por subsistema (solo identifica; el diagrama no se separa)
COL = {'SS-01': '#A9C4EB', 'SS-02': '#B9E0A5', 'SS-03': '#FFD966', 'SS-04': '#F4B183', 'SS-05': '#C9A0DC',
       'SS-06': '#F8CECC', 'SS-08': '#9DD9D2', 'SS-10': '#D0D0D0', 'SS-11': '#E1D5E7'}


def edges():
    e = []
    for t in TABLES:
        for c in t.cols:
            if c.fk: e.append((t.name, c.name, c.fk, c.null, c.uq))
        for n, cl, rt, rc in t.fkx:
            e.append((t.name, cl[0], rt, False, cl[0] in pkcols(t)))
    return e


E = edges()

# ---------------------------------------------------------------- Graphviz
g = ['digraph G {', '  graph [rankdir=TB, nodesep=0.3, ranksep=0.9, fontname="Arial", pad=0.3, dpi=150, concentrate=false, newrank=true];',
     '  node [shape=plaintext, fontname="Arial", fontsize=10];', '  edge [color="#555555", arrowsize=0.7, penwidth=0.8];']
for t in TABLES:
    lab = label(t).replace('BGCOLOR="#A9C4EB"', f'BGCOLOR="{COL[t.ss]}"', 1)
    lab = lab.replace(f'<B>{t.name}</B>', f'<B>{t.name}</B> <FONT POINT-SIZE="8">«{t.ss}»</FONT>', 1)
    g.append(f'  {t.name} [label={lab}];')
for a, col, b, nul, uq in E:
    head = 'teeodot' if nul else 'teetee'
    tail = 'teeodot' if uq else 'crowodot'
    extra = ', constraint=false, color="#AAAAAA"' if b == 'usuarios_internos' and TB[a].ss != 'SS-01' else ''
    g.append(f'  {a} -> {b} [dir=both, arrowhead={head}, arrowtail={tail}{extra}];')
g.append('}')
dotp = os.path.join(OUT, 'DER_SIG-CCR_completo.dot')
open(dotp, 'w', encoding='utf-8').write('\n'.join(g))
for fmt in ('png', 'svg', 'pdf'):
    subprocess.run(['dot', f'-T{fmt}', dotp, '-o', os.path.join(OUT, f'DER_SIG-CCR_completo.{fmt}')], check=True)

# ---------------------------------------------------------------- draw.io
lay = json.loads(subprocess.run(['dot', '-Tjson', dotp], capture_output=True, text=True, check=True).stdout)
Hb = float(lay['bb'].split(',')[3])
S, ROW, HDR = 1.45, 20, 26
pos = {}
for o in lay['objects']:
    x, y = map(float, o['pos'].split(','))
    pos[o['name']] = (x * S, (Hb - y) * S, float(o['width']) * 72 * S)
esc = lambda s: html.escape(s, quote=True)
cells, ids, n = [], {}, [10]


def new():
    n[0] += 1
    return f'c{n[0]}'


for t in TABLES:
    cx, cy, w = pos[t.name]
    pk = pkcols(t)
    fkc = {c.name for c in t.cols if c.fk} | {x for f in t.fkx for x in f[1]}
    h = HDR + ROW * len(t.cols)
    tid = new(); ids[t.name] = tid
    cells.append(f'<mxCell id="{tid}" value="{esc(t.name)} «{t.ss}»" style="swimlane;fontStyle=1;childLayout=stackLayout;horizontal=1;startSize={HDR};horizontalStack=0;resizeParent=1;resizeParentMax=0;resizeLast=0;collapsible=1;marginBottom=0;fillColor={COL[t.ss]};strokeColor=#6C8EBF;swimlaneFillColor=#F5F9FF;fontFamily=Arial;fontSize=12;" vertex="1" parent="1"><mxGeometry x="{cx - w / 2:.0f}" y="{cy - h / 2:.0f}" width="{w:.0f}" height="{h}" as="geometry"/></mxCell>')
    for i, c in enumerate(t.cols):
        k = '/'.join([x for x, cond in (('PK', c.name in pk), ('FK', c.name in fkc), ('UQ', c.uq)) if cond])
        typ = 'calculada' if c.typ.startswith('AS ') else c.typ
        txt = f'{(k + " ") if k else ""}{c.name} : {typ}{" (N)" if c.null else ""}'
        st = 'text;strokeColor=none;fillColor=none;align=left;verticalAlign=middle;spacingLeft=6;overflow=hidden;rotatable=0;points=[[0,0.5],[1,0.5]];portConstraint=eastwest;fontFamily=Arial;fontSize=11;' + ('fontStyle=5;' if c.name in pk else '')
        cells.append(f'<mxCell id="{new()}" value="{esc(txt)}" style="{st}" vertex="1" parent="{tid}"><mxGeometry y="{HDR + i * ROW}" width="{w:.0f}" height="{ROW}" as="geometry"/></mxCell>')
# leyenda de colores
lx, ly = 0, -60 - 24 * len(COL)
cells.append(f'<mxCell id="{new()}" value="Colores por subsistema" style="text;fontStyle=1;fontFamily=Arial;fontSize=12;" vertex="1" parent="1"><mxGeometry x="{lx}" y="{ly - 26}" width="300" height="24" as="geometry"/></mxCell>')
for i, (ss, col) in enumerate(COL.items()):
    cells.append(f'<mxCell id="{new()}" value="{ss} {esc(SS[ss])}" style="rounded=0;whiteSpace=wrap;html=1;align=left;spacingLeft=6;fillColor={col};strokeColor=#6C8EBF;fontFamily=Arial;fontSize=11;" vertex="1" parent="1"><mxGeometry x="{lx}" y="{ly + i * 24}" width="360" height="22" as="geometry"/></mxCell>')
for a, col, b, nul, uq in E:
    end = 'ERzeroToOne' if nul else 'ERmandOne'
    start = 'ERzeroToOne' if uq else 'ERzeroToMany'
    color = '#AAAAAA' if b == 'usuarios_internos' and TB[a].ss != 'SS-01' else '#333333'
    cells.append(f'<mxCell id="{new()}" value="" style="edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;endArrow={end};startArrow={start};endFill=0;startFill=0;strokeColor={color};" edge="1" parent="1" source="{ids[a]}" target="{ids[b]}"><mxGeometry relative="1" as="geometry"/></mxCell>')
open(os.path.join(OUT, 'DER_SIG-CCR_completo_unico.drawio'), 'w', encoding='utf-8').write(
    '<mxfile host="app.diagrams.net"><diagram name="Modelo de datos completo" id="full"><mxGraphModel dx="1600" dy="1000" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="0" math="0" shadow="0"><root><mxCell id="0"/><mxCell id="1" parent="0"/>'
    + ''.join(cells) + '</root></mxGraphModel></diagram></mxfile>')

# ---------------------------------------------------------------- Mermaid erDiagram
def mtype(t):
    if t.startswith('AS '): return 'VARCHAR'
    return t.split('(')[0].replace(' ', '_')


m = ['erDiagram']
for t in TABLES:
    pk = pkcols(t)
    fkc = {c.name for c in t.cols if c.fk} | {x for f in t.fkx for x in f[1]}
    m.append(f'    {t.name} {{')
    for c in t.cols:
        keys = [k for k, cond in (('PK', c.name in pk), ('FK', c.name in fkc), ('UK', c.uq)) if cond]
        typ = mtype(c.typ)
        detail = c.typ if '(' in c.typ and not c.typ.startswith('AS ') else ''
        if c.null: detail = (detail + ' NULL').strip()
        line = f'        {typ} {c.name}'
        if keys: line += ' ' + ','.join(keys)
        if detail: line += f' "{detail}"'
        m.append(line)
    m.append('    }')
for a, col, b, nul, uq in E:
    left = '|o' if nul else '||'          # lado de la tabla referenciada (padre)
    right = 'o|' if uq else 'o{'          # lado de la tabla que contiene la FK (hija)
    m.append(f'    {b} {left}--{right} {a} : "{col}"')
mmd = os.path.join(OUT, 'DER_SIG-CCR_completo.mmd')
open(mmd, 'w', encoding='utf-8').write('\n'.join(m) + '\n')
print('tablas', len(TABLES), 'relaciones', len(E))
