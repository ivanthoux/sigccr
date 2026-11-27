"""Extrae cada página de un .drawio (comprimida o no) como XML de mxGraphModel."""
import base64
import json
import os
import sys
import urllib.parse
import zlib
import xml.etree.ElementTree as ET

def ruta_segura(ruta):
    """Devuelve la ruta absoluta si está dentro de la carpeta actual; si no, termina con error."""
    base = os.path.realpath(os.getcwd())
    abs_ = os.path.realpath(os.path.join(base, ruta))
    if os.path.commonpath([base, abs_]) != base:
        sys.exit(f'Ruta fuera de {base}: {ruta}')
    return abs_

def paginas(ruta):
    t = ET.parse(ruta_segura(ruta)).getroot()
    out = []
    for d in t.findall('diagram'):
        m = d.find('mxGraphModel')
        if m is not None:
            xml = ET.tostring(m, encoding='unicode')
        else:
            raw = base64.b64decode(d.text.strip())
            xml = urllib.parse.unquote(zlib.decompress(raw, -15).decode('utf-8'))
        out.append({'name': d.get('name'), 'id': d.get('id'), 'xml': xml})
    return out
if __name__ == '__main__':
    print(json.dumps(paginas(sys.argv[1]), ensure_ascii=False))
