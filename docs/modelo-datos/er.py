# -*- coding: utf-8 -*-
import os, sys, subprocess, html
sys.path.insert(0, os.path.dirname(__file__))
from spec import TABLES, SS

OUT = os.path.join(os.path.dirname(__file__), 'out', 'er')
os.makedirs(OUT, exist_ok=True)
TB = {t.name: t for t in TABLES}
HDR, BODY, BORDER = '#A9C4EB', '#DAE8FC', '#6C8EBF'
GHDR, GBODY, GBORDER = '#BFBFBF', '#E6E6E6', '#808080'
SKIP_REF = {'usuarios_internos'}
SOLO_CLAVES = '--claves' in sys.argv
RENDER = True


def short(t):
    t = t.replace('DATETIMEOFFSET', 'DATETIMEOFFSET').replace('NVARCHAR(MAX)', 'NVARCHAR(MAX)')
    if t.startswith('AS '): return 'calculada'
    return t


def pkcols(t):
    return t.pkcols or [c.name for c in t.cols if c.pk]


def fila(c, pk, fkcols):
    k = [m for m, si in (('PK', c.name in pk), ('FK', c.name in fkcols), ('UQ', c.uq)) if si]
    name = html.escape(c.name)
    if c.name in pk:
        name = f'<B><U>{name}</U></B>'
    typ = html.escape(short(c.typ)) + (' <FONT COLOR="#555555">N</FONT>' if c.null else '')
    return (f'<TR><TD ALIGN="LEFT" BGCOLOR="{BODY}"><FONT POINT-SIZE="8">{",".join(k) or " "}</FONT></TD>'
            f'<TD ALIGN="LEFT" BGCOLOR="{BODY}" PORT="{c.name}">{name}</TD>'
            f'<TD ALIGN="LEFT" BGCOLOR="{BODY}"><FONT POINT-SIZE="8">{typ}</FONT></TD></TR>')


def label(t):
    pk = pkcols(t)
    fkcols = {c.name for c in t.cols if c.fk} | {x for f in t.fkx for x in f[1]}
    cols = [c for c in t.cols if not SOLO_CLAVES or c.name in pk or c.name in fkcols or c.uq]
    resto = len(t.cols) - len(cols)
    rows = [f'<TR><TD COLSPAN="3" BGCOLOR="{HDR}" ALIGN="CENTER"><B>{t.name}</B></TD></TR>']
    rows += [fila(c, pk, fkcols) for c in cols]
    if resto:
        rows.append(f'<TR><TD COLSPAN="3" ALIGN="LEFT" BGCOLOR="{BODY}"><FONT POINT-SIZE="8" COLOR="#555555"><I>{resto} columnas más (4.3.6)</I></FONT></TD></TR>')
    return f'<<TABLE BORDER="1" CELLBORDER="0" CELLSPACING="0" CELLPADDING="2" COLOR="{BORDER}">' + ''.join(rows) + '</TABLE>>'


def glabel(t):
    return (f'<<TABLE BORDER="1" CELLBORDER="0" CELLSPACING="0" CELLPADDING="4" COLOR="{GBORDER}">'
            f'<TR><TD BGCOLOR="{GHDR}"><B>{t.name}</B></TD></TR>'
            f'<TR><TD BGCOLOR="{GBODY}"><FONT POINT-SIZE="8">«{t.ss}»</FONT></TD></TR></TABLE>>')


def diagram(ss, rankdir='TB', extra='', only=None, key=None):
    own = [t for t in TABLES if t.ss == ss and (only is None or t.name in only)]
    names = {t.name for t in own}
    ext = set()
    edges = []
    for t in own:
        for c in t.cols:
            if c.fk and not (c.fk in SKIP_REF and ss != 'SS-01'):
                edges.append((t.name, c.name, c.fk, c.null, c.uq))
        for n, cl, rt, rc in t.fkx:
            edges.append((t.name, cl[0], rt, False, cl[0] in pkcols(t)))
    for a, col, b, nul, uq in edges:
        if b not in names: ext.add(b)
    g = ['digraph G {', f'  graph [rankdir={rankdir}, nodesep=0.25, ranksep=0.45, fontname="Arial", pad=0.2, dpi=170 {extra}];',
         '  node [shape=plaintext, fontname="Arial", fontsize=10];', '  edge [color="#333333", arrowsize=0.8, fontname="Arial", fontsize=8];']
    for t in own:
        g.append(f'  {t.name} [label={label(t)}];')
    for e in sorted(ext):
        g.append(f'  {e} [label={glabel(TB[e])}];')
    for a, col, b, nul, uq in edges:
        head = 'teeodot' if nul else 'teetee'
        tail = ('teeodot' if uq else 'crowodot')
        g.append(f'  {a} -> {b} [dir=both, arrowhead={head}, arrowtail={tail}];')
    g.append('}')
    key = key or ss
    src = os.path.join(OUT, f'{key}.dot')
    open(src, 'w', encoding='utf-8').write('\n'.join(g))
    if not RENDER: return src
    png = os.path.join(OUT, f'{key}.png')
    subprocess.run(['dot', '-Tpng', src, '-o', png], check=True)
    subprocess.run(['dot', '-Tsvg', src, '-o', os.path.join(OUT, f'{key}.svg')], check=True)
    return png


def letra_impresa(src, ancho=24.7, alto=15.5):
    import re
    svg = subprocess.run(['dot', '-Tsvg', src], capture_output=True, text=True, check=True).stdout
    w, h = map(float, re.search(r'width="([\d.]+)pt" height="([\d.]+)pt"', svg).groups())
    w, h = w * 72 / 170, h * 72 / 170  # el SVG viene escalado por dpi=170
    return 10 * min(1, ancho / (w / 72 * 2.54), alto / (h / 72 * 2.54))


def paginar(ss, minimo=6.5):
    """Reparte las tablas del subsistema en diagramas apaisados con letra impresa >= minimo pt."""
    global RENDER
    tablas = [t.name for t in TABLES if t.ss == ss]
    grupos, actual = [], []
    def mejor(only, key):
        res = []
        for rd in ('TB', 'LR'):
            res.append((letra_impresa(diagram(ss, rd, only=only, key=key)), rd))
        return max(res)
    RENDER = False
    for t in tablas:
        prueba = actual + [t]
        f, rd = mejor(prueba, '_prueba')
        if f < minimo and actual:
            grupos.append(actual); actual = [t]
        else:
            actual = prueba
    grupos.append(actual)
    RENDER = True
    salida = []
    for i, g in enumerate(grupos):
        key = ss if len(grupos) == 1 else f'{ss}{"abcdefghij"[i]}'
        RENDER = False; f, rd = mejor(g, key); RENDER = True
        diagram(ss, rd, only=g, key=key)
        salida.append((key, rd, round(f, 1), g))
    os.remove(os.path.join(OUT, '_prueba.dot'))
    return salida


GROUPS = [
    ('SS-01', None, 'TB', None),
    ('SS-02', None, 'TB', None),
    ('SS-03', None, 'TB', None),
    ('SS-04', 'SS-04a', 'TB', ['dias_no_habiles', 'simulaciones_comparativas', 'simulaciones', 'solicitudes_credito', 'transiciones_solicitud', 'revisiones_documentales', 'verificaciones_documento']),
    ('SS-04', 'SS-04b', 'TB', ['evaluaciones_crediticias', 'antecedentes_externos', 'estructuraciones', 'alternativas_financiacion', 'resoluciones', 'contratos_prestamo', 'liquidaciones']),
    ('SS-05', 'SS-05a', 'TB', ['cajas', 'asignaciones_caja', 'jornadas_caja', 'movimientos_caja', 'cobros', 'cobros_medios', 'imputaciones', 'comprobantes_pago', 'anulaciones_cobro', 'conciliaciones_jornada']),
    ('SS-05', 'SS-05b', 'TB', ['ordenes_pago_electronico', 'ordenes_pago_cuotas', 'notificaciones_pasarela', 'transacciones_pasarela', 'incidencias_conciliacion', 'saldos_a_favor', 'aplicaciones_saldo', 'autorizaciones_devolucion', 'ejecuciones_devolucion']),
    ('SS-05', 'SS-05c', 'LR', ['movimientos_fondos', 'afectaciones_fondos', 'proyecciones_fondos']),
    ('SS-06', None, 'TB', None),
    ('SS-08', None, 'TB', None),
    ('SS-10', None, 'TB', None),
    ('SS-11', None, 'TB', None),
]

if __name__ == '__main__':
    if '--paginar' in sys.argv:
        import glob
        for f in glob.glob(os.path.join(OUT, '*')): os.remove(f)
        for ss in sorted({t.ss for t in TABLES}):
            for key, rd, f, g in paginar(ss):
                print(f'{key:8} {rd} {f:4} pt  {len(g)} tablas: {", ".join(g)}')
    else:
        for ss, key, rd, only in GROUPS:
            print(diagram(ss, rd, only=only, key=key))
