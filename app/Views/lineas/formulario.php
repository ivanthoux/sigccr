<?php
/**
 * CU-16 Registrar Línea de Crédito y CU-17 Modificar Línea de Crédito (nueva versión de condiciones).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed>|null $l
 * @var list<array{codigo: string, nombre: string, aplica_a: string}> $tipos
 */
use App\Libraries\Clientes\RequisitosDocumentales;
use App\Libraries\Lineas\ValidadorLinea;

$alta = $l === null;
$vig = $alta ? [] : $l['vigente'];
$enviado = old('nombre') !== null;
$importe = static fn (?string $v): string => $v === null || $v === '' ? '' : str_replace('$ ', '', formato_importe($v));
$inicial = $alta ? [] : [
    'codigo' => $l['codigo'], 'nombre' => $l['nombre'], 'descripcion' => (string) $l['descripcion'],
    'tna' => ValidadorLinea::aPorcentaje((string) $vig['tna']), 'gasto_otorgamiento_pct' => ValidadorLinea::aPorcentaje((string) $vig['gasto_otorgamiento_pct']),
    'gasto_administrativo_cuota' => $importe((string) $vig['gasto_administrativo_cuota']),
    'monto_minimo' => $importe((string) $vig['monto_minimo']), 'monto_maximo' => $importe((string) $vig['monto_maximo']),
    'cuotas_minimas' => (string) $vig['cuotas_minimas'], 'cuotas_maximas' => (string) $vig['cuotas_maximas'],
    'edad_minima' => (string) $vig['edad_minima'], 'edad_maxima' => (string) $vig['edad_maxima'], 'antiguedad_minima_meses' => (string) $vig['antiguedad_minima_meses'],
    'vigencia_inicio' => substr((string) $vig['vigencia_inicio'], 0, 10), 'vigencia_fin' => substr((string) $vig['vigencia_fin'], 0, 10),
];
$v = static fn (string $k): string => (string) old($k, $inicial[$k] ?? '');
$humana = $enviado ? old('admite_persona_humana') !== null : ($alta ? true : (bool) $vig['admite_persona_humana']);
$juridica = $enviado ? old('admite_persona_juridica') !== null : (!$alta && (bool) $vig['admite_persona_juridica']);
$categoriasMarcadas = $enviado ? (array) old('categorias') : ($alta ? [] : $vig['categorias']);
$requisitosMarcados = [];
foreach (RequisitosDocumentales::CATEGORIAS as $cats) {
    foreach (array_keys($cats) as $cat) {
        $requisitosMarcados[$cat] = $enviado ? (array) (old('requisitos')[$cat] ?? [])
            : ($alta || !in_array($cat, $vig['categorias'], true) ? RequisitosDocumentales::exigidos($cat) : array_column($vig['requisitos'][$cat] ?? [], 'codigo'));
    }
}
$campo = static fn (string $nombre, string $etiqueta, string $html, string $ayuda = '', string $col = 'col-md-6'): string =>
    "<div class=\"{$col}\"><label for=\"{$nombre}\" class=\"form-label\">{$etiqueta}</label>{$html}" . error_campo($nombre) . ($ayuda !== '' ? "<div class=\"form-text\">{$ayuda}</div>" : '') . '</div>';
$entrada = static fn (string $nombre, string $extra = '', string $tipo = 'text') => "<input type=\"{$tipo}\" class=\"form-control" . campo_invalido($nombre) . "\" id=\"{$nombre}\" name=\"{$nombre}\" value=\"" . esc($v($nombre), 'attr') . "\" {$extra}>";
$conSufijo = static fn (string $nombre, string $sufijo, string $extra = '') => '<div class="input-group' . (campo_invalido($nombre) !== '' ? ' has-validation' : '') . '">' . $entrada($nombre, $extra) . "<span class=\"input-group-text\">{$sufijo}</span></div>";
$conPrefijo = static fn (string $nombre, string $extra = '') => '<div class="input-group"><span class="input-group-text">$</span>' . $entrada($nombre, $extra) . '</div>';
$this->extend('layouts/panel');
$this->section('contenido');
?>
<?php if (!$alta): ?><nav aria-label="Ruta" class="mb-2"><a href="<?= site_url('admin/lineas/' . $l['id']) ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= esc($l['nombre']) ?></a></nav><?php endif ?>
<div class="encabezado">
    <div>
        <h1><?= $alta ? 'Registrar línea de crédito' : 'Modificar línea de crédito' ?></h1>
        <p><?= $alta ? 'La línea queda activa con su versión 1 de condiciones.' : 'Los cambios generan la versión ' . (count($l['versiones']) + 1) . ': las operaciones en curso conservan la versión con la que se generaron (RN-15).' ?></p>
    </div>
</div>

<form method="post" action="<?= $alta ? site_url('admin/lineas') : site_url('admin/lineas/' . $l['id'] . '/editar') ?>" class="formulario-linea" style="max-width: 60rem" novalidate>
    <?= csrf_field() ?>
    <?php if (!$alta): ?><input type="hidden" name="version_fila" value="<?= (int) $l['version_fila'] ?>"><?php endif ?>

    <fieldset class="hoja p-4 mb-3">
        <legend class="h5">Identificación</legend>
        <div class="row g-3">
            <?= $alta
                ? $campo('codigo', 'Identificación', $entrada('codigo', 'maxlength="20" autocapitalize="characters" required'), 'Código corto y único, por ejemplo MOTOS.', 'col-md-4')
                : '<div class="col-md-4"><span class="form-label d-block">Identificación</span><code class="fs-6">' . esc($l['codigo']) . '</code></div>' ?>
            <?= $campo('nombre', 'Nombre', $entrada('nombre', 'maxlength="100" required'), '', 'col-md-8') ?>
            <div class="col-12">
                <label for="descripcion" class="form-label">Descripción <span class="text-body-secondary fw-normal">(opcional)</span></label>
                <textarea class="form-control<?= campo_invalido('descripcion') ?>" id="descripcion" name="descripcion" rows="2" maxlength="500"><?= esc($v('descripcion')) ?></textarea>
                <?= error_campo('descripcion') ?>
            </div>
        </div>
    </fieldset>

    <fieldset class="hoja p-4 mb-3">
        <legend class="h5">Importes y plazos prestables</legend>
        <p class="small text-body-secondary">El importe mínimo y máximo acotan las simulaciones, las solicitudes, las alternativas automáticas y las ofertas de esta línea (RN-76). Por ejemplo, una línea para motos podría prestar entre $ 300.000 y $ 1.500.000.</p>
        <div class="row g-3">
            <?= $campo('monto_minimo', 'Importe mínimo prestable', $conPrefijo('monto_minimo', 'inputmode="decimal" required placeholder="300.000,00"'), '', 'col-md-6') ?>
            <?= $campo('monto_maximo', 'Importe máximo prestable', $conPrefijo('monto_maximo', 'inputmode="decimal" required placeholder="1.500.000,00"'), '', 'col-md-6') ?>
            <?= $campo('cuotas_minimas', 'Cantidad mínima de cuotas', $entrada('cuotas_minimas', 'inputmode="numeric" min="1" max="120" required', 'number'), '', 'col-6 col-md-3') ?>
            <?= $campo('cuotas_maximas', 'Cantidad máxima de cuotas', $entrada('cuotas_maximas', 'inputmode="numeric" min="1" max="120" required', 'number'), '', 'col-6 col-md-3') ?>
        </div>
    </fieldset>

    <fieldset class="hoja p-4 mb-3">
        <legend class="h5">Tasa y gastos</legend>
        <div class="row g-3">
            <?= $campo('tna', 'TNA', $conSufijo('tna', '%', 'inputmode="decimal" required'), 'Tasa nominal anual. La TEA y el CFTEA se calculan en cada simulación (RN-63).', 'col-md-4') ?>
            <?= $campo('gasto_otorgamiento_pct', 'Gasto de otorgamiento', $conSufijo('gasto_otorgamiento_pct', '% del capital', 'inputmode="decimal" required'), 'Se descuenta del importe a desembolsar (RN-68).', 'col-md-4') ?>
            <?= $campo('gasto_administrativo_cuota', 'Gasto administrativo por cuota', $conPrefijo('gasto_administrativo_cuota', 'inputmode="decimal" required'), 'Integra cada cuota; lleva IVA.', 'col-md-4') ?>
        </div>
    </fieldset>

    <fieldset class="hoja p-4 mb-3">
        <legend class="h5">Elegibilidad</legend>
        <div class="mb-3<?= campo_invalido('tipos_cliente') !== '' ? ' is-invalid' : '' ?>">
            <span class="form-label d-block">Tipos de cliente admitidos</span>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" id="admite_persona_humana" name="admite_persona_humana" value="1"<?= $humana ? ' checked' : '' ?>>
                <label class="form-check-label" for="admite_persona_humana">Personas humanas</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" id="admite_persona_juridica" name="admite_persona_juridica" value="1"<?= $juridica ? ' checked' : '' ?>>
                <label class="form-check-label" for="admite_persona_juridica">Personas jurídicas (pymes)</label>
            </div>
            <?= error_campo('tipos_cliente') ?>
        </div>
        <div class="row g-3">
            <div class="col-md-6" data-tipo="PersonaHumana">
                <div class="row g-2">
                    <?= $campo('edad_minima', 'Edad mínima <span class="text-body-secondary fw-normal">(opcional)</span>', $conSufijo('edad_minima', 'años', 'inputmode="numeric"'), '', 'col-6') ?>
                    <?= $campo('edad_maxima', 'Edad máxima <span class="text-body-secondary fw-normal">(opcional)</span>', $conSufijo('edad_maxima', 'años', 'inputmode="numeric"'), '', 'col-6') ?>
                </div>
            </div>
            <div class="col-md-6" data-tipo="PersonaJuridica">
                <?= $campo('antiguedad_minima_meses', 'Antigüedad mínima de la empresa <span class="text-body-secondary fw-normal">(opcional)</span>', $conSufijo('antiguedad_minima_meses', 'meses', 'inputmode="numeric"'), '', 'col-12') ?>
            </div>
        </div>
    </fieldset>

    <fieldset class="hoja p-4 mb-3">
        <legend class="h5">Categorías de ingresos y documentación exigida</legend>
        <p class="small text-body-secondary">Marque las categorías admitidas. Para cada una se propone la documentación base de RN-11; puede agregar documentación adicional.</p>
        <?= error_campo('categorias') ?><?= error_campo('requisitos') ?>
        <div class="row g-3 mt-0">
            <?php foreach (RequisitosDocumentales::CATEGORIAS as $tipoCliente => $cats): ?>
                <?php foreach ($cats as $cat => $nombreCat): ?>
                    <div class="col-md-6" data-tipo="<?= $tipoCliente ?>">
                        <div class="border rounded p-3 h-100">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="cat-<?= $cat ?>" name="categorias[]" value="<?= $cat ?>" data-categoria<?= in_array($cat, $categoriasMarcadas, true) ? ' checked' : '' ?>>
                                <label class="form-check-label fw-medium" for="cat-<?= $cat ?>"><?= esc($nombreCat) ?></label>
                            </div>
                            <div class="ps-4 small" data-requisitos="<?= $cat ?>">
                                <?php foreach ($tipos as $t): ?>
                                    <?php if (!in_array($t['aplica_a'], ['Ambos', $tipoCliente], true)) {
                                        continue;
                                    } ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="req-<?= $cat ?>-<?= esc($t['codigo'], 'attr') ?>" name="requisitos[<?= $cat ?>][]" value="<?= esc($t['codigo'], 'attr') ?>"<?= in_array($t['codigo'], $requisitosMarcados[$cat], true) ? ' checked' : '' ?>>
                                        <label class="form-check-label" for="req-<?= $cat ?>-<?= esc($t['codigo'], 'attr') ?>"><?= esc($t['nombre']) ?></label>
                                    </div>
                                <?php endforeach ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach ?>
            <?php endforeach ?>
        </div>
    </fieldset>

    <fieldset class="hoja p-4 mb-3">
        <legend class="h5">Período de vigencia de la línea</legend>
        <div class="row g-3">
            <?= $campo('vigencia_inicio', 'Desde', $entrada('vigencia_inicio', 'required', 'date'), '', 'col-md-4') ?>
            <?= $campo('vigencia_fin', 'Hasta', $entrada('vigencia_fin', 'required', 'date'), 'Al terminar, la línea queda Vencida.', 'col-md-4') ?>
        </div>
    </fieldset>

    <?php if (!$alta): ?>
        <fieldset class="hoja p-4 mb-3">
            <legend class="h5">Nueva versión</legend>
            <div class="row g-3">
                <?= $campo('fecha_vigencia', 'Rige desde', '<input type="date" class="form-control' . campo_invalido('fecha_vigencia') . '" id="fecha_vigencia" name="fecha_vigencia" min="' . date('Y-m-d') . '" value="' . esc((string) old('fecha_vigencia', date('Y-m-d')), 'attr') . '" required>', 'Hoy o una fecha futura: no se admite retroactividad.', 'col-md-4') ?>
                <div class="col-md-8">
                    <label for="motivo" class="form-label">Motivo del cambio</label>
                    <textarea class="form-control<?= campo_invalido('motivo') ?>" id="motivo" name="motivo" rows="2" required minlength="5" maxlength="1000"><?= esc((string) old('motivo')) ?></textarea>
                    <?= error_campo('motivo') ?>
                </div>
            </div>
        </fieldset>
    <?php endif ?>

    <div class="d-flex gap-2 justify-content-end mb-4">
        <a href="<?= $alta ? url_to('lineas.localizar') : site_url('admin/lineas/' . $l['id']) ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= $alta ? 'Registrar línea' : 'Registrar nueva versión' ?></button>
    </div>
</form>
<?php $this->endSection() ?>
<?php $this->section('scripts') ?>
<script src="<?= base_url('assets/sigccr/lineas.js') ?>"></script>
<?php $this->endSection() ?>
