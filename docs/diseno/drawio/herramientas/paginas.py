"""Extrae cada página de un .drawio (comprimida o no) como XML de mxGraphModel."""
import sys, re, base64, zlib, urllib.parse, json, xml.etree.ElementTree as ET
def paginas(ruta):
    t = ET.parse(ruta).getroot()
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
