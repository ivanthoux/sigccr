"""Lista las celdas de las páginas cuyo nombre contiene el filtro.

Uso: python3 herramientas/paginas.py fuentes/<archivo>.drawio | python3 herramientas/celdas.py <filtro>
"""
import json
import re
import sys
import xml.etree.ElementTree as ET


def listar(paginas, filtro):
    for p in paginas:
        if filtro not in p['name']:
            continue
        for c in ET.fromstring(p['xml']).iter('mxCell'):
            g = c.find('mxGeometry')
            v = re.sub('<[^>]+>', ' ', c.get('value') or '').strip()
            geo = '' if g is None else f"{g.get('x', '0')},{g.get('y', '0')} {g.get('width', '')}x{g.get('height', '')}"
            print('E' if c.get('edge') else 'V', c.get('id'), (c.get('style') or '')[:40], '|', v[:45], '|', geo,
                  '|', c.get('source', ''), '->', c.get('target', ''))


if __name__ == '__main__':
    listar(json.load(sys.stdin), sys.argv[1])
