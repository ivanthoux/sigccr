"""Resumen de cobertura por área y por conjunto de pruebas a partir de los Clover de unir.php."""
import collections, json, os, xml.etree.ElementTree as ET

def area(ruta):
    p = os.path.relpath(ruta).split('/')
    return '/'.join(p[1:3]) if p[1] in ('Libraries', 'Services') else p[1]

def lineas(clover):
    """{archivo: {línea: cubierta}} de las sentencias (stmt) del Clover."""
    r = ET.parse(clover).getroot(); d = {}
    for f in r.iter('file'):
        d[f.get('name')] = {int(l.get('num')): int(l.get('count')) > 0 for l in f.iter('line') if l.get('type') == 'stmt'}
    return d

conj = {'unitarias': lineas('build/clover-unitarias.xml'), 'phpunit': lineas('build/clover-phpunit.xml'), 'total': lineas('build/clover-total.xml')}
e2e = {f: set(ls) for f, ls in json.load(open('build/e2e-lineas.json')).items()} if os.path.exists('build/e2e-lineas.json') else {}
base = conj['total']
agg = collections.defaultdict(lambda: collections.Counter())
for f, ls in base.items():
    k = area(f)
    for l in ls:
        agg[k]['stmt'] += 1
        for n, d in conj.items():
            agg[k][n] += d.get(f, {}).get(l, False)
        agg[k]['e2e'] += l in e2e.get(f, set())
tot = collections.Counter()
for k in agg: tot.update(agg[k])
print('COB|área|sentencias|unitarias %|+integración %|solo punta a punta %|total %')
for k in sorted(agg) + ['TOTAL']:
    c = tot if k == 'TOTAL' else agg[k]; s = c['stmt'] or 1
    print(f"COB|{k}|{c['stmt']}|{100*c['unitarias']/s:.1f}|{100*c['phpunit']/s:.1f}|{100*c['e2e']/s:.1f}|{100*c['total']/s:.1f}")
