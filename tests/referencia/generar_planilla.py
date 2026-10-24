# -*- coding: utf-8 -*-
"""
Genera casos_referencia.xlsx: verificación independiente de los casos de referencia del motor financiero con
fórmulas de planilla de cálculo (plan preventivo de R1 y R3, RNF-15). Cada resultado lo calcula la planilla; la hoja
Resumen lo compara con el valor esperado de las pruebas PHP (tests/unit/Finanzas).

    python3 tests/referencia/generar_planilla.py   # luego recalcular (Excel lo hace al abrir)
"""
from datetime import date, datetime
from pathlib import Path

from openpyxl import Workbook
from openpyxl.comments import Comment
from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
from openpyxl.formatting.rule import FormulaRule
from openpyxl.utils import get_column_letter

SALIDA = Path(__file__).with_name('casos_referencia.xlsx')
FUENTE = 'Arial'
AZUL, VERDE, NEGRO, GRIS = '0000FF', '008000', '000000', '595959'
F_TITULO = Font(name=FUENTE, size=14, bold=True)
F_SUB = Font(name=FUENTE, size=10, italic=True, color=GRIS)
F_ENC = Font(name=FUENTE, size=10, bold=True, color='FFFFFF')
F_SECC = Font(name=FUENTE, size=11, bold=True)
RELLENO_ENC = PatternFill('solid', fgColor='1F4E78')
RELLENO_ENTRADA = PatternFill('solid', fgColor='FFF2CC')
RELLENO_RES = PatternFill('solid', fgColor='E2EFDA')
BORDE = Border(*(Side(style='thin', color='BFBFBF'),) * 4)
IMPORTE, TASA4, TASA12, PORC = '#,##0.00;-#,##0.00;"-"', '#,##0.0000', '0.000000000000', '0.00%'
FECHA, FECHAHORA = 'dd/mm/yyyy', 'dd/mm/yyyy hh:mm'

wb = Workbook()
resumen = []   # (id, regla, descripción, esperado, referencia calculada, tolerancia, formato)


def hoja(nombre, titulo, subtitulo, anchos):
    ws = wb.create_sheet(nombre)
    ws['A1'] = titulo
    ws['A1'].font = F_TITULO
    ws['A2'] = subtitulo
    ws['A2'].font = F_SUB
    for i, w in enumerate(anchos, 1):
        ws.column_dimensions[get_column_letter(i)].width = w
    ws.sheet_view.showGridLines = False
    return ws


def celda(ws, ref, valor, fmt=None, tipo='formula', negrita=False):
    c = ws[ref]
    c.value = valor
    color = {'entrada': AZUL, 'vinculo': VERDE}.get(tipo, NEGRO)
    c.font = Font(name=FUENTE, size=10, color=color, bold=negrita)
    if tipo == 'entrada':
        c.fill = RELLENO_ENTRADA
    if fmt:
        c.number_format = fmt
    c.border = BORDE
    return c


def etiqueta(ws, ref, texto, negrita=False):
    c = ws[ref]
    c.value = texto
    c.font = Font(name=FUENTE, size=10, bold=negrita)
    return c


def seccion(ws, ref, texto):
    ws[ref] = texto
    ws[ref].font = F_SECC


def encabezados(ws, fila, col, textos):
    for j, t in enumerate(textos):
        c = ws.cell(row=fila, column=col + j, value=t)
        c.font, c.fill, c.border = F_ENC, RELLENO_ENC, BORDE
        c.alignment = Alignment(horizontal='center', vertical='center', wrap_text=True)


def entradas(ws, fila, filas):
    """filas: [(etiqueta, valor, formato, nota)] en A:B; devuelve {etiqueta: ref}."""
    refs = {}
    for k, (txt, val, fmt, nota) in enumerate(filas):
        f = fila + k
        etiqueta(ws, f'A{f}', txt)
        c = celda(ws, f'B{f}', val, fmt, 'entrada')
        if nota:
            c.comment = Comment(nota, 'SIG-CCR')
        refs[txt] = f'$B${f}'
    return refs


def calculos(ws, fila, filas, col='B'):
    refs = {}
    for k, (txt, formula, fmt) in enumerate(filas):
        f = fila + k
        etiqueta(ws, f'A{f}', txt)
        celda(ws, f'{col}{f}', formula, fmt)
        refs[txt] = f'${col}${f}'
    return refs


def vencimiento(primero, k):
    """k-ésimo vencimiento (k = 0, 1, ...) con día de anclaje del primero (RN-52)."""
    return (f'DATE(YEAR({primero}),MONTH({primero})+{k},MIN(DAY({primero}),'
            f'DAY(EOMONTH(DATE(YEAR({primero}),MONTH({primero})+{k},1),0))))')


def esperar(cp, regla, desc, esperado, ref, tol=0.005, fmt=IMPORTE):
    resumen.append((cp, regla, desc, esperado, ref, tol, fmt))


# ---------------------------------------------------------------------------------------------------------------
# Plan francés: CP-F01, CP-F02, CP-F04, CP-F05, CP-F06
ws = hoja('Plan francés', 'Sistema francés, gastos de otorgamiento, TEA y CFTEA',
          'CP-F01, CP-F02, CP-F04 a CP-F06 · RN-25, RN-50, RN-51, RN-63, RN-68', [34, 16, 16, 14, 14, 12, 12, 14, 16, 3, 14])
seccion(ws, 'A4', 'Datos del préstamo')
e = entradas(ws, 5, [
    ('Capital', 100000, IMPORTE, 'Caso de referencia RN-68 del documento.'),
    ('TNA', 0.60, PORC, None),
    ('Cantidad de cuotas', 6, '0', 'La tabla de cuotas tiene 6 filas.'),
    ('Fecha de liquidación', date(2026, 1, 10), FECHA, None),
    ('Gasto administrativo por cuota', 500, IMPORTE, None),
    ('Alícuota de IVA', 0.21, PORC, None),
    ('Gasto de otorgamiento (% del capital)', 0.02, PORC, None),
])
C, TNA, N, LIQ, G, IVA, GOT = (e[k] for k in ('Capital', 'TNA', 'Cantidad de cuotas', 'Fecha de liquidación',
                                              'Gasto administrativo por cuota', 'Alícuota de IVA',
                                              'Gasto de otorgamiento (% del capital)'))
seccion(ws, 'A13', 'Cálculos')
k = calculos(ws, 14, [
    ('Tasa mensual i = TNA / 12', f'={TNA}/12', TASA12),
    ('Cuota ordinaria (capital + interés)', f'=ROUND(IF(B14=0,{C}/{N},{C}*B14/(1-(1+B14)^-{N})),2)', IMPORTE),
    ('Primer vencimiento (liquidación + 30 días)', f'={LIQ}+30', FECHA),
])
I_M, CUOTA, PRIMER = '$B$14', '$B$15', '$B$16'
seccion(ws, 'A18', 'Plan de cuotas')
encabezados(ws, 19, 1, ['N.º', 'Vencimiento', 'Saldo inicial', 'Interés', 'Capital', 'Gastos', 'IVA', 'Total cuota', 'Saldo final'])
for n in range(1, 7):
    f = 19 + n
    celda(ws, f'A{f}', n, '0', 'entrada')
    celda(ws, f'B{f}', '=' + vencimiento(PRIMER, f'A{f}-1'), FECHA)
    celda(ws, f'C{f}', f'={C}' if n == 1 else f'=I{f - 1}', IMPORTE)
    celda(ws, f'D{f}', f'=ROUND(C{f}*{I_M},2)', IMPORTE)
    celda(ws, f'E{f}', f'=IF(A{f}<{N},{CUOTA}-D{f},C{f})', IMPORTE)
    celda(ws, f'F{f}', f'={G}', IMPORTE)
    celda(ws, f'G{f}', f'=ROUND((D{f}+F{f})*{IVA},2)', IMPORTE)
    celda(ws, f'H{f}', f'=E{f}+D{f}+F{f}+G{f}', IMPORTE)
    celda(ws, f'I{f}', f'=C{f}-E{f}', IMPORTE)
etiqueta(ws, 'A26', 'Totales', True)
for col in 'DEFGH':
    celda(ws, f'{col}26', f'=SUM({col}20:{col}25)', IMPORTE, negrita=True)
etiqueta(ws, 'A27', 'La última cuota toma el saldo pendiente como capital (ajuste final, RN-51).')
ws['A27'].font = F_SUB

seccion(ws, 'A29', 'Gastos de otorgamiento y monto neto (RN-25)')
g = calculos(ws, 30, [
    ('Gasto de otorgamiento', f'=ROUND({C}*{GOT},2)', IMPORTE),
    ('IVA del gasto', f'=ROUND(B30*{IVA},2)', IMPORTE),
    ('Monto neto acreditado', f'={C}-B30-B31', IMPORTE),
])
seccion(ws, 'A34', 'Tasas (RN-63)')
calculos(ws, 35, [
    ('TEA = (1 + i)^12 − 1', f'=(1+{I_M})^12-1', PORC),
    ('TIR mensual de los flujos', '=IRR(K19:K25)', TASA12),
    ('CFTEA = (1 + TIR)^12 − 1', '=(1+B36)^12-1', PORC),
    ('TEA en % (2 decimales)', '=ROUND(B35*100,2)', '0.00'),
    ('CFTEA en % (2 decimales)', '=ROUND(B37*100,2)', '0.00'),
])
encabezados(ws, 18, 11, ['Flujo CFTEA'])
celda(ws, 'K19', '=-B32', IMPORTE)
for n in range(1, 7):
    celda(ws, f'K{19 + n}', f'=H{19 + n}', IMPORTE)
ws['K26'] = 'Monto neto (negativo) y totales de cada cuota'
ws['K26'].font = F_SUB

P = "'Plan francés'!"
esperar('CP-F01', 'RN-50', 'Cuota ordinaria', 19701.75, P + CUOTA)
esperar('CP-F01', 'RN-68', '1.ª cuota: capital', 14701.75, P + '$E$20')
esperar('CP-F01', 'RN-68', '1.ª cuota: interés', 5000.00, P + '$D$20')
esperar('CP-F01', 'RN-68', '1.ª cuota: gasto administrativo', 500.00, P + '$F$20')
esperar('CP-F01', 'RN-68', '1.ª cuota: IVA', 1155.00, P + '$G$20')
esperar('CP-F01', 'RN-68', '1.ª cuota: total', 21356.75, P + '$H$20')
esperar('CP-F02', 'RN-51', 'Suma de capitales', 100000.00, P + '$E$26')
esperar('CP-F02', 'RN-51', 'Última cuota: capital (ajuste final)', 18763.55, P + '$E$25')
esperar('CP-F02', 'RN-51', 'Total a pagar', 125664.67, P + '$H$26')
esperar('CP-F04', 'RN-25', 'Gasto de otorgamiento', 2000.00, P + '$B$30')
esperar('CP-F04', 'RN-25', 'IVA del gasto', 420.00, P + '$B$31')
esperar('CP-F04', 'RN-25', 'Monto neto', 97580.00, P + '$B$32')
esperar('CP-F05', 'RN-63', 'TEA (%)', 79.59, P + '$B$38', fmt='0.00')
esperar('CP-F06', 'RN-63', 'CFTEA (%)', 146.33, P + '$B$39', fmt='0.00')

# ---------------------------------------------------------------------------------------------------------------
# Tasa cero: CP-F03
ws = hoja('Tasa cero', 'Préstamo sin interés', 'CP-F03 · RN-50: con tasa 0 la cuota es capital / n y la última absorbe el redondeo',
          [34, 16, 16, 14, 14])
e = entradas(ws, 4, [('Capital', 1000, IMPORTE, None), ('TNA', 0, PORC, None), ('Cantidad de cuotas', 3, '0', None)])
calculos(ws, 8, [('Tasa mensual', '=$B$5/12', TASA12),
                 ('Cuota', '=ROUND(IF(B8=0,$B$4/$B$6,$B$4*B8/(1-(1+B8)^-$B$6)),2)', IMPORTE)])
encabezados(ws, 11, 1, ['N.º', 'Saldo inicial', 'Interés', 'Capital', 'Saldo final'])
for n in range(1, 4):
    f = 11 + n
    celda(ws, f'A{f}', n, '0', 'entrada')
    celda(ws, f'B{f}', '=$B$4' if n == 1 else f'=E{f - 1}', IMPORTE)
    celda(ws, f'C{f}', f'=ROUND(B{f}*$B$8,2)', IMPORTE)
    celda(ws, f'D{f}', f'=IF(A{f}<$B$6,$B$9-C{f},B{f})', IMPORTE)
    celda(ws, f'E{f}', f'=B{f}-D{f}', IMPORTE)
for n, v in enumerate([333.33, 333.33, 333.34], 1):
    esperar('CP-F03', 'RN-50', f'Capital de la cuota {n}', v, f"'Tasa cero'!$D${11 + n}")

# ---------------------------------------------------------------------------------------------------------------
# Calendario: CP-F07, CP-F08
ws = hoja('Calendario', 'Calendario de vencimientos', 'CP-F07 y CP-F08 · RN-52: primer vencimiento a 30 días de la liquidación; '
          'los siguientes, el mismo día de cada mes o el último día si el mes es más corto', [34, 16, 16])
for base, (titulo, liq, cant, esperados, cp) in enumerate([
        ('Liquidación 01/01/2026 (día de anclaje 31)', date(2026, 1, 1), 4,
         [date(2026, 1, 31), date(2026, 2, 28), date(2026, 3, 31), date(2026, 4, 30)], 'CP-F07'),
        ('Liquidación 31/12/2027 (año bisiesto)', date(2027, 12, 31), 2, [date(2028, 1, 30), date(2028, 2, 29)], 'CP-F08')]):
    f0 = 4 + base * 10
    seccion(ws, f'A{f0}', titulo)
    entradas(ws, f0 + 1, [('Fecha de liquidación', liq, FECHA, None)])
    calculos(ws, f0 + 2, [('Primer vencimiento', f'=B{f0 + 1}+30', FECHA)])
    encabezados(ws, f0 + 3, 1, ['N.º', 'Vencimiento'])
    for n in range(cant):
        f = f0 + 4 + n
        celda(ws, f'A{f}', n + 1, '0', 'entrada')
        celda(ws, f'B{f}', '=' + vencimiento(f'$B${f0 + 2}', f'A{f}-1'), FECHA)
        esperar(cp, 'RN-52', f'Vencimiento {n + 1}', esperados[n], f'Calendario!$B${f}', tol=0, fmt=FECHA)

# ---------------------------------------------------------------------------------------------------------------
# Punitorios: CP-F09 a CP-F12
ws = hoja('Punitorios', 'Interés punitorio diario', 'CP-F09 a CP-F12 · RN-34, RN-35, RN-53', [44, 16, 16, 16])
seccion(ws, 'A4', 'Parámetros')
e = entradas(ws, 5, [
    ('Tasa punitoria diaria configurada', 0.0012, TASA12, None),
    ('Tope: proporción de la TNA', 0.5, PORC, 'Tasa diaria máxima = tope × TNA / 365 (RN-34).'),
    ('TNA del préstamo', 0.60, PORC, None),
    ('Alícuota de IVA', 0.21, PORC, None),
    ('Capital vencido de la cuota', 15436.84, IMPORTE, 'Capital de la cuota 2 del plan CP-F01.'),
    ('Vencimiento de la cuota', date(2026, 3, 9), FECHA, None),
    ('Días de tolerancia', 3, '0', 'Si el atraso no supera la tolerancia, los punitorios no son exigibles (RN-53).'),
])
seccion(ws, 'A13', 'Tasa aplicada y tramo diario')
calculos(ws, 14, [
    ('Tasa máxima diaria', '=$B$6*$B$7/365', TASA12),
    ('Tasa aplicada (12 decimales)', '=ROUND(MIN($B$5,B14),12)', TASA12),
    ('Punitorio diario (4 decimales)', '=ROUND($B$9*B15,4)', TASA4),
    ('IVA del punitorio diario (4 decimales)', '=ROUND(B16*$B$8,4)', TASA4),
])
seccion(ws, 'A19', 'Pagos con la tasa aplicada')
encabezados(ws, 20, 1, ['Caso', 'Fecha de pago', 'Días de atraso', '¿Exigible?'])
encabezados(ws, 20, 5, ['Punitorio', 'IVA'])
ws.column_dimensions['E'].width = 14
ws.column_dimensions['F'].width = 14
for j, (cp, pago) in enumerate([('CP-F10', date(2026, 3, 12)), ('CP-F11', date(2026, 3, 13))]):
    f = 21 + j
    etiqueta(ws, f'A{f}', cp)
    celda(ws, f'B{f}', pago, FECHA, 'entrada')
    celda(ws, f'C{f}', f'=B{f}-$B$10', '0')
    celda(ws, f'D{f}', f'=IF(C{f}>$B$11,"Sí","No")')
    celda(ws, f'E{f}', f'=IF(D{f}="Sí",ROUND($B$16*C{f},2),0)', IMPORTE)
    celda(ws, f'F{f}', f'=IF(D{f}="Sí",ROUND($B$17*C{f},2),0)', IMPORTE)
seccion(ws, 'A24', 'Cambio de tasa dentro del período (RN-35): CP-F12')
e2 = entradas(ws, 25, [
    ('Fecha de pago', date(2026, 3, 19), FECHA, None),
    ('Tasa diaria hasta el', 0.0008, TASA12, None),
    ('… fecha de fin de la primera tasa', date(2026, 3, 14), FECHA, None),
    ('Tasa diaria desde el día siguiente', 0.0005, TASA12, None),
])
calculos(ws, 29, [
    ('Días de atraso', '=$B$25-$B$10', '0'),
    ('Días con la primera tasa', '=MAX(0,MIN($B$25,$B$27)-$B$10)', '0'),
    ('Días con la segunda tasa', '=B29-B30', '0'),
    ('Punitorio diario, primera tasa', '=ROUND($B$9*$B$26,4)', TASA4),
    ('Punitorio diario, segunda tasa', '=ROUND($B$9*$B$28,4)', TASA4),
    ('Punitorio total', '=IF(B29>$B$11,ROUND(B32*B30+B33*B31,2),0)', IMPORTE),
    ('IVA total', '=IF(B29>$B$11,ROUND(ROUND(B32*$B$8,4)*B30+ROUND(B33*$B$8,4)*B31,2),0)', IMPORTE),
])
esperar('CP-F09', 'RN-34', 'Tasa diaria aplicada', 0.000821917808, 'Punitorios!$B$15', tol=5e-13, fmt=TASA12)
esperar('CP-F10', 'RN-53', '3 días: punitorio exigible', 0, 'Punitorios!$E$21')
esperar('CP-F10', 'RN-53', '3 días: IVA exigible', 0, 'Punitorios!$F$21')
esperar('CP-F11', 'RN-53', 'Punitorio diario', 12.6878, 'Punitorios!$B$16', tol=0.00005, fmt=TASA4)
esperar('CP-F11', 'RN-53', 'IVA del punitorio diario', 2.6644, 'Punitorios!$B$17', tol=0.00005, fmt=TASA4)
esperar('CP-F11', 'RN-53', '4 días: punitorio', 50.75, 'Punitorios!$E$22')
esperar('CP-F11', 'RN-53', '4 días: IVA', 10.66, 'Punitorios!$F$22')
esperar('CP-F12', 'RN-35', '10 días con cambio de tasa: punitorio', 100.34, 'Punitorios!$B$34')
esperar('CP-F12', 'RN-35', '10 días con cambio de tasa: IVA', 21.07, 'Punitorios!$B$35')

# ---------------------------------------------------------------------------------------------------------------
# Horas hábiles: CP-F13 a CP-F15
ws = hoja('Horas hábiles', 'Plazo de 72 horas hábiles', 'CP-F13 a CP-F15 · RN-49: cuentan las horas de lunes a viernes no '
          'feriados; si empieza en un día inhábil, corre desde las 0:00 del siguiente día hábil', [44, 18, 18, 18])
e = entradas(ws, 4, [('Plazo en horas hábiles', 72, '0', None),
                     ('Feriado (solo CP-F15)', date(2026, 10, 26), FECHA, 'Lunes 26/10/2026 como feriado de prueba.')])
encabezados(ws, 7, 1, ['Concepto', 'CP-F13', 'CP-F14', 'CP-F15'])
inicios = [datetime(2026, 10, 23, 10), datetime(2026, 10, 24, 15), datetime(2026, 10, 23, 10)]
filas = ['Inicio (aprobación)', '¿Día hábil?', 'Inicio del cómputo', 'Horas del primer día', 'Horas restantes',
         'Días hábiles completos', 'Horas del último día', 'Vencimiento']
for j, txt in enumerate(filas):
    etiqueta(ws, f'A{8 + j}', txt)
for j, ini in enumerate(inicios):
    col = 'BCD'[j]
    fer = ',$B$5' if j == 2 else ''
    celda(ws, f'{col}8', ini, FECHAHORA, 'entrada')
    celda(ws, f'{col}9', f'=IF(NETWORKDAYS(INT({col}8),INT({col}8){fer})=1,"Sí","No")')
    celda(ws, f'{col}10', f'=IF({col}9="Sí",{col}8,WORKDAY(INT({col}8),1{fer}))', FECHAHORA)
    celda(ws, f'{col}11', f'=ROUND((INT({col}10)+1-{col}10)*24,6)', '0.00')
    celda(ws, f'{col}12', f'=$B$4-{col}11', '0.00')
    celda(ws, f'{col}13', f'=INT({col}12/24)', '0')
    celda(ws, f'{col}14', f'=ROUND(MOD({col}12,24),6)', '0.00')
    celda(ws, f'{col}15', f'=IF({col}12<=0,{col}10+$B$4/24,IF({col}14=0,WORKDAY(INT({col}10),{col}13{fer})+1,'
                          f'WORKDAY(INT({col}10),{col}13+1{fer})+{col}14/24))', FECHAHORA)
esperados = [datetime(2026, 10, 28, 10), datetime(2026, 10, 29, 0), datetime(2026, 10, 29, 10)]
descr = ['Aprobación vie 23/10 10:00', 'Aprobación sáb 24/10 15:00', 'Vie 23/10 10:00 con feriado lun 26/10']
for j, (cp, esp) in enumerate(zip(['CP-F13', 'CP-F14', 'CP-F15'], esperados)):
    esperar(cp, 'RN-49', descr[j], esp, f"'Horas hábiles'!${'BCD'[j]}$15", tol=1 / 1440, fmt=FECHAHORA)

# ---------------------------------------------------------------------------------------------------------------
# Capacidad de pago: CP-F16, CP-F17
ws = hoja('Capacidad de pago', 'Capacidad de pago y capital máximo', 'CP-F16 y CP-F17 · CU-87 con el IVA del interés '
          '(decisión D-01)', [52, 18, 18])
seccion(ws, 'A4', 'Capacidad de pago (CP-F16)')
entradas(ws, 5, [('Ingresos mensuales', 500000, IMPORTE, None), ('Afectación máxima de ingresos', 0.30, PORC, None),
                 ('Obligaciones mensuales vigentes', 50000, IMPORTE, None)])
calculos(ws, 8, [('Capacidad de pago', '=ROUND(B5*B6-B7,2)', IMPORTE)])
seccion(ws, 'A10', 'Capital máximo (CP-F17)')
entradas(ws, 11, [('TNA', 0.60, PORC, None), ('Cantidad de cuotas', 12, '0', None),
                  ('Gasto administrativo por cuota', 500, IMPORTE, None), ('Alícuota de IVA', 0.21, PORC, None),
                  ('Paso de redondeo del capital', 1000, IMPORTE, None)])
calculos(ws, 16, [
    ('Tasa mensual i', '=B11/12', TASA12),
    ('Factor de anualidad a = (1 − (1 + i)^−n) / i', '=(1-(1+B16)^-B12)/B16', TASA12),
    ('Disponible para capital e interés = K − g·(1 + IVA)', '=B8-B13*(1+B14)', IMPORTE),
    ('Capital bruto = disponible / (1/a + IVA·i)', '=B18/(1/B17+B14*B16)', TASA4),
    ('Capital candidato (múltiplo del paso)', '=INT(B19/B15)*B15', IMPORTE),
])
encabezados(ws, 22, 1, ['1.ª cuota (la de mayor importe)', 'Capital candidato', 'Candidato + paso'])
etiqueta(ws, 'A23', 'Capital')
celda(ws, 'B23', '=B20', IMPORTE)
celda(ws, 'C23', '=B20+B15', IMPORTE)
for j, (txt, fb) in enumerate([
        ('Cuota ordinaria', '=ROUND({c}23*$B$16/(1-(1+$B$16)^-$B$12),2)'),
        ('Interés', '=ROUND({c}23*$B$16,2)'),
        ('IVA de interés y gasto', '=ROUND(({c}25+$B$13)*$B$14,2)'),
        ('Total de la 1.ª cuota', '={c}24+$B$13+{c}26'),
        ('¿Dentro de la capacidad?', '=IF({c}27<=$B$8,"Sí","No")')]):
    etiqueta(ws, f'A{24 + j}', txt)
    for c in 'BC':
        celda(ws, f'{c}{24 + j}', fb.format(c=c), IMPORTE)
calculos(ws, 30, [('Capital máximo', '=IF(AND(B28="Sí",C28="No"),B23,"revisar el paso")', IMPORTE)])
esperar('CP-F16', 'CU-87', 'Capacidad de pago', 100000.00, "'Capacidad de pago'!$B$8")
esperar('CP-F17', 'CU-87', 'Capital máximo', 805000, "'Capacidad de pago'!$B$30")
esperar('CP-F17', 'CU-87', '1.ª cuota con el capital máximo', 99881.96, "'Capacidad de pago'!$B$27")
esperar('CP-F17', 'CU-87', '1.ª cuota con 806.000 (supera la capacidad)', 100005.28, "'Capacidad de pago'!$C$27")

# ---------------------------------------------------------------------------------------------------------------
# Imputación: CP-F18 a CP-F21
ws = hoja('Imputación', 'Imputación de cobros por prelación', 'CP-F18 a CP-F21 · RN-29: punitorio, interés ordinario, gastos, '
          'IVA y capital, cuota por cuota desde la más antigua; RN-56 pago parcial excepcional; RN-57 excedente',
          [26, 22, 14, 14, 16, 16, 16])
entradas(ws, 4, [('Cobro ordinario CP-F18', 42304.08, IMPORTE, None), ('Cobro ordinario CP-F19', 20000, IMPORTE, None),
                 ('Pago excepcional CP-F20', 6000, IMPORTE, None), ('Pago electrónico CP-F21', 42400, IMPORTE, None)])
conceptos = [(2, 'Punitorio', 50.75), (2, 'Interés ordinario', 4264.91), (2, 'Gastos y cargos', 500.00), (2, 'IVA', 1011.29),
             (2, 'Capital', 15436.84), (3, 'Interés ordinario', 3493.07), (3, 'Gastos y cargos', 500.00), (3, 'IVA', 838.54),
             (3, 'Capital', 16208.68)]
encabezados(ws, 9, 1, ['Cuota', 'Concepto', 'Importe', 'Acumulado', 'Imputado CP-F18', 'Imputado CP-F20', 'Imputado CP-F21'])
for j, (cu, con, imp) in enumerate(conceptos):
    f = 10 + j
    celda(ws, f'A{f}', cu, '0', 'entrada')
    celda(ws, f'B{f}', con, None, 'entrada')
    celda(ws, f'C{f}', imp, IMPORTE, 'entrada')
    celda(ws, f'D{f}', f'=SUM($C$10:C{f})', IMPORTE)
    for col, monto in zip('EFG', ['$B$4', '$B$6', '$B$7']):
        celda(ws, f'{col}{f}', f'=MIN(C{f},MAX(0,{monto}-(D{f}-C{f})))', IMPORTE)
ws['B10'].comment = Comment('El IVA de la cuota 2 incluye el IVA del punitorio (1.000,63 + 10,66).', 'SIG-CCR')
etiqueta(ws, 'B19', 'Totales', True)
for col in 'CEFG':
    celda(ws, f'{col}19', f'=SUM({col}10:{col}18)', IMPORTE, negrita=True)
encabezados(ws, 21, 1, ['', 'Resultado', 'Total cuota', '', 'CP-F18', 'CP-F20', 'CP-F21'])
for j, cu in enumerate([2, 3]):
    f = 22 + j
    etiqueta(ws, f'B{f}', f'¿Cuota {cu} cancelada?')
    celda(ws, f'C{f}', f'=SUMIF($A$10:$A$18,{cu},$C$10:$C$18)', IMPORTE)
    for col in 'EFG':
        celda(ws, f'{col}{f}', f'=IF(ROUND(SUMIF($A$10:$A$18,{cu},{col}$10:{col}$18)-C{f},2)=0,1,0)', '0')
etiqueta(ws, 'B24', 'Cuotas canceladas')
etiqueta(ws, 'B25', 'Remanente (saldo a favor)')
for col, monto in zip('EFG', ['$B$4', '$B$6', '$B$7']):
    celda(ws, f'{col}24', f'={col}22+{col}23', '0')
    celda(ws, f'{col}25', f'=ROUND(MAX(0,{monto}-{col}19),2)', IMPORTE)
seccion(ws, 'A27', 'Cobro ordinario (RN-29): debe coincidir con cuotas completas y consecutivas')
calculos(ws, 28, [
    ('Cuota 2', '=C22', IMPORTE), ('Cuotas 2 y 3', '=C22+C23', IMPORTE),
    ('CP-F18', '=IF(OR(ROUND($B$4-C28,2)=0,ROUND($B$4-C29,2)=0),"Aceptado","Rechazado")', None),
    ('CP-F19', '=IF(OR(ROUND($B$5-C28,2)=0,ROUND($B$5-C29,2)=0),"Aceptado","Rechazado")', None),
], col='C')
esperar('CP-F18', 'RN-29', 'Cobro ordinario de 42.304,08', 'Aceptado', 'Imputación!$C$30', fmt=None)
esperar('CP-F18', 'RN-29', 'Cuotas canceladas', 2, 'Imputación!$E$24', fmt='0')
esperar('CP-F18', 'RN-29', 'Remanente', 0, 'Imputación!$E$25')
esperar('CP-F19', 'RN-29', 'Cobro ordinario de 20.000 (fracción de cuota)', 'Rechazado', 'Imputación!$C$31', fmt=None)
esperar('CP-F20', 'RN-56', 'Capital imputado a la cuota 2', 173.05, 'Imputación!$F$14')
esperar('CP-F20', 'RN-56', 'Cuotas canceladas', 0, 'Imputación!$F$24', fmt='0')
esperar('CP-F21', 'RN-57', 'Excedente como saldo a favor', 95.92, 'Imputación!$G$25')

# ---------------------------------------------------------------------------------------------------------------
# Cancelación anticipada: CP-F22
ws = hoja('Cancelación', 'Cancelación anticipada total', 'CP-F22 · RN-67 sobre el plan de CP-F01: cuota 1 pagada, cuota 2 '
          'vencida con punitorios, cuota 3 en curso', [60, 18])
entradas(ws, 4, [('Punitorios exigibles de la cuota 2', 120.50, IMPORTE, None),
                 ('IVA de los punitorios', 25.31, IMPORTE, None), ('Fecha de pago', date(2026, 3, 20), FECHA, None)])
calculos(ws, 8, [
    ('Total de la cuota 2 (vencida)', f"={P}$H$21", IMPORTE),
    ('Cuotas vencidas con punitorios', '=B8+B4+B5', IMPORTE),
    ('Capital no vencido (cuotas 3 a 6)', f"=SUM({P}$E$22:$E$25)", IMPORTE),
    ('Vencimiento anterior al pago (cuota 2)', f"={P}$B$21", FECHA),
    ('Días transcurridos de la cuota en curso', '=B6-B11', '0'),
    ('Interés de la cuota 3 (período completo)', f"={P}$D$22", IMPORTE),
    ('Interés en curso = interés × días / 30 (con tope)', '=MIN(ROUND(B13*B12/30,2),B13)', IMPORTE),
    ('IVA del interés en curso', f"=ROUND(B14*{P}{IVA},2)", IMPORTE),
    ('Total para cancelar', '=B9+B10+B14+B15', IMPORTE),
])
for f in (8, 10, 11, 13):
    ws[f'B{f}'].font = Font(name=FUENTE, size=10, color=VERDE)
esperar('CP-F22', 'RN-67', 'Cuotas vencidas', 21348.19, 'Cancelación!$B$9')
esperar('CP-F22', 'RN-67', 'Capital no vencido', 69861.41, 'Cancelación!$B$10')
esperar('CP-F22', 'RN-67', 'Interés en curso (11 días)', 1280.79, 'Cancelación!$B$14')
esperar('CP-F22', 'RN-67', 'IVA del interés en curso', 268.97, 'Cancelación!$B$15')
esperar('CP-F22', 'RN-67', 'Total para cancelar', 92759.36, 'Cancelación!$B$16')

# ---------------------------------------------------------------------------------------------------------------
# Refinanciación: CP-F23, CP-F24
ws = hoja('Refinanciación', 'Refinanciación', 'CP-F23 y CP-F24 · RN-70: el capital pendiente se refinancia con sistema francés; '
          'intereses, gastos, IVA y punitorios vencidos se distribuyen sin capitalizar', [48, 14, 14, 14, 14, 12, 12, 14, 14])
seccion(ws, 'A4', 'Deuda al corte del 20/03/2026 (cuotas 2 a 6 de CP-F01)')
entradas(ws, 5, [('Punitorios de la cuota 2 al corte', 120.50, IMPORTE, None), ('IVA de esos punitorios', 25.31, IMPORTE, None),
                 ('Punitorios devengados después del corte', 35.40, IMPORTE, 'Se incorporan al aplicar la refinanciación.')])
calculos(ws, 8, [
    ('Capital a refinanciar', f"=SUM({P}$E$21:$E$25)", IMPORTE),
    ('No capitalizable al corte', f"={P}$D$21+{P}$F$21+{P}$G$21+B5+B6", IMPORTE),
    ('No capitalizable al aplicar', '=B9+B7', IMPORTE),
])
for f in (8, 9):
    ws[f'B{f}'].font = Font(name=FUENTE, size=10, color=VERDE)
seccion(ws, 'A12', 'Nuevo plan')
entradas(ws, 13, [('TNA', 0.48, PORC, None), ('Cantidad de cuotas', 12, '0', None),
                  ('Fecha de aplicación', date(2026, 3, 25), FECHA, None), ('Gasto administrativo por cuota', 0, IMPORTE, None),
                  ('Alícuota de IVA', 0.21, PORC, None)])
calculos(ws, 18, [
    ('Tasa mensual', '=B13/12', TASA12),
    ('Cuota ordinaria', '=ROUND(B8*B18/(1-(1+B18)^-B14),2)', IMPORTE),
    ('Primer vencimiento', '=B15+30', FECHA),
    ('Parte no capitalizable por cuota', '=ROUND(B10/B14,2)', IMPORTE),
])
encabezados(ws, 23, 1, ['N.º', 'Vencimiento', 'Saldo inicial', 'Interés', 'Capital', 'Gastos', 'IVA', 'No capitalizable', 'Total'])
for n in range(1, 13):
    f = 23 + n
    celda(ws, f'A{f}', n, '0', 'entrada')
    celda(ws, f'B{f}', '=' + vencimiento('$B$20', f'A{f}-1'), FECHA)
    celda(ws, f'C{f}', '=$B$8' if n == 1 else f'=C{f - 1}-E{f - 1}', IMPORTE)
    celda(ws, f'D{f}', f'=ROUND(C{f}*$B$18,2)', IMPORTE)
    celda(ws, f'E{f}', f'=IF(A{f}<$B$14,$B$19-D{f},C{f})', IMPORTE)
    celda(ws, f'F{f}', '=$B$16', IMPORTE)
    celda(ws, f'G{f}', f'=ROUND((D{f}+F{f})*$B$17,2)', IMPORTE)
    celda(ws, f'H{f}', f'=IF(A{f}<$B$14,$B$21,$B$10-$B$21*($B$14-1))', IMPORTE)
    celda(ws, f'I{f}', f'=E{f}+D{f}+F{f}+G{f}+H{f}', IMPORTE)
etiqueta(ws, 'A36', 'Totales', True)
for col in 'DEGHI':
    celda(ws, f'{col}36', f'=SUM({col}24:{col}35)', IMPORTE, negrita=True)
R = 'Refinanciación!'
esperar('CP-F23', 'RN-70', 'Capital a refinanciar', 85298.25, R + '$B$8')
esperar('CP-F23', 'RN-70', 'No capitalizable al corte', 5911.35, R + '$B$9')
esperar('CP-F24', 'RN-70', 'Cuota ordinaria', 9088.71, R + '$B$19')
esperar('CP-F24', 'RN-70', '1.ª cuota: vencimiento', date(2026, 4, 24), R + '$B$24', tol=0, fmt=FECHA)
esperar('CP-F24', 'RN-70', '1.ª cuota: no capitalizable', 495.56, R + '$H$24')
esperar('CP-F24', 'RN-70', '1.ª cuota: total', 10300.78, R + '$I$24')
esperar('CP-F24', 'RN-70', 'Última cuota: vencimiento', date(2027, 3, 24), R + '$B$35', tol=0, fmt=FECHA)
esperar('CP-F24', 'RN-70', 'Última cuota: no capitalizable', 495.59, R + '$H$35')
esperar('CP-F24', 'RN-70', 'Última cuota: total', 9657.75, R + '$I$35')
esperar('CP-F24', 'RN-70', 'Suma de capitales', 85298.25, R + '$E$36')

# ---------------------------------------------------------------------------------------------------------------
# Modelo de evaluación: CP-F25 a CP-F27
ws = hoja('Evaluación', 'Modelo de evaluación crediticia', 'CP-F25 a CP-F27 · RN-69: suma ponderada de cuatro indicadores de '
          '0 a 100, categoría por cortes y situaciones excluyentes', [46, 14, 14, 14, 3, 30, 12, 12])
seccion(ws, 'F4', 'Parámetros iniciales (RN-69)')
param = [
    ('Peso: situación BCRA', 0.40), ('Peso: comportamiento interno', 0.25), ('Peso: endeudamiento', 0.25), ('Peso: antigüedad', 0.10),
    ('BCRA situación 1', 100), ('BCRA situación 2', 60), ('BCRA situación 3', 20), ('BCRA situación 4 a 6', 0), ('BCRA sin deudas', 70),
    ('Atraso hasta 0 días', 100), ('Atraso hasta 30 días', 70), ('Atraso hasta 60 días', 30), ('Atraso mayor', 0), ('Sin antecedentes', 50),
    ('Endeudamiento hasta 10 %', 100), ('Endeudamiento hasta 20 %', 70), ('Endeudamiento hasta 30 %', 40), ('Endeudamiento mayor', 0),
    ('Antigüedad desde 24 meses', 100), ('Antigüedad desde 12 meses', 70), ('Antigüedad desde 6 meses', 40), ('Antigüedad menor', 20),
    ('Categoría A desde', 80), ('Categoría B desde', 65), ('Categoría C desde', 50), ('Categoría D desde', 35),
]
pr = {}
for j, (txt, v) in enumerate(param):
    f = 5 + j
    ws[f'F{f}'] = txt
    ws[f'F{f}'].font = Font(name=FUENTE, size=10)
    celda(ws, f'G{f}', v, '0.00' if v < 1 else '0', 'entrada')
    pr[txt] = f'$G${f}'
encabezados(ws, 4, 1, ['Dato o cálculo', 'CP-F25', 'CP-F26', 'CP-F27'])
datos = [('Peor situación BCRA de 12 meses (vacío: sin deudas)', [1, 5, None]),
         ('Situación BCRA vigente (vacío: sin deudas)', [1, 5, None]),
         ('Máximo atraso interno en días (vacío: sin antecedentes)', [0, None, None]),
         ('Cuotas vigentes y comprometidas', [15000, 0, 25000]),
         ('Ingresos mensuales', [100000, 100000, 100000]),
         ('Antigüedad laboral en meses', [8, 30, 12])]
for j, (txt, vals) in enumerate(datos):
    f = 5 + j
    etiqueta(ws, f'A{f}', txt)
    for c, v in zip('BCD', vals):
        celda(ws, f'{c}{f}', v, '0' if j != 3 and j != 4 else IMPORTE, 'entrada')
for j, (txt, fb, fmt) in enumerate([
        ('Valor BCRA', f'=IF(ISBLANK({{c}}5),{pr["BCRA sin deudas"]},IF({{c}}5=1,{pr["BCRA situación 1"]},IF({{c}}5=2,'
                       f'{pr["BCRA situación 2"]},IF({{c}}5=3,{pr["BCRA situación 3"]},{pr["BCRA situación 4 a 6"]}))))', '0'),
        ('Valor comportamiento', f'=IF(ISBLANK({{c}}7),{pr["Sin antecedentes"]},IF({{c}}7<=0,{pr["Atraso hasta 0 días"]},'
                                 f'IF({{c}}7<=30,{pr["Atraso hasta 30 días"]},IF({{c}}7<=60,{pr["Atraso hasta 60 días"]},'
                                 f'{pr["Atraso mayor"]}))))', '0'),
        ('Relación cuotas / ingresos', '=IF({c}9>0,{c}8/{c}9,1)', PORC),
        ('Valor endeudamiento', f'=IF({{c}}13<=0.1,{pr["Endeudamiento hasta 10 %"]},IF({{c}}13<=0.2,{pr["Endeudamiento hasta 20 %"]},'
                                f'IF({{c}}13<=0.3,{pr["Endeudamiento hasta 30 %"]},{pr["Endeudamiento mayor"]})))', '0'),
        ('Valor antigüedad', f'=IF({{c}}10>=24,{pr["Antigüedad desde 24 meses"]},IF({{c}}10>=12,{pr["Antigüedad desde 12 meses"]},'
                             f'IF({{c}}10>=6,{pr["Antigüedad desde 6 meses"]},{pr["Antigüedad menor"]})))', '0'),
        ('Puntaje', f'=ROUND({{c}}11*{pr["Peso: situación BCRA"]}+{{c}}12*{pr["Peso: comportamiento interno"]}'
                    f'+{{c}}14*{pr["Peso: endeudamiento"]}+{{c}}15*{pr["Peso: antigüedad"]},2)', '0.00'),
        ('Categoría', f'=IF({{c}}16>={pr["Categoría A desde"]},"A",IF({{c}}16>={pr["Categoría B desde"]},"B",'
                      f'IF({{c}}16>={pr["Categoría C desde"]},"C",IF({{c}}16>={pr["Categoría D desde"]},"D","E"))))', None),
        ('Resultado', '=IF(AND(NOT(ISBLANK({c}6)),{c}6>=4),"Desfavorable",IF(OR({c}17="A",{c}17="B"),"Favorable",'
                      'IF({c}17="C","RequiereAnalisisGerencial","Desfavorable")))', None)]):
    f = 11 + j
    etiqueta(ws, f'A{f}', txt, negrita=txt in ('Puntaje', 'Categoría', 'Resultado'))
    for c in 'BCD':
        celda(ws, f'{c}{f}', fb.format(c=c), fmt)
ws['A20'] = 'Situaciones excluyentes: 4, 5 y 6 vigentes producen resultado Desfavorable cualquiera sea el puntaje.'
ws['A20'].font = F_SUB
esperar('CP-F25', 'RN-69', 'Puntaje', 86.50, 'Evaluación!$B$16', fmt='0.00')
esperar('CP-F25', 'RN-69', 'Categoría', 'A', 'Evaluación!$B$17', fmt=None)
esperar('CP-F25', 'RN-69', 'Resultado', 'Favorable', 'Evaluación!$B$18', fmt=None)
esperar('CP-F26', 'RN-69', 'Resultado con situación 5 vigente', 'Desfavorable', 'Evaluación!$C$18', fmt=None)
esperar('CP-F27', 'RN-69', 'Puntaje', 57.50, 'Evaluación!$D$16', fmt='0.00')
esperar('CP-F27', 'RN-69', 'Categoría', 'C', 'Evaluación!$D$17', fmt=None)
esperar('CP-F27', 'RN-69', 'Resultado', 'RequiereAnalisisGerencial', 'Evaluación!$D$18', fmt=None)

# ---------------------------------------------------------------------------------------------------------------
# Resumen
ws = wb.worksheets[0]
ws.title = 'Resumen'
ws.sheet_view.showGridLines = False
ws['A1'] = 'SIG-CCR · Verificación independiente de los casos de referencia del motor financiero'
ws['A1'].font = F_TITULO
ws['A2'] = ('Cada valor calculado sale de las fórmulas de las demás hojas; el esperado es el que verifican las pruebas PHP '
            '(tests/unit/Finanzas). Plan preventivo de R1 y R3 · RNF-15.')
ws['A2'].font = F_SUB
ws['A3'] = 'Celdas amarillas con texto azul: datos de entrada. Texto negro: fórmulas. Texto verde: vínculos a otra hoja.'
ws['A3'].font = F_SUB
for i, w in enumerate([10, 9, 46, 26, 26, 14, 12, 11], 1):
    ws.column_dimensions[get_column_letter(i)].width = w
encabezados(ws, 6, 1, ['Caso', 'Regla', 'Qué se verifica', 'Esperado (pruebas)', 'Calculado (planilla)', 'Diferencia', 'Estado', 'Tolerancia'])
for j, (cp, regla, desc, esp, ref, tol, fmt) in enumerate(resumen):
    f = 7 + j
    etiqueta(ws, f'A{f}', cp)
    etiqueta(ws, f'B{f}', regla)
    etiqueta(ws, f'C{f}', desc)
    celda(ws, f'D{f}', esp, fmt, 'entrada')
    celda(ws, f'E{f}', f'={ref}', fmt, 'vinculo')
    celda(ws, f'F{f}', f'=IF(ISNUMBER(D{f}),IF(ABS(E{f}-D{f})<1E-9,0,E{f}-D{f}),"")', '0.00E+00' if tol and tol < 0.001 else '#,##0.00;-#,##0.00;"-"')
    celda(ws, f'G{f}', f'=IF(ISNUMBER(D{f}),IF(ABS(E{f}-D{f})<=H{f},"OK","REVISAR"),IF(E{f}=D{f},"OK","REVISAR"))', negrita=True)
    ws[f'G{f}'].alignment = Alignment(horizontal='center')
    celda(ws, f'H{f}', tol, '0.00E+00' if tol and tol < 0.001 else '0.000', 'entrada')
ultima = 6 + len(resumen)
ws.conditional_formatting.add(f'G7:G{ultima}', FormulaRule(formula=[f'G7="OK"'], fill=RELLENO_RES))
ws.conditional_formatting.add(f'G7:G{ultima}', FormulaRule(formula=[f'G7="REVISAR"'], fill=PatternFill('solid', fgColor='F8CBAD')))
ws['A5'] = 'Resultado'
ws['A5'].font = F_SECC
ws['C5'] = f'=COUNTIF(G7:G{ultima},"OK")&" de "&COUNTA(G7:G{ultima})&" valores coinciden"'
ws['C5'].font = Font(name=FUENTE, size=11, bold=True)
ws.freeze_panes = 'A7'
ws.merge_cells('A2:H2')
ws['A2'].alignment = Alignment(wrap_text=True, vertical='top')
ws.row_dimensions[2].height = 30

for h in wb.worksheets:
    h.page_setup.orientation = 'landscape'
    h.page_setup.fitToWidth, h.page_setup.fitToHeight = 1, 0
    h.sheet_properties.pageSetUpPr.fitToPage = True
wb.save(SALIDA)
print(f'{SALIDA.name}: {len(resumen)} valores verificados en {len(wb.worksheets)} hojas')
