<?php
/**
 * CU-108 Atender Solicitud de Derechos del Titular, A1: la solicitud llega en la sucursal y la registra el Analista.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $c
 * @var list<array{id: int, numero: int, fecha_registro: string}> $revisables
 */
use App\Libraries\Clientes\DerechosTitular;

$tipoElegido = (string) old('tipo');
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= site_url('admin/clientes/' . (int) $c['id']) ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Cliente</a></nav>
<div class="encabezado">
    <div>
        <h1>Registrar solicitud de derechos</h1>
        <p><?= esc((string) $c['denominacion']) ?>. Verifique su identidad con el documento original antes de registrar el pedido.</p>
    </div>
</div>
<form method="post" action="<?= site_url('admin/derechos') ?>" class="hoja p-4" style="max-width: 48rem" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="cliente_id" value="<?= (int) $c['id'] ?>">
    <div class="mb-2">
        <label for="tipo" class="form-label">Pedido</label>
        <select id="tipo" name="tipo" class="form-select<?= campo_invalido('tipo') ?>" required data-derechos-tipo>
            <option value="">Elija una opción</option>
            <?php foreach (DerechosTitular::TIPOS as $codigo => $nombre): ?>
                <option value="<?= esc($codigo, 'attr') ?>"<?= $tipoElegido === $codigo ? ' selected' : '' ?>><?= esc($nombre) ?></option>
            <?php endforeach ?>
        </select>
        <?= error_campo('tipo') ?>
    </div>
    <div class="mb-2" data-derechos-solicitud<?= $tipoElegido === 'RevisionAutomatizada' ? '' : ' hidden' ?>>
        <label for="solicitud_id" class="form-label">Solicitud cuya decisión se pide revisar</label>
        <select id="solicitud_id" name="solicitud_id" class="form-select<?= campo_invalido('solicitud_id') ?>">
            <option value="">Elija una solicitud</option>
            <?php foreach ($revisables as $r): ?>
                <option value="<?= (int) $r['id'] ?>"<?= (int) old('solicitud_id') === (int) $r['id'] ? ' selected' : '' ?>>N.º <?= (int) $r['numero'] ?> · <?= esc(formato_fecha((string) $r['fecha_registro'])) ?></option>
            <?php endforeach ?>
        </select>
        <?= error_campo('solicitud_id') ?>
    </div>
    <div class="mb-3">
        <label for="detalle" class="form-label">Detalle</label>
        <textarea id="detalle" name="detalle" rows="4" maxlength="<?= DerechosTitular::LARGO_DETALLE ?>" class="form-control<?= campo_invalido('detalle') ?>" required><?= esc((string) old('detalle')) ?></textarea>
        <?= error_campo('detalle') ?>
    </div>
    <div class="d-flex gap-2 justify-content-end">
        <a href="<?= site_url('admin/clientes/' . (int) $c['id']) ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">Registrar solicitud</button>
    </div>
</form>
<?php $this->endSection() ?>
