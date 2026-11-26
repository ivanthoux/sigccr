import json,sys,re,xml.etree.ElementTree as ET
pags=json.load(open(sys.argv[1])); filtro=sys.argv[2]
for p in pags:
    if filtro not in p['name']: continue
    r=ET.fromstring(p['xml'])
    for c in r.iter('mxCell'):
        g=c.find('mxGeometry'); v=re.sub('<[^>]+>',' ',c.get('value') or '').strip()
        geo='' if g is None else f"{g.get('x','0')},{g.get('y','0')} {g.get('width','')}x{g.get('height','')}"
        kind='E' if c.get('edge') else 'V'
        print(kind, c.get('id'), (c.get('style') or '')[:40], '|', v[:45], '|', geo, '|', c.get('source',''), '->', c.get('target',''))
