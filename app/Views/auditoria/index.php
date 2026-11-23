<?php
/**
 * CU-57 Consultar Registro de Auditoría: criterios (paso 2, A2) y listado (paso 4, A1).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, string> $f
 * @var array{filas: list<array<string, mixed>>, total: int, pagina: int, paginas: int, periodo: array<string, string>} $b
 */
use App\Libraries\Alertas\OperacionAlerta;
use App\Services\Reportes\AuditoriaConsultaService as A;

$sel = static function (string $k, string $etiqueta, array $opc, string $todos) use ($f): string {
    $h = "<div class=\"col-6 col-md-2\"><label for=\"{$k}\" class=\"form-label small\">{$etiqueta}</label><select id=\"{$k}\" name=\"{$k}\" class=\"form-select\"><option value=\"\">{$todos}</option>";
    foreach ($opc as $v => $t) {
        $h .= '<option value="' . esc((string) $v, 'attr') . '"' . ($f[$k] === (string) $v ? ' selected' : '') . '>' . esc(is_array($t) ? $t[0] : $t) . '</option>';
    }

    return $h . '</select></div>';
};
$consulta = array_filter($f, static fn ($x) => $x !== '');
$pagina = static fn (int $n): string => url_to('auditoria.consultar') . '?' . http_build_query(['pagina' => $n] + $consulta);
$paginacion = [
    'actual' => $b['pagina'], 'paginas' => $b['paginas'], 'total' => $b['total'], 'porPagina' => $b['por_pagina'],
    'opciones' => A::OPCIONES_POR_PAGINA, 'enlace' => $pagina, 'accion' => url_to('auditoria.consultar'),
    'criterios' => array_diff_key($consulta, ['pagina' => 1, 'por_pagina' => 1]),
];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Registro de auditoría</h1>
        <p>Operaciones sensibles con su fecha, responsable, objeto y resultado. Los registros no se modifican (RN-45).</p>
    </div>
</div>
<form method="get" class="hoja p-3 mb-3 row g-2 align-items-end" novalidate>
    <div class="col-6 col-md-3 col-xl-2"><label for="desde" class="form-label small">Desde</label><input type="date" id="desde" name="desde" class="form-control<?= campo_invalido('desde') ?>" value="<?= esc($f['desde'] !== '' ? $f['desde'] : $b['periodo']['desde'], 'attr') ?>"><?= error_campo('desde') ?></div>
    <div class="col-6 col-md-3 col-xl-2"><label for="hasta" class="form-label small">Hasta</label><input type="date" id="hasta" name="hasta" class="form-control<?= campo_invalido('hasta') ?>" value="<?= esc($f['hasta'] !== '' ? $f['hasta'] : $b['periodo']['hasta'], 'attr') ?>"><?= error_campo('hasta') ?></div>
    <div class="col-6 col-md-2"><label for="responsable" class="form-label small">Responsable</label><input type="text" id="responsable" name="responsable" class="form-control" value="<?= esc($f['responsable'], 'attr') ?>" placeholder="Usuario o proceso"></div>
    <?= $sel('categoria', 'Tipo de operación', A::CATEGORIAS, 'Todos') ?>
    <?= $sel('objeto', 'Objeto afectado', A::OBJETOS, 'Todos') ?>
    <?= $sel('resultado', 'Resultado', A::RESULTADOS, 'Todos') ?>
    <?php if ($f['por_pagina'] !== ''): ?><input type="hidden" name="por_pagina" value="<?= esc($f['por_pagina'], 'attr') ?>"><?php endif ?>
    <?= acciones_filtro(limpiar: url_to('auditoria.consultar')) ?>
</form>
<?php if ($b['filas'] === []): ?>
    <div class="hoja p-4"><p class="mb-0">No hay registros que coincidan con los criterios: modifíquelos y vuelva a buscar (A1).</p></div>
<?php else: ?>
    <?= view('partials/paginacion', $paginacion) ?>
    <div class="hoja p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Fecha y hora</th><th>Responsable</th><th>Tipo de operación</th><th>Acción</th><th>Objeto</th><th>Resultado</th><th>Motivo</th></tr></thead>
                <tbody>
                <?php foreach ($b['filas'] as $a): ?>
                    <tr>
                        <td class="text-nowrap"><a href="<?= site_url('admin/auditoria/' . (int) $a['id']) . '?' . esc(http_build_query(['volver' => http_build_query(['pagina' => $b['pagina']] + $consulta)])) ?>"><?= esc(formato_fecha_hora((string) $a['fecha_hora'])) ?></a></td>
                        <td><?= esc((string) $a['responsable']) ?></td>
                        <td class="small"><?= esc(A::categoria((string) $a['accion'], $a['proceso'])) ?></td>
                        <td class="small"><code><?= esc((string) $a['accion']) ?></code></td>
                        <td class="small"><?= esc(OperacionAlerta::OBJETOS[$a['objeto_tipo']] ?? (string) $a['objeto_tipo']) ?><?= $a['objeto_id'] !== null ? ' ' . esc((string) $a['objeto_id']) : '' ?></td>
                        <td><?= $a['resultado'] === 'Exitoso' ? 'Exitoso' : '<span class="text-danger">' . esc((string) $a['resultado']) . '</span>' ?></td>
                        <td class="small"><?= esc((string) ($a['motivo'] ?? '')) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
    <?= view('partials/paginacion', $paginacion + ['pie' => true]) ?>
<?php endif ?>
<?php $this->endSection() ?>
