# -*- coding: utf-8 -*-
"""Compara la especificación (spec.py) con los scripts de migración de app/Database/Sql.

Aplica en orden los *_subir.sql (CREATE TABLE, ALTER ADD/DROP COLUMN, sp_rename y DROP de restricciones o índices) y
verifica que el esquema resultante tenga las mismas tablas, columnas, restricciones e índices que genera gen.py, que
cada clave foránea tenga el tipo de la clave primaria que referencia y que el rol de la aplicación pueda borrar en las
tablas donde el código borra filas.
Uso: python verificar.py   (desde esta carpeta, después de python gen.py)
"""
import glob, os, re, sys

AQUI = os.path.dirname(os.path.abspath(__file__))
SQL = os.path.join(AQUI, '..', '..', 'app', 'Database', 'Sql')
generado = open(os.path.join(AQUI, 'out', 'sigccr_esquema.sql'), encoding='utf-8').read()
repo = '\n'.join(open(f, encoding='utf-8').read() for f in sorted(glob.glob(os.path.join(SQL, '*_subir.sql'))))
PAT = r'CONSTRAINT (\w+)|INDEX (\w+)|CREATE TABLE dbo\.(\w+)'


def objetos(sql):
    return {''.join(t) for t in re.findall(PAT, sql)}


def tablas(sql):
    t = {}
    for m in re.finditer(r'CREATE TABLE dbo\.(\w+) \((.*?)\n\);', sql, re.S):
        t[m.group(1)] = [l.strip().split()[0] for l in m.group(2).split('\n')
                         if l.strip() and not l.strip().startswith(('CONSTRAINT', '--'))]
    return t


esperado, real = objetos(generado), objetos(repo)
eliminados = {''.join(t) for t in re.findall(r'DROP CONSTRAINT (\w+)|DROP INDEX (\w+)', repo)}
renombrados = dict(re.findall(r"sp_rename 'dbo\.(\w+)', '(\w+)', 'OBJECT'", repo))
real = {renombrados.get(o, o) for o in real} - eliminados
# Una restricción eliminada y vuelta a crear (por ejemplo, un CHECK que cambia) cuenta como presente
real |= {o for o in esperado if o in eliminados and len(re.findall(rf'(?:CONSTRAINT|INDEX) {o}\b', repo)) > 1}

cols = tablas(repo)
for m in re.finditer(r'ALTER TABLE dbo\.(\w+) ADD (?!CONSTRAINT)(\w+) [^;]*?(?:, (\w+) [A-Z][^;]*)?;', repo):
    cols[m.group(1)] += [c for c in (m.group(2), m.group(3)) if c]
for m in re.finditer(r'ALTER TABLE dbo\.(\w+) DROP COLUMN ([\w, ]+);', repo):
    for c in m.group(2).split(','):
        cols[m.group(1)].remove(c.strip())
for m in re.finditer(r"sp_rename 'dbo\.(\w+)\.(\w+)', '(\w+)', 'COLUMN'", repo):
    cols[m.group(1)][cols[m.group(1)].index(m.group(2))] = m.group(3)

errores = [f'falta en las migraciones: {o}' for o in sorted(esperado - real)]
# Cada clave foránea debe tener el mismo tipo que la clave primaria que referencia (SQL Server rechaza la FK)
sys.path.insert(0, AQUI)
import spec  # noqa: E402
tipo_pk = {t.name: next((c.typ for c in t.cols if c.pk), None) for t in spec.TABLES}
for t in spec.TABLES:
    for c in t.cols:
        if c.fk and tipo_pk.get(c.fk) and c.typ != tipo_pk[c.fk]:
            errores.append(f'{t.name}.{c.name}: es {c.typ} y {c.fk}.id es {tipo_pk[c.fk]}')
for m in re.finditer(r'ALTER TABLE dbo\.(\w+) ADD CONSTRAINT \w+ FOREIGN KEY \((\w+)\) REFERENCES dbo\.(\w+) \(id\)', repo):
    tabla, col, ref = m.groups()
    tipo = re.search(rf'CREATE TABLE dbo\.{tabla} \(.*?\n    {col} (\w+)', repo, re.S) or re.search(rf'ALTER TABLE dbo\.{tabla} ADD {col} (\w+)', repo)
    if tipo and tipo_pk.get(ref) and tipo.group(1) != tipo_pk[ref]:
        errores.append(f'migraciones: {tabla}.{col} es {tipo.group(1)} y {ref}.id es {tipo_pk[ref]}')
# Cada tabla que la aplicación borra necesita GRANT DELETE para rol_sigccr_app (el rol solo tiene SELECT, INSERT y UPDATE)
codigo = '\n'.join(open(f, encoding='utf-8').read() for f in glob.glob(os.path.join(AQUI, '..', '..', 'app', '**', '*.php'), recursive=True)
                   if os.sep + 'Database' + os.sep not in f)
borradas = set(re.findall(r"table\('(\w+)'\)(?:->\w+\([^;]*?\))*?->delete\(", codigo)) | set(re.findall(r'DELETE FROM dbo\.(\w+)', codigo))
con_permiso = set(re.findall(r'GRANT DELETE ON dbo\.(\w+) TO rol_sigccr_app', repo))
errores += [f'la aplicación borra filas de {t} sin GRANT DELETE para rol_sigccr_app' for t in sorted(borradas - con_permiso)]
errores += [f'no está en la especificación: {o}' for o in sorted(real - esperado)]
for t, c in tablas(generado).items():
    if set(c) != set(cols.get(t, [])):
        errores.append(f'{t}: columnas distintas {sorted(set(c) ^ set(cols.get(t, [])))}')
print('\n'.join(errores) if errores else f'Esquema alineado: {len(tablas(generado))} tablas.')
sys.exit(1 if errores else 0)
