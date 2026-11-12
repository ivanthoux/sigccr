<?php
/**
 * CU-55 Consultar Tablero Gerencial: período (paso 2, A2), indicadores agrupados (pasos 3 y 4, A1) y acceso a cada
 * reporte (pasos 5 y 6).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, string> $f
 * @var array{periodo: array<string, string>, grupos: list<array{0: string, 1: string, 2: list<array{0: string, 1: mixed, 2: string}>}>, aviso: ?string} $t
 */
use App\Libraries\Reportes\Periodo;
use App\Services\Reportes\ReporteService;

$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Tablero gerencial</h1>
        <p>Indicadores del período <strong><?= esc($t['periodo']['etiqueta']) ?></strong>. Los saldos de cartera y morosidad son a hoy; la proyección de fondos, para el período configurado en los parámetros de campañas.</p>
    </div>
    <?php if (tiene_permiso('reportes.cartera')): ?><a href="<?= url_to('reportes.index') ?>" class="btn btn-outline-primary">Reportes</a><?php endif ?>
</div>
<form method="get" class="hoja p-3 mb-3 row g-2 align-items-end d-print-none" novalidate>
    <div class="col-6 col-md-2"><label for="tipo" class="form-label small mb-1">Período</label>
        <select id="tipo" name="tipo" class="form-select form-select-sm"><?php foreach (Periodo::TIPOS as $k => $txt): ?><option value="<?= $k ?>"<?= $f['tipo'] === $k ? ' selected' : '' ?>><?= esc($txt) ?></option><?php endforeach ?></select></div>
    <div class="col-6 col-md-2"><label for="fecha" class="form-label small mb-1">Fecha de referencia</label><input type="date" id="fecha" name="fecha" class="form-control form-control-sm<?= campo_invalido('fecha') ?>" value="<?= esc($f['fecha'], 'attr') ?>"><?= error_campo('fecha') ?></div>
    <div class="col-6 col-md-2"><label for="desde" class="form-label small mb-1">Rango: desde</label><input type="date" id="desde" name="desde" class="form-control form-control-sm<?= campo_invalido('desde') ?>" value="<?= esc($f['desde'], 'attr') ?>"><?= error_campo('desde') ?></div>
    <div class="col-6 col-md-2"><label for="hasta" class="form-label small mb-1">hasta</label><input type="date" id="hasta" name="hasta" class="form-control form-control-sm<?= campo_invalido('hasta') ?>" value="<?= esc($f['hasta'], 'attr') ?>"><?= error_campo('hasta') ?></div>
    <div class="col-12 col-md-4 d-flex gap-2 justify-content-end"><button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir</button><button type="submit" class="btn btn-sm btn-primary">Actualizar</button></div>
</form>
<?php if ($t['aviso'] !== null): ?><div class="alert alert-info"><?= esc($t['aviso']) ?></div><?php endif ?>
<div class="row g-3">
    <?php foreach ($t['grupos'] as [$grupo, $reporte, $indicadores]): ?>
        <?php $permiso = ReporteService::REPORTES[$reporte][2]; ?>
        <div class="col-md-6 col-xl-4">
            <section class="hoja p-3 h-100 d-flex flex-column">
                <h2 class="h6 mb-2"><?= esc($grupo) ?></h2>
                <dl class="row small mb-2 flex-grow-1">
                    <?php foreach ($indicadores as [$etiqueta, $valor, $tipo]): ?>
                        <dt class="col-7 fw-normal text-body-secondary"><?= esc($etiqueta) ?></dt>
                        <dd class="col-5 text-end fw-semibold mb-1"><?= esc(valor_reporte($valor, $tipo, true)) ?></dd>
                    <?php endforeach ?>
                </dl>
                <?php if (tiene_permiso($permiso)): ?>
                    <a class="small d-print-none" href="<?= url_to($permiso) . '?' . esc(http_build_query($reporte === 'proyeccion' ? ['generar' => 1] : array_filter($f, static fn ($x) => $x !== '') + ['generar' => 1]), 'attr') ?>">Ver el reporte <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                <?php endif ?>
            </section>
        </div>
    <?php endforeach ?>
</div>
<?php $this->endSection() ?>
