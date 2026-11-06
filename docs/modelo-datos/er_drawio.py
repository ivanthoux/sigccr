# -*- coding: utf-8 -*-
"""Genera los DER en formato draw.io (.drawio) usando la disposición calculada por Graphviz."""
import os, sys, json, subprocess, html
sys.path.insert(0, os.path.dirname(__file__))
from spec import TABLES
from er import GROUPS, SKIP_REF, pkcols, TB, OUT as EROUT

OUT = os.path.join(os.path.dirname(__file__), 'out', 'drawio')
os.makedirs(OUT, exist_ok=True)
S = 1.45      # escala puntos Graphviz -> px draw.io
ROW = 20
HDR = 26


def esc(s):
    return html.escape(s, quote=True)


def build(ss, key, only):
    key = key or ss
    lay = json.loads(subprocess.run(['dot', '-Tjson', os.path.join(EROUT, f'{key}.dot')], capture_output=True, text=True, check=True).stdout)
    H = float(lay['bb'].split(',')[3])
    pos = {}
    for o in lay['objects']:
        x, y = map(float, o['pos'].split(','))
        pos[o['name']] = (x * S, (H - y) * S, float(o['width']) * 72 * S)
    own = [t for t in TABLES if t.ss == ss and (only is None or t.name in only)]
    names = {t.name for t in own}
    cells = []
    nid = [10]

    def new():
        nid[0] += 1
        return f'c{nid[0]}'
    ids = {}
    for t in own:
        cx, cy, w = pos[t.name]
        pk = pkcols(t)
        fkc = {c.name for c in t.cols if c.fk} | {x for f in t.fkx for x in f[1]}
        h = HDR + ROW * len(t.cols)
        x0, y0 = cx - w / 2, cy - h / 2
        tid = new(); ids[t.name] = tid
        cells.append(f'<mxCell id="{tid}" value="{esc(t.name)}" style="swimlane;fontStyle=1;childLayout=stackLayout;horizontal=1;startSize={HDR};horizontalStack=0;resizeParent=1;resizeParentMax=0;resizeLast=0;collapsible=1;marginBottom=0;fillColor=#A9C4EB;strokeColor=#6C8EBF;swimlaneFillColor=#DAE8FC;fontFamily=Arial;fontSize=12;" vertex="1" parent="1"><mxGeometry x="{x0:.0f}" y="{y0:.0f}" width="{w:.0f}" height="{h}" as="geometry"/></mxCell>')
        for i, c in enumerate(t.cols):
            k = '/'.join([x for x, cond in (('PK', c.name in pk), ('FK', c.name in fkc), ('UQ', c.uq)) if cond])
            typ = 'calculada' if c.typ.startswith('AS ') else c.typ
            label = f'{k:<6}' if k else ''
            txt = f'{(k + " ") if k else ""}{c.name} : {typ}{" (N)" if c.null else ""}'
            style = 'text;strokeColor=none;fillColor=none;align=left;verticalAlign=middle;spacingLeft=6;spacingRight=4;overflow=hidden;rotatable=0;points=[[0,0.5],[1,0.5]];portConstraint=eastwest;fontFamily=Arial;fontSize=11;'
            if c.name in pk: style += 'fontStyle=5;'
            cells.append(f'<mxCell id="{new()}" value="{esc(txt)}" style="{style}" vertex="1" parent="{tid}"><mxGeometry y="{HDR + i * ROW}" width="{w:.0f}" height="{ROW}" as="geometry"/></mxCell>')
    edges = []
    for t in own:
        for c in t.cols:
            if c.fk and not (c.fk in SKIP_REF and ss != 'SS-01'):
                edges.append((t.name, c.fk, c.null, c.uq))
        for n, cl, rt, rc in t.fkx:
            edges.append((t.name, rt, False, cl[0] in pkcols(t)))
    for a, b, nul, uq in edges:
        if b not in ids:
            cx, cy, w = pos[b]
            gid = new(); ids[b] = gid
            cells.append(f'<mxCell id="{gid}" value="&lt;b&gt;{esc(b)}&lt;/b&gt;&lt;br&gt;&lt;font style=&quot;font-size:10px&quot;&gt;«{TB[b].ss}»&lt;/font&gt;" style="rounded=0;whiteSpace=wrap;html=1;fillColor=#E6E6E6;strokeColor=#808080;fontFamily=Arial;fontSize=12;" vertex="1" parent="1"><mxGeometry x="{cx - w / 2:.0f}" y="{cy - 22:.0f}" width="{w:.0f}" height="44" as="geometry"/></mxCell>')
    for a, b, nul, uq in edges:
        end = 'ERzeroToOne' if nul else 'ERmandOne'
        start = 'ERzeroToOne' if uq else 'ERzeroToMany'
        cells.append(f'<mxCell id="{new()}" style="edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;endArrow={end};startArrow={start};endFill=0;startFill=0;strokeColor=#333333;" edge="1" parent="1" source="{ids[a]}" target="{ids[b]}"><mxGeometry relative="1" as="geometry"/></mxCell>')
    return key, cells


def write_file(name, pages):
    diagrams = ''
    for pname, cells in pages:
        diagrams += (f'<diagram name="{esc(pname)}" id="{esc(pname)}"><mxGraphModel dx="1200" dy="800" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="0" pageScale="1" math="0" shadow="0"><root>'
                     '<mxCell id="0"/><mxCell id="1" parent="0"/>' + ''.join(cells) + '</root></mxGraphModel></diagram>')
    open(os.path.join(OUT, name), 'w', encoding='utf-8').write(f'<mxfile host="app.diagrams.net" agent="SIG-CCR">{diagrams}</mxfile>')


if __name__ == '__main__':
    pages = []
    for ss, key, rd, only in GROUPS:
        k, cells = build(ss, key, only)
        write_file(f'DER_{k}.drawio', [(k, cells)])
        pages.append((k, cells))
    write_file('DER_SIG-CCR_completo.drawio', pages)
    print(sorted(os.listdir(OUT)))
