"""Evalúa los resultados de las pruebas de rendimiento contra los criterios del plan.

    python3 tests/rendimiento/evaluar.py tests/rendimiento/resultados

Lee carga.jtl, concurrencia.txt, procesos.txt y volumen.txt; escribe resumen.md y resumen.json en la misma carpeta y
termina con código 1 si algún criterio no se cumple.
"""
import csv
import json
import math
import re
import sys
from collections import defaultdict
from pathlib import Path

LIMITE_MS = 2000
ERRORES_MAX = 0.01
# Operaciones con criterio de aceptación (RNF-03 y RNF-07); el resto se informa
CRITERIO = {
    'RP-01 Búsqueda de clientes por apellido': 'RNF-03',
    'RP-01 Búsqueda de clientes por documento': 'RNF-03',
    'RP-01 Búsqueda de clientes por CUIL': 'RNF-03',
    'RP-02 Búsqueda del cliente en el cobro': 'RNF-03',
    'RP-03 Deuda del cliente': 'RNF-03',
    'RP-03 Registro del cobro': 'RNF-03',
    'RP-05 Listado de solicitudes': 'RNF-07',
    'RP-05 Comprobantes del día': 'RNF-07',
}


def percentil(valores: list[int], p: float) -> int:
    v = sorted(valores)
    return v[max(0, math.ceil(p / 100 * len(v)) - 1)]


def main(res: Path) -> int:
    muestras: dict[str, list[tuple[int, bool, int]]] = defaultdict(list)
    with open(res / 'carga.jtl', encoding='utf-8', newline='') as f:
        for fila in csv.DictReader(f):
            etiqueta = fila['label']
            # Las redirecciones se guardan como submuestras («-0», «-1»): su tiempo ya está en la muestra principal
            if re.search(r'-\d+$', etiqueta) or etiqueta.startswith('00 '):
                continue
            muestras[etiqueta].append((int(fila['elapsed']), fila['success'] == 'true', int(fila['timeStamp'])))

    filas, falla = [], False
    inicio = min(t for m in muestras.values() for _, _, t in m)
    fin = max(t for m in muestras.values() for _, _, t in m)
    duracion = max(1, (fin - inicio) / 1000)
    for etiqueta in sorted(muestras):
        m = muestras[etiqueta]
        tiempos = [e for e, _, _ in m]
        errores = sum(1 for _, ok, _ in m if not ok) / len(m)
        p95 = percentil(tiempos, 95)
        req = CRITERIO.get(etiqueta)
        cumple = None if req is None else (p95 < LIMITE_MS and errores <= ERRORES_MAX)
        falla |= cumple is False
        filas.append({
            'operacion': etiqueta, 'requisito': req or 'Informativo', 'muestras': len(m), 'por_minuto': round(len(m) / duracion * 60, 1),
            'p50': percentil(tiempos, 50), 'p90': percentil(tiempos, 90), 'p95': p95, 'p99': percentil(tiempos, 99), 'maximo': max(tiempos),
            'errores_pct': round(errores * 100, 2), 'cumple': cumple,
        })

    conc = dict(l.split('=', 1) for l in (res / 'concurrencia.txt').read_text(encoding='utf-8').split() if '=' in l)
    concurrencia = [
        {'caso': 'RP-06a Dos cobros simultáneos de la misma cuota', 'esperado': 'un solo cobro confirmado',
         'obtenido': f"{conc.get('cobros_confirmados_del_cliente')} cobro(s) confirmado(s)", 'cumple': conc.get('cobros_confirmados_del_cliente') == '1'},
        {'caso': 'RP-06b Dos anulaciones simultáneas del mismo cobro', 'esperado': 'una sola anulación y un solo saldo a favor',
         'obtenido': f"estado {conc.get('estado_del_cobro')}, {conc.get('anulaciones_del_cobro')} anulación(es), {conc.get('saldos_a_favor_de_la_anulacion')} saldo(s) a favor",
         'cumple': conc.get('estado_del_cobro') == 'Anulado' and conc.get('anulaciones_del_cobro') == '1' and conc.get('saldos_a_favor_de_la_anulacion') == '1'},
    ]
    falla |= any(not c['cumple'] for c in concurrencia)
    # Respuestas de JMeter de la concurrencia: cuál prosperó y qué recibió la otra
    detalle_conc = []
    if (res / 'concurrencia.jtl').exists():
        with open(res / 'concurrencia.jtl', encoding='utf-8', newline='') as f:
            detalle_conc = [f"{r['label']} ({r['threadName']}): {r['elapsed']} ms, HTTP {r['responseCode']}" for r in csv.DictReader(f) if r['label'].startswith('RP-06') and not re.search(r'-\d+$', r['label'])]

    procesos = []
    for linea in (res / 'procesos.txt').read_text(encoding='utf-8').splitlines():
        partes = linea.split(',')
        if len(partes) == 3:
            procesos.append({'proceso': partes[0], 'segundos': round(float(partes[1]), 1), 'codigo': int(partes[2])})
    volumen = (res / 'volumen.txt').read_text(encoding='utf-8').strip()
    recursos = (res / 'recursos.txt').read_text(encoding='utf-8').strip()

    si_no = {True: 'Sí', False: 'No', None: '—'}
    md = ['# Resultados de las pruebas de rendimiento del SIG-CCR', '', f'**Resultado general:** {"no cumple" if falla else "cumple"} los criterios del plan.', '',
          '## Volumen y entorno', '', '```', volumen, '', recursos, '```', '',
          f'## Carga mixta (RP-01 a RP-05): 10 usuarios durante {duracion / 60:.1f} minutos', '',
          f'Criterio: percentil 95 menor que {LIMITE_MS} ms y errores ≤ {ERRORES_MAX:.0%} en las operaciones de RNF-03 y RNF-07. Tiempos en milisegundos.', '',
          '| Operación | Requisito | Muestras | Por minuto | p50 | p90 | p95 | p99 | Máximo | Errores | Cumple |', '|---|---|---|---|---|---|---|---|---|---|---|']
    md += [f"| {r['operacion']} | {r['requisito']} | {r['muestras']} | {r['por_minuto']} | {r['p50']} | {r['p90']} | {r['p95']} | {r['p99']} | {r['maximo']} | {r['errores_pct']} % | {si_no[r['cumple']]} |" for r in filas]
    md += ['', '## Decisiones simultáneas (RP-06, RNF-06)', '', '| Caso | Esperado | Obtenido | Cumple |', '|---|---|---|---|']
    md += [f"| {c['caso']} | {c['esperado']} | {c['obtenido']} | {si_no[c['cumple']]} |" for c in concurrencia]
    md += ['', *[f'- {d}' for d in detalle_conc], '', '## Procesos automáticos con el volumen completo (RP-07, RNF-10)', '', '| Proceso | Duración (s) | Código de salida |', '|---|---|---|']
    md += [f"| {p['proceso']} | {p['segundos']} | {p['codigo']} |" for p in procesos]
    (res / 'resumen.md').write_text('\n'.join(md) + '\n', encoding='utf-8')
    (res / 'resumen.json').write_text(json.dumps({'cumple': not falla, 'carga': filas, 'concurrencia': concurrencia, 'procesos': procesos}, ensure_ascii=False, indent=2), encoding='utf-8')
    print('\n'.join(md))
    return 1 if falla else 0


if __name__ == '__main__':
    sys.exit(main(Path(sys.argv[1])))
