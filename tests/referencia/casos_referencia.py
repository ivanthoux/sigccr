# -*- coding: utf-8 -*-
"""
Cálculo independiente de los casos de prueba de referencia del motor financiero (plan preventivo de R3, RNF-15).

Implementación separada del código PHP, escrita con el módulo decimal de Python (40 dígitos de precisión y redondeo
a la mitad hacia arriba). Sus resultados son los valores esperados de tests/unit/Finanzas. Ejecutar:
    python3 tests/referencia/casos_referencia.py
"""
from decimal import Decimal as D, getcontext, ROUND_HALF_UP, ROUND_FLOOR
from datetime import date, timedelta, datetime
import calendar, json

getcontext().prec = 40


def r(x, n=2):
    return x.quantize(D(1).scaleb(-n), ROUND_HALF_UP)


def vencimientos(base, n):
    p = base + timedelta(days=30)
    out = []
    for k in range(n):
        m = p.month - 1 + k
        y, m = p.year + m // 12, m % 12 + 1
        out.append(date(y, m, min(p.day, calendar.monthrange(y, m)[1])))
    return out


def plan(C, tna, n, base, g=D(0), iva=D(0), nocap=D(0)):
    i = tna / 12
    cuota = r(C / n) if i == 0 else r(C * i / (1 - (1 + i) ** -n))
    s = C
    parte = r(nocap / n)
    asign = D(0)
    rows = []
    for k, v in enumerate(vencimientos(base, n), 1):
        it = r(s * i)
        cap = cuota - it if k < n else s
        s -= cap
        nc = parte if k < n else nocap - asign
        asign += nc
        iv = r((it + g) * iva)
        rows.append(dict(n=k, venc=v.isoformat(), capital=cap, interes=it, gastos=g, iva=iv, nocap=nc, total=cap + it + g + iv + nc))
    return cuota, rows


def tir(neto, flujos):
    lo, hi = D(0), D(1)
    for _ in range(300):
        m = (lo + hi) / 2
        va = sum(f / (1 + m) ** (k + 1) for k, f in enumerate(flujos))
        lo, hi = (m, hi) if va > neto else (lo, m)
    return (lo + hi) / 2


out = {}

# 1) RN-68: caso de referencia del documento
cuota, rows = plan(D(100000), D('0.60'), 6, date(2026, 1, 10), D(500), D('0.21'))
gasto = r(D(100000) * D('0.02')); ivag = r(gasto * D('0.21')); neto = D(100000) - gasto - ivag
rm = tir(neto, [x['total'] for x in rows])
out['rn68'] = dict(cuota=str(cuota), plan=[{k: str(v) for k, v in x.items()} for x in rows], gasto=str(gasto), iva_gasto=str(ivag), neto=str(neto),
                   tea=str(r(((1 + D('0.05')) ** 12 - 1) * 100)), cftea=str(r(((1 + rm) ** 12 - 1) * 100)),
                   total_capital=str(sum(x['capital'] for x in rows)), total_pagar=str(sum(x['total'] for x in rows)))

# 2) Tasa cero
cuota0, rows0 = plan(D(1000), D(0), 3, date(2026, 1, 10))
out['tasa_cero'] = dict(cuota=str(cuota0), capitales=[str(x['capital']) for x in rows0])

# 3) Punitorios: tasa configurada 0.0012 diaria, tope 50 % de TNA 0.60 => 0.6*0.5/365 = 0.000821917808...
tasa_tope = D('0.5') * D('0.60') / 365
td = min(D('0.0012'), tasa_tope).quantize(D('1e-12'), ROUND_HALF_UP)
base = D('15436.84')
tramo = r(base * td, 4); iva_t = r(tramo * D('0.21'), 4)
out['punitorios'] = dict(tasa_diaria=str(td), tramo=str(tramo), iva_tramo=str(iva_t),
                         dia4_importe=str(r(tramo * 4)), dia4_iva=str(r(iva_t * 4)))
# cambio de tasa en el período (RN-35): días 1-5 a 0.0008, días 6-10 a 0.0005
t1 = r(base * D('0.0008'), 4); t2 = r(base * D('0.0005'), 4)
out['punitorios_cambio_tasa'] = dict(importe=str(r(t1 * 5 + t2 * 5)), iva=str(r(r(t1 * D('0.21'), 4) * 5 + r(t2 * D('0.21'), 4) * 5)))

# 4) Capacidad de pago y capital máximo (CU-87 con IVA del interés)
cap = r(D(500000) * D('0.30') - D(50000))
def capmax(K, tna, n, g, iva, paso):
    i = tna / 12
    disp = K - g * (1 + iva)
    a = (1 - (1 + i) ** -n) / i
    bruto = disp / (1 / a + iva * i)
    c = (bruto / paso).to_integral_value(ROUND_FLOOR) * paso
    while c > 0:
        _, rr = plan(c, tna, n, date(2000, 1, 1), g, iva)
        if max(x['total'] for x in rr) <= K:
            return c, bruto, max(x['total'] for x in rr)
        c -= paso
    return D(0), bruto, D(0)
c12, bruto12, max12 = capmax(cap, D('0.60'), 12, D(500), D('0.21'), D(1000))
_, rr13 = plan(c12 + 1000, D('0.60'), 12, date(2000, 1, 1), D(500), D('0.21'))
out['capacidad'] = dict(capacidad=str(cap), capital_maximo_12=str(c12), bruto=str(r(bruto12, 4)), cuota_max=str(max12), cuota_max_siguiente=str(max(x['total'] for x in rr13)))

# 5) Cancelación anticipada sobre el plan RN-68 liquidado el 10/01/2026: cuota 1 pagada, cuota 2 vencida el 09/03/2026
#    con punitorios exigibles 120.50 + IVA 25.31, pago el 20/03/2026; cuota en curso: 3 (vence 09/04/2026).
c2 = rows[1]
venc2 = c2['total'] + D('120.50') + D('25.31')
cap_no_venc = sum(x['capital'] for x in rows[2:])
dias = (date(2026, 3, 20) - date.fromisoformat(rows[1]['venc'])).days
int3 = min(r(rows[2]['interes'] * dias / 30), rows[2]['interes'])
iva3 = r(int3 * D('0.21'))
out['cancelacion'] = dict(vencidas=str(venc2), capital_no_vencido=str(cap_no_venc), dias=dias, interes=str(int3), iva=str(iva3), total=str(venc2 + cap_no_venc + int3 + iva3))

# 6) Refinanciación: cuotas 2 a 6 del plan RN-68 con corte el 20/03/2026; cuota 2 vencida con punitorios 120.50 + IVA 25.31
nocap = rows[1]['interes'] + rows[1]['gastos'] + rows[1]['iva'] + D('120.50') + D('25.31')
capital = sum(x['capital'] for x in rows[1:])
nocap_aplic = nocap + D('35.40')   # punitorios devengados desde el corte, incorporados al aplicar
cuotaR, rowsR = plan(capital, D('0.48'), 12, date(2026, 3, 25), D(0), D('0.21'), nocap_aplic)
out['refinanciacion'] = dict(capital=str(capital), no_capitalizable=str(nocap), no_capitalizable_aplicado=str(nocap_aplic), cuota=str(cuotaR),
                             primera=[str(rowsR[0][k]) for k in ('venc', 'capital', 'interes', 'iva', 'nocap', 'total')],
                             ultima=[str(rowsR[-1][k]) for k in ('venc', 'capital', 'interes', 'iva', 'nocap', 'total')],
                             suma_capital=str(sum(x['capital'] for x in rowsR)), suma_nocap=str(sum(x['nocap'] for x in rowsR)))

# 7) RN-69: ejemplo del documento
out['rn69'] = dict(puntaje=str(D(100) * D('0.40') + D(100) * D('0.25') + D(70) * D('0.25') + D(40) * D('0.10')))

print(json.dumps(out, indent=1, ensure_ascii=False))
