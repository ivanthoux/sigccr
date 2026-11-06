# -*- coding: utf-8 -*-
import re, sys, json, os, subprocess, collections
sys.path.insert(0, os.path.dirname(__file__))
from spec import TABLES, ENUMS, SS, SEQUENCES

OUT = os.path.join(os.path.dirname(__file__), 'out')
os.makedirs(OUT, exist_ok=True)
TB = {t.name: t for t in TABLES}
errors = []


def cols(t):
    return {c.name: c for c in t.cols}


# ------------------------------------------------------------------ validación
for t in TABLES:
    cs = cols(t)
    pk = t.pkcols or [c.name for c in t.cols if c.pk]
    if not pk:
        errors.append(f'{t.name}: sin PK')
    for c in t.cols:
        if c.fk and c.fk not in TB:
            errors.append(f'{t.name}.{c.name}: FK a tabla inexistente {c.fk}')
    for n, cl, w in t.uq:
        for x in cl:
            if x not in cs: errors.append(f'{t.name} {n}: columna {x}')
    for n, cl, inc, w in t.idx:
        for x in cl + (inc or []):
            if x not in cs: errors.append(f'{t.name} {n}: columna {x}')
    for n, cl, rt, rc in t.fkx:
        for x in cl:
            if x not in cs: errors.append(f'{t.name} {n}: columna {x}')
        for x in rc:
            if x not in cols(TB[rt]): errors.append(f'{t.name} {n}: col ref {rt}.{x}')
        # la referencia debe tener UQ o PK exacta
        r = TB[rt]
        keys = [r.pkcols or [c.name for c in r.cols if c.pk]] + [u[1] for u in r.uq if u[2] is None]
        if rc not in keys: errors.append(f'{t.name} {n}: {rt}{rc} no es clave')
    kw = set('AND OR NOT IN IS NULL BETWEEN LIKE ISJSON CAST AS DATE DATEADD DAY'.split())
    for n, expr in t.ck:
        e2 = re.sub(r"'[^']*'", '', expr)
        for tok in re.findall(r'[A-Za-z_][A-Za-z_0-9]*', e2):
            if tok.upper() in kw: continue
            if tok not in cs: errors.append(f'{t.name} {n}: token {tok}?')
    for n, cl, w in t.uq:
        if w:
            e2 = re.sub(r"'[^']*'", '', w)
            for tok in re.findall(r'[A-Za-z_][A-Za-z_0-9]*', e2):
                if tok.upper() in kw: continue
                if tok not in cs: errors.append(f'{t.name} {n} where: {tok}?')
names = collections.Counter()
for t in TABLES:
    for n in [u[0] for u in t.uq] + [k[0] for k in t.ck] + [i[0] for i in t.idx] + [f[0] for f in t.fkx]:
        names[n] += 1
for n, k in names.items():
    if k > 1: errors.append(f'nombre duplicado {n}')
for t in TABLES:
    for c in t.cols:
        if c.default and 'NEXT VALUE FOR' in c.default:
            sq = c.default.split()[-1]
            if sq not in SEQUENCES: errors.append(f'secuencia {sq}')

# ------------------------------------------------------------------ dependencias entre subsistemas
deps = collections.defaultdict(set)
for t in TABLES:
    refs = [c.fk for c in t.cols if c.fk] + [f[2] for f in t.fkx]
    for r in refs:
        if TB[r].ss != t.ss:
            deps[t.ss].add(TB[r].ss)

# ------------------------------------------------------------------ DDL
def is_computed(c):
    return c.typ.startswith('AS ')


def coldef(t, c):
    if is_computed(c):
        return f'    {c.name} {c.typ}'
    s = f'    {c.name} {c.typ}'
    if c.ident: s += ' IDENTITY(1,1)'
    s += ' NULL' if c.null else ' NOT NULL'
    if c.default:
        s += f' CONSTRAINT DF_{t.name}_{c.name} DEFAULT ({c.default})'
    return s


def enum_check(t, c):
    vals = ', '.join(f"'{v}'" for v in ENUMS[c.enum])
    return f'CK_{t.name}_{c.name}', f'{c.name} IN ({vals})'


ddl = []
w = ddl.append
w('/* ==========================================================================')
w('   SIG-CCR - Sistema Integral de Gestión de Créditos, Cobranzas y Refinanciación')
w('   Script de creación de la base de datos (modelo físico, sección 4.3)')
w('   Motor: Microsoft SQL Server 2022 (Developer en desarrollo, Express en producción)')
w('   Generado a partir de la especificación única del modelo de datos.')
w('   Convenciones: tablas y columnas en snake_case, sin tildes; tablas en plural;')
w('   clave primaria "id"; claves foráneas "<entidad>_id"; restricciones con nombre:')
w('   PK_, FK_, UQ_, CK_, DF_, IX_.')
w('   ========================================================================== */')
w('')
w('/* 1. Base de datos (ejecutar una sola vez, con un inicio de sesión administrativo)')
w("CREATE DATABASE sigccr COLLATE Modern_Spanish_CI_AI;")
w('GO')
w('*/')
w('-- USE sigccr;')
w('-- GO')
w('-- Lecturas por versiones de fila: los reportes no bloquean a las operaciones de caja (RNF-03).')
w('ALTER DATABASE CURRENT SET READ_COMMITTED_SNAPSHOT ON WITH ROLLBACK IMMEDIATE;')
w('GO')
w('SET ANSI_NULLS ON;')
w('SET QUOTED_IDENTIFIER ON;')
w('GO')
w('')
w('/* 2. Secuencias para los números visibles de las operaciones */')
for sq, st in SEQUENCES.items():
    w(f'CREATE SEQUENCE dbo.{sq} AS {st} START WITH 1 INCREMENT BY 1 NO CYCLE CACHE 20;')
w('GO')
w('')
w('/* 3. Tablas (las claves foráneas se agregan en el punto 4 para independizarse del orden) */')
cur = None
for t in TABLES:
    if t.ss != cur:
        cur = t.ss
        w('')
        w(f'/* ---------------------------------------------------------------- {t.ss} {SS[t.ss]} */')
    w('')
    w(f'-- {t.name}: {t.desc}')
    w(f'CREATE TABLE dbo.{t.name} (')
    lines = [coldef(t, c) for c in t.cols]
    pk = t.pkcols or [c.name for c in t.cols if c.pk]
    lines.append(f'    CONSTRAINT PK_{t.name} PRIMARY KEY ({", ".join(pk)})')
    for c in t.cols:
        if c.uq:
            lines.append(f'    CONSTRAINT UQ_{t.name}_{c.name} UNIQUE ({c.name})' if not c.null else None)
    for n, cl, wh in t.uq:
        if not wh:
            lines.append(f'    CONSTRAINT {n} UNIQUE ({", ".join(cl)})')
    for c in t.cols:
        if c.enum:
            n, e = enum_check(t, c)
            if c.null: e = f'{c.name} IS NULL OR {e}'
            lines.append(f'    CONSTRAINT {n} CHECK ({e})')
    for n, e in t.ck:
        lines.append(f'    CONSTRAINT {n} CHECK ({e})')
    lines = [l for l in lines if l]
    w(',\n'.join(lines))
    w(');')
w('GO')
w('')
w('/* 4. Claves foráneas (sin acciones en cascada: no se eliminan operaciones, RN-44) */')
fk_index_needed = []
for t in TABLES:
    for c in t.cols:
        if c.fk:
            rpk = (TB[c.fk].pkcols or [x.name for x in TB[c.fk].cols if x.pk])
            w(f'ALTER TABLE dbo.{t.name} ADD CONSTRAINT FK_{t.name}_{c.name} FOREIGN KEY ({c.name}) REFERENCES dbo.{c.fk} ({rpk[0]});')
            fk_index_needed.append((t, c.name))
    for n, cl, rt, rc in t.fkx:
        w(f'ALTER TABLE dbo.{t.name} ADD CONSTRAINT {n} FOREIGN KEY ({", ".join(cl)}) REFERENCES dbo.{rt} ({", ".join(rc)});')
w('GO')
w('')
w('/* 5. Índices únicos filtrados (reglas de unicidad condicionada) */')
for t in TABLES:
    for c in t.cols:
        if c.uq and c.null:
            w(f'CREATE UNIQUE INDEX UQ_{t.name}_{c.name} ON dbo.{t.name} ({c.name}) WHERE {c.name} IS NOT NULL;')
    for n, cl, wh in t.uq:
        if wh:
            w(f'CREATE UNIQUE INDEX {n} ON dbo.{t.name} ({", ".join(cl)}) WHERE {wh};')
w('GO')
w('')
w('/* 6. Índices de búsqueda y de soporte de claves foráneas (RNF-03) */')


def leading(t):
    s = set()
    pk = t.pkcols or [c.name for c in t.cols if c.pk]
    s.add(pk[0])
    for c in t.cols:
        if c.uq: s.add(c.name)
    for n, cl, wh in t.uq: s.add(cl[0])
    for n, cl, inc, wh in t.idx:
        if not wh: s.add(cl[0])
        # un índice filtrado «columna IS NOT NULL» declarado con el nombre del automático lo reemplaza
        elif wh == f'{cl[0]} IS NOT NULL' and n == f'IX_{t.name}_{cl[0]}': s.add(cl[0])
    return s


for t in TABLES:
    for n, cl, inc, wh in t.idx:
        s = f'CREATE INDEX {n} ON dbo.{t.name} ({", ".join(cl)})'
        if inc: s += f' INCLUDE ({", ".join(inc)})'
        if wh: s += f' WHERE {wh}'
        w(s + ';')
auto_ix = []
for t, cn in fk_index_needed:
    if cn not in leading(t):
        n = f'IX_{t.name}_{cn}'
        auto_ix.append(n)
        w(f'CREATE INDEX {n} ON dbo.{t.name} ({cn});')
w('GO')
w('')
w('/* 7. Inmutabilidad del registro de auditoría (RN-45) */')
w('CREATE TRIGGER dbo.TR_registros_auditoria_inmutable ON dbo.registros_auditoria')
w('INSTEAD OF UPDATE, DELETE')
w('AS')
w('BEGIN')
w('    SET NOCOUNT ON;')
w("    THROW 50001, 'El registro de auditoría no admite modificaciones ni eliminaciones (RN-45).', 1;")
w('END;')
w('GO')
w('')
w('/* 8. Permisos del usuario de la aplicación: sin DELETE salvo en tablas de configuración */')
w('-- CREATE LOGIN sigccr_app WITH PASSWORD = \'<desde variable de entorno>\';')
w('-- CREATE USER sigccr_app FOR LOGIN sigccr_app;')
w('CREATE ROLE rol_sigccr_app;')
w('GRANT SELECT, INSERT, UPDATE ON SCHEMA::dbo TO rol_sigccr_app;')
w('GRANT DELETE ON dbo.roles_permisos TO rol_sigccr_app;')
w('GRANT DELETE ON dbo.dias_no_habiles TO rol_sigccr_app;')
w('DENY UPDATE ON dbo.registros_auditoria TO rol_sigccr_app;')
w('-- ALTER ROLE rol_sigccr_app ADD MEMBER sigccr_app;')
w('-- Las copias y restauraciones (CU-61 a CU-63) usan un inicio de sesión de mantenimiento separado,')
w('-- con BACKUP DATABASE y dbcreator, configurado solo en el contenedor de procesos.')
w('GO')
w('')
w('/* 9. Datos semilla mínimos (el resto de los parámetros se carga con los seeders de CodeIgniter) */')
w("INSERT INTO dbo.roles (codigo, nombre, descripcion) VALUES")
w("    ('Administrador', N'Administrador', N'Administra usuarios, roles, cajas y copias de seguridad.'),")
w("    ('Gerente', N'Gerente', N'Resuelve solicitudes y propuestas, anula cobros y configura parámetros.'),")
w("    ('AnalistaCredito', N'Analista de Crédito', N'Registra clientes, solicitudes, evaluaciones de contingencia y propuestas.'),")
w("    ('Cajero', N'Cajero', N'Opera la caja, registra cobros y desembolsos en efectivo.');")
docs = [
    ('RECIBO_SUELDO', 'Recibo de sueldo (últimos tres)', 'PersonaHumana', 1),
    ('RECIBO_HABERES', 'Recibo de haberes previsionales', 'PersonaHumana', 1),
    ('CONST_MONOTRIBUTO', 'Constancia de inscripción en el monotributo', 'PersonaHumana', 1),
    ('PAGOS_MONOTRIBUTO', 'Comprobantes de pago del monotributo (seis meses)', 'PersonaHumana', 1),
    ('CONST_INSCRIPCION', 'Constancia de inscripción (responsable inscripto)', 'PersonaHumana', 1),
    ('DDJJ_GANANCIAS', 'Última declaración jurada del impuesto a las ganancias', 'PersonaHumana', 1),
    ('ESTATUTO', 'Estatuto o contrato social', 'PersonaJuridica', 1),
    ('CONST_CUIT', 'Constancia de CUIT', 'PersonaJuridica', 1),
    ('ESTADOS_CONTABLES', 'Últimos estados contables', 'PersonaJuridica', 1),
    ('PODER_REPRESENTACION', 'Instrumento que acredita la representación', 'PersonaJuridica', 1),
    ('DOC_IDENTIDAD', 'Documento de identidad', 'Ambos', 1),
]
w("INSERT INTO dbo.tipos_documentacion (codigo, nombre, aplica_a, es_clave) VALUES")
w(',\n'.join(f"    ('{a}', N'{b}', '{c}', {d})" for a, b, c, d in docs) + ';')
w('GO')
open(os.path.join(OUT, 'sigccr_esquema.sql'), 'w', encoding='utf-8').write('\n'.join(ddl) + '\n')

# ------------------------------------------------------------------ diccionario (json para el docx)
dic = []
for t in TABLES:
    cs = []
    pk = t.pkcols or [c.name for c in t.cols if c.pk]
    uqcols = {c.name for c in t.cols if c.uq}
    for c in t.cols:
        keys = []
        if c.name in pk: keys.append('PK')
        if c.fk: keys.append('FK')
        for n, cl, rt, rc in t.fkx:
            if c.name in cl and 'FK' not in keys: keys.append('FK')
        if c.name in uqcols: keys.append('UQ')
        typ = c.typ
        if is_computed(c): typ = 'calculada'
        if c.ident: typ += ' IDENTITY'
        d = c.d
        if c.fk:
            d = (d + ' ' if d else '') + f'→ {c.fk}.'
        if c.enum and not c.d.startswith('Valor de'):
            d += f' Valores de {c.enum}.'
        if c.default and 'NEXT VALUE' not in c.default and c.default not in ('SYSDATETIMEOFFSET()',):
            if c.enum or c.typ == 'BIT' or c.name in ('version_fila',):
                pass
        cs.append([c.name, typ, 'Sí' if c.null else 'No', ', '.join(keys), d.strip()])
    extra = []
    for n, cl, wh in t.uq:
        extra.append(f'{n}: única ({", ".join(cl)})' + (f' cuando {wh}' if wh else ''))
    for n, cl, rt, rc in t.fkx:
        extra.append(f'{n}: ({", ".join(cl)}) → {rt} ({", ".join(rc)})')
    for n, e in t.ck:
        extra.append(f'{n}: {e}')
    dic.append(dict(name=t.name, ss=t.ss, clase=t.clase, desc=t.desc, cols=cs, extra=extra))
json.dump(dict(tables=dic, deps={k: sorted(v) for k, v in deps.items()}), open(os.path.join(OUT, 'dic.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)

# ------------------------------------------------------------------ estadísticas
nfk = sum(1 for t in TABLES for c in t.cols if c.fk) + sum(len(t.fkx) for t in TABLES)
nck = sum(len(t.ck) for t in TABLES) + sum(1 for t in TABLES for c in t.cols if c.enum)
print('tablas', len(TABLES), 'columnas', sum(len(t.cols) for t in TABLES), 'FK', nfk, 'CHECK', nck,
      'UQ', sum(len(t.uq) for t in TABLES) + sum(1 for t in TABLES for c in t.cols if c.uq),
      'IX', sum(len(t.idx) for t in TABLES) + len(auto_ix))
print('por subsistema', collections.Counter(t.ss for t in TABLES))
print('dependencias', {k: sorted(v) for k, v in sorted(deps.items())})
allnames = re.findall(r'CONSTRAINT (\w+)|INDEX (\w+)', open(os.path.join(OUT, 'sigccr_esquema.sql'), encoding='utf-8').read())
cn = collections.Counter(a or b for a, b in allnames)
errors += [f'nombre repetido {k}' for k, v in cn.items() if v > 1]
errors += [f'nombre largo {k}' for k in cn if len(k) > 128]
print('ERRORES:' if errors else 'sin errores', *errors, sep='\n')
