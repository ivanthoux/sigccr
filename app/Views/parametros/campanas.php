<?php
/**
 * CU-51 Configurar Parámetros de Campañas Crediticias (RN-39, RN-40, RN-43, RN-64, RN-66; D-43).
 *
 * @var CodeIgniter\View\View $this
 * @var array{id: int, numero: int, valores: array<string, mixed>} $vigente
 * @var array<string, mixed> $f valores del formulario
 * @var list<array{id: int|string, nombre: string, codigo: string}> $lineas líneas activas
 * @var list<array<string, mixed>> $historial
 */
use App\Libraries\Validacion\ValidadorParametrosCampanas as C;

$v = static fn (string $k): string => (string) (old($k) ?? $f[$k]);
$campo = static fn (string $k, string $etiqueta, string $ayuda, string $sufijo = '', string $col = 'col-md-4', string $prefijo = ''): string =>
    "<div class=\"{$col}\"><label for=\"{$k}\" class=\"form-label\">{$etiqueta}</label><div class=\"input-group\">" . ($prefijo !== '' ? "<span class=\"input-group-text\">{$prefijo}</span>" : '')
    . "<input type=\"text\" inputmode=\"decimal\" class=\"form-control" . campo_invalido($k) . "\" id=\"{$k}\" name=\"{$k}\" value=\"" . esc($v($k), 'attr') . '">'
    . ($sufijo !== '' ? "<span class=\"input-group-text\">{$sufijo}</span>" : '') . '</div>' . error_campo($k) . "<div class=\"form-text\">{$ayuda}</div></div>";
$enviado = old('motivo') !== null;
$marcado = static fn (string $k, string $valor) => in_array($valor, $enviado ? (array) old($k) : (array) $f[$k], true);
$check = static fn (string $k): bool => $enviado ? old($k) !== null : (bool) $f[$k];
$lineaSel = $v('linea_id');
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Parámetros de campañas crediticias</h1>
        <p>Activación, límites, elegibilidad, comunicación y vigencia de las campañas automáticas. Versión vigente: <?= (int) $vigente['numero'] ?>.</p>
    </div>
</div>
<?php if ($lineas === []): ?>
    <div class="alert alert-warning" style="max-width: 64rem">No hay ninguna línea de crédito activa: registre o reactive una línea en <a href="<?= url_to('lineas.localizar') ?>">Líneas de crédito</a> antes de configurar las campañas (A2).</div>
<?php else: ?>
<form method="post" action="<?= url_to('parametros.campanas') ?>" style="max-width: 64rem" novalidate data-confirmar="¿Registra la nueva versión de los parámetros de campañas?">
    <?= csrf_field() ?>
    <section class="hoja p-4 mb-3">
        <h2 class="h5">Activación</h2>
        <div class="row g-3">
            <?= $campo('umbral_disponibilidad', 'Umbral de disponibilidad', 'La campaña se activa cuando la disponibilidad proyectada lo supera (CU-92, RN-40).', '', 'col-md-4', '$') ?>
            <?= $campo('periodo_proyeccion_dias', 'Período de proyección', 'Días hacia adelante que considera la proyección de fondos.', 'días') ?>
            <?= $campo('factor_cobrabilidad', 'Factor de cobrabilidad', 'Porción de las cuotas a vencer que se espera cobrar (RN-39).', '%') ?>
            <?= $campo('reserva_minima', 'Reserva mínima de fondos', 'Importe que no se presta (GLO-40): se descuenta del disponible libre.', '', 'col-md-4', '$') ?>
            <div class="col-md-8">
                <label for="linea_id" class="form-label">Línea habilitada para campañas</label>
                <select class="form-select<?= campo_invalido('linea_id') ?>" id="linea_id" name="linea_id">
                    <option value="">Seleccione…</option>
                    <?php foreach ($lineas as $l): ?><option value="<?= (int) $l['id'] ?>"<?= $lineaSel === (string) $l['id'] ? ' selected' : '' ?>><?= esc($l['nombre'] . ' (' . $l['codigo'] . ')') ?></option><?php endforeach ?>
                </select>
                <?= error_campo('linea_id') ?>
                <div class="form-text">Las ofertas usan la versión de condiciones vigente de la línea al activarse la campaña.</div>
            </div>
        </div>
    </section>
    <section class="hoja p-4 mb-3">
        <h2 class="h5">Límites</h2>
        <div class="row g-3">
            <?= $campo('capital_maximo', 'Capital máximo de la campaña', 'Se asigna el menor entre este importe y la disponibilidad proyectada (CU-93).', '', 'col-md-6', '$') ?>
            <?= $campo('cantidad_maxima_ofertas', 'Cantidad máxima de ofertas', 'Se ofrecen a los clientes elegibles más antiguos primero.', 'ofertas', 'col-md-6') ?>
        </div>
    </section>
    <section class="hoja p-4 mb-3">
        <h2 class="h5">Elegibilidad</h2>
        <fieldset class="mb-3">
            <legend class="form-label fs-6">Categorías de ingresos admitidas</legend>
            <?php foreach (C::categorias() as $k => $t): ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input<?= campo_invalido('categorias_admitidas') ?>" type="checkbox" id="cat-<?= $k ?>" name="categorias_admitidas[]" value="<?= $k ?>"<?= $marcado('categorias_admitidas', $k) ? ' checked' : '' ?>>
                    <label class="form-check-label" for="cat-<?= $k ?>"><?= esc($t) ?></label>
                </div>
            <?php endforeach ?>
            <?= error_campo('categorias_admitidas') ?>
        </fieldset>
        <div class="row g-3">
            <?= $campo('antiguedad_minima_meses', 'Antigüedad mínima como cliente', '', 'meses') ?>
            <?= $campo('morosidad_maxima_dias', 'Morosidad máxima admitida', 'Días de atraso máximos de sus cuotas impagas; 0 excluye a cualquier cliente con atraso.', 'días') ?>
            <div class="col-md-4 d-flex align-items-center">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="admite_obligaciones_vigentes" name="admite_obligaciones_vigentes" value="1"<?= $check('admite_obligaciones_vigentes') ? ' checked' : '' ?>>
                    <label class="form-check-label" for="admite_obligaciones_vigentes">Admite clientes con créditos vigentes</label>
                </div>
            </div>
        </div>
        <p class="small text-body-secondary mt-3 mb-0">Se exigen siempre, sin configurarse: cliente activo, acceso al portal habilitado, consentimiento vigente para comunicaciones comerciales (RN-64), las condiciones de la línea y que no tenga otra solicitud en trámite en ella (RN-18). Las condiciones de cada oferta salen de su capacidad de pago (CU-87).</p>
    </section>
    <section class="hoja p-4 mb-3">
        <h2 class="h5">Comunicación</h2>
        <fieldset class="mb-3">
            <legend class="form-label fs-6">Canales habilitados</legend>
            <?php foreach (C::CANALES as $k => $t): ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input<?= campo_invalido('canales') ?>" type="checkbox" id="canal-<?= $k ?>" name="canales[]" value="<?= $k ?>"<?= $marcado('canales', $k) ? ' checked' : '' ?>>
                    <label class="form-check-label" for="canal-<?= $k ?>"><?= esc($t) ?></label>
                </div>
            <?php endforeach ?>
            <?= error_campo('canales') ?>
            <div class="form-text">Los envíos fallidos se reintentan según la política de comunicaciones (RNF-09).</div>
        </fieldset>
        <label for="mensaje" class="form-label">Mensaje adicional de la oferta <span class="text-body-secondary small">(opcional)</span></label>
        <textarea class="form-control<?= campo_invalido('mensaje') ?>" id="mensaje" name="mensaje" rows="2" maxlength="500"><?= esc($v('mensaje')) ?></textarea>
        <?= error_campo('mensaje') ?>
    </section>
    <section class="hoja p-4 mb-3">
        <h2 class="h5">Vigencia</h2>
        <div class="row g-3">
            <?= $campo('duracion_dias', 'Duración máxima de la campaña', 'Las ofertas vencen al cerrarse la campaña.', 'días') ?>
            <div class="col-md-8 d-flex align-items-center">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="cierre_por_disponibilidad" name="cierre_por_disponibilidad" value="1"<?= $check('cierre_por_disponibilidad') ? ' checked' : '' ?>>
                    <label class="form-check-label" for="cierre_por_disponibilidad">Cerrar antes si la disponibilidad proyectada deja de superar el umbral</label>
                </div>
            </div>
        </div>
        <p class="small text-body-secondary mt-3 mb-0">La campaña también se cierra al alcanzar el capital asignado. Solo puede haber una campaña activa a la vez (RN-66).</p>
    </section>
    <section class="hoja p-4 mb-3">
        <h2 class="h5">Nueva versión</h2>
        <div class="row g-3">
            <div class="col-md-3">
                <label for="fecha_vigencia" class="form-label">Rige desde</label>
                <input type="date" class="form-control<?= campo_invalido('fecha_vigencia') ?>" id="fecha_vigencia" name="fecha_vigencia" min="<?= hoy_negocio() ?>" value="<?= esc((string) old('fecha_vigencia', hoy_negocio()), 'attr') ?>">
                <?= error_campo('fecha_vigencia') ?>
            </div>
            <div class="col-md-9">
                <label for="motivo" class="form-label">Motivo del cambio</label>
                <textarea class="form-control<?= campo_invalido('motivo') ?>" id="motivo" name="motivo" rows="2" maxlength="1000"><?= esc((string) old('motivo')) ?></textarea>
                <?= error_campo('motivo') ?>
            </div>
        </div>
        <p class="small text-body-secondary mt-3 mb-0">La versión anterior queda como antecedente. Las campañas activas conservan los parámetros con los que se generaron; las nuevas usan los vigentes al activarse (RN-40). La proyección de fondos usa siempre la versión vigente.</p>
        <div class="d-flex justify-content-end mt-3"><button type="submit" class="btn btn-primary">Registrar nueva versión</button></div>
    </section>
</form>
<?php endif ?>
<?php if ($historial !== []): ?>
    <section class="hoja p-4" style="max-width: 64rem">
        <h2 class="h5">Versiones anteriores</h2>
        <table class="table table-sm mb-0">
            <thead><tr><th>Versión</th><th>Vigente desde</th><th>Responsable</th><th>Motivo</th></tr></thead>
            <tbody>
            <?php foreach ($historial as $h): ?>
                <tr><td><?= (int) $h['numero'] ?></td><td><?= esc(formato_fecha_hora((string) $h['vigente_desde'])) ?></td><td><?= esc((string) ($h['usuario'] ?? 'Instalación')) ?></td><td><?= esc((string) $h['motivo']) ?></td></tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </section>
<?php endif ?>
<?php $this->endSection() ?>
