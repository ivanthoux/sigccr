<?php
/**
 * CU-50 Consultar Campaña Crediticia, pasos 2 a 4 (A1, A5).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, string> $f
 * @var list<array<string, mixed>> $campanas
 * @var list<array{id: int|string, nombre: string}> $lineas
 */
use App\Libraries\Campanas\ReglasCampanas;
use App\Libraries\Validacion\ValidadorParametrosCampanas as C;

$filtrado = array_filter($f, static fn ($x) => $x !== '') !== [];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Campañas crediticias</h1>
        <p>Campañas activadas automáticamente por la proyección de fondos, con sus ofertas y resultados.</p>
    </div>
</div>
<form method="get" class="hoja p-3 mb-3 row g-2 align-items-end">
    <div class="col-6 col-md-2"><label for="numero" class="form-label small mb-1">N.º</label><input type="text" inputmode="numeric" id="numero" name="numero" class="form-control form-control-sm" value="<?= esc($f['numero'], 'attr') ?>"></div>
    <div class="col-6 col-md-3"><label for="linea" class="form-label small mb-1">Línea</label>
        <select id="linea" name="linea" class="form-select form-select-sm"><option value="">Todas</option><?php foreach ($lineas as $l): ?><option value="<?= (int) $l['id'] ?>"<?= $f['linea'] === (string) $l['id'] ? ' selected' : '' ?>><?= esc($l['nombre']) ?></option><?php endforeach ?></select></div>
    <div class="col-6 col-md-2"><label for="estado" class="form-label small mb-1">Estado</label>
        <select id="estado" name="estado" class="form-select form-select-sm"><option value="">Todos</option><?php foreach (['Activa' => 'Activa', 'Finalizada' => 'Finalizada'] as $k => $t): ?><option value="<?= $k ?>"<?= $f['estado'] === $k ? ' selected' : '' ?>><?= $t ?></option><?php endforeach ?></select></div>
    <div class="col-6 col-md-2"><label for="desde" class="form-label small mb-1">Activada desde</label><input type="date" id="desde" name="desde" class="form-control form-control-sm" value="<?= esc($f['desde'], 'attr') ?>"></div>
    <div class="col-6 col-md-2"><label for="hasta" class="form-label small mb-1">hasta</label><input type="date" id="hasta" name="hasta" class="form-control form-control-sm" value="<?= esc($f['hasta'], 'attr') ?>"></div>
    <div class="col-6 col-md-1"><label for="canal" class="form-label small mb-1">Canal</label>
        <select id="canal" name="canal" class="form-select form-select-sm"><option value="">Todos</option><?php foreach (C::CANALES as $k => $t): ?><option value="<?= $k ?>"<?= $f['canal'] === $k ? ' selected' : '' ?>><?= esc($t) ?></option><?php endforeach ?></select></div>
    <div class="col-12 d-flex gap-2 justify-content-end"><a href="<?= url_to('campanas.consultar') ?>" class="btn btn-sm btn-outline-secondary">Limpiar</a><button type="submit" class="btn btn-sm btn-primary">Buscar</button></div>
</form>
<?php if ($campanas === []): ?>
    <div class="hoja p-4"><p class="mb-0"><?= $filtrado ? 'No hay campañas que coincidan con los criterios: modifíquelos y vuelva a buscar (A1).' : 'Todavía no se activó ninguna campaña. Se activan solas cuando la proyección de fondos supera el umbral configurado.' ?></p></div>
<?php else: ?>
    <div class="hoja p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>N.º</th><th>Activación</th><th>Línea</th><th>Condiciones</th><th>Vigencia</th><th class="text-end">Capital asignado</th><th class="text-end">Comprometido</th><th class="text-end">Ofertas</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($campanas as $c): ?>
                    <tr>
                        <td><a href="<?= site_url('admin/campanas/' . (int) $c['id']) ?>"><?= (int) $c['numero'] ?></a></td>
                        <td><?= esc(formato_fecha_hora((string) $c['activacion'])) ?></td>
                        <td><?= esc($c['linea']) ?></td>
                        <td class="small">TNA <?= esc(formato_porcentaje((string) $c['tna'])) ?> · <?= (int) $c['cuotas_minimas'] ?> a <?= (int) $c['cuotas_maximas'] ?> cuotas</td>
                        <td class="small">hasta <?= esc(formato_fecha_hora((string) ($c['fecha_cierre'] ?? $c['vence']))) ?></td>
                        <td class="importe"><?= esc(formato_importe((string) $c['capital_asignado'], false)) ?></td>
                        <td class="importe"><?= esc(formato_importe((string) $c['capital_comprometido'], false)) ?></td>
                        <td class="text-end"><?= (int) $c['ofertas'] ?></td>
                        <td><?= $c['estado'] === 'Activa' ? '<span class="badge text-bg-success">Activa</span>' : 'Finalizada <span class="small text-body-secondary">(' . esc(mb_strtolower(ReglasCampanas::CAUSAS[$c['causa_cierre']] ?? (string) $c['causa_cierre'])) . ')</span>' ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
