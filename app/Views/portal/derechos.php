<?php
/**
 * CU-108 Atender Solicitud de Derechos del Titular, pasos 1 a 3 (Ley 25.326, arts. 14, 16 y 20): el cliente pide el
 * acceso, la rectificación, la actualización o la supresión de sus datos, o la revisión de una decisión automatizada,
 * y consulta la respuesta.
 *
 * @var CodeIgniter\View\View $this
 * @var list<array<string, mixed>> $solicitudes
 * @var list<array{id: int, numero: int, fecha_registro: string}> $revisables
 */
use App\Libraries\Clientes\DerechosTitular;
use App\Libraries\Clientes\PoliticaDatos;

$tipoElegido = (string) old('tipo');
$this->extend('layouts/portal');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('portal.perfil') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Mi perfil</a></nav>
<div class="encabezado">
    <div>
        <h1>Mis derechos sobre mis datos</h1>
        <p>Puede pedir ver, corregir o suprimir sus datos personales, o que una persona revise una decisión automatizada. Es gratis y lo responderemos dentro de <?= PoliticaDatos::DIAS_ACCESO ?> días corridos (acceso y revisión) o <?= PoliticaDatos::DIAS_RECTIFICACION ?> días hábiles (rectificación, actualización y supresión). Más detalles en la <a href="<?= url_to('politica-datos') ?>">política de datos personales</a>.</p>
    </div>
</div>
<form method="post" action="<?= url_to('portal.derechos') ?>" class="hoja p-4 mb-3" style="max-width: 48rem" novalidate>
    <?= csrf_field() ?>
    <h2 class="h5">Nueva solicitud</h2>
    <div class="mb-2">
        <label for="tipo" class="form-label">¿Qué quiere pedir?</label>
        <select id="tipo" name="tipo" class="form-select<?= campo_invalido('tipo') ?>" required data-derechos-tipo>
            <option value="">Elija una opción</option>
            <?php foreach (DerechosTitular::TIPOS as $codigo => $nombre): ?>
                <option value="<?= esc($codigo, 'attr') ?>"<?= $tipoElegido === $codigo ? ' selected' : '' ?>><?= esc($nombre) ?></option>
            <?php endforeach ?>
        </select>
        <?= error_campo('tipo') ?>
    </div>
    <div class="mb-2" data-derechos-solicitud<?= $tipoElegido === 'RevisionAutomatizada' ? '' : ' hidden' ?>>
        <label for="solicitud_id" class="form-label">Solicitud cuya decisión quiere que se revise</label>
        <select id="solicitud_id" name="solicitud_id" class="form-select<?= campo_invalido('solicitud_id') ?>">
            <option value="">Elija una solicitud</option>
            <?php foreach ($revisables as $r): ?>
                <option value="<?= (int) $r['id'] ?>"<?= (int) old('solicitud_id') === (int) $r['id'] ? ' selected' : '' ?>>N.º <?= (int) $r['numero'] ?> · <?= esc(formato_fecha((string) $r['fecha_registro'])) ?></option>
            <?php endforeach ?>
        </select>
        <?php if ($revisables === []): ?><div class="form-text">No tiene solicitudes rechazadas por motivos crediticios.</div><?php endif ?>
        <?= error_campo('solicitud_id') ?>
    </div>
    <div class="mb-3">
        <label for="detalle" class="form-label">Detalle</label>
        <textarea id="detalle" name="detalle" rows="4" maxlength="<?= DerechosTitular::LARGO_DETALLE ?>" class="form-control<?= campo_invalido('detalle') ?>" required placeholder="Por ejemplo: mi domicilio cambió; necesito una copia de los datos que tienen sobre mí."><?= esc((string) old('detalle')) ?></textarea>
        <?= error_campo('detalle') ?>
    </div>
    <button type="submit" class="btn btn-primary">Enviar solicitud</button>
</form>
<section class="hoja p-0" aria-labelledby="t-mis-solicitudes">
    <h2 class="h5 p-3 mb-0" id="t-mis-solicitudes">Mis solicitudes</h2>
    <?php if ($solicitudes === []): ?>
        <p class="px-3 pb-3 mb-0">Todavía no hizo ninguna solicitud.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th scope="col">N.º</th><th scope="col">Pedido</th><th scope="col">Recibida</th><th scope="col">Responderemos antes del</th><th scope="col">Estado</th></tr></thead>
                <tbody>
                <?php foreach ($solicitudes as $s): ?>
                    <tr>
                        <td><?= (int) $s['id'] ?></td>
                        <td><?= esc(DerechosTitular::TIPOS[$s['tipo']] ?? $s['tipo']) ?><?= $s['solicitud_numero'] !== null ? ' (solicitud N.º ' . (int) $s['solicitud_numero'] . ')' : '' ?></td>
                        <td><?= esc(formato_fecha_hora((string) $s['creado_en'])) ?></td>
                        <td><?= esc(formato_fecha((string) $s['vence'])) ?></td>
                        <td><?= esc(DerechosTitular::ESTADOS[$s['estado']] ?? $s['estado']) ?></td>
                    </tr>
                    <?php if ($s['estado'] !== 'Pendiente'): ?>
                        <tr><td colspan="5" class="small text-body-secondary"><strong>Respuesta (<?= esc(formato_fecha((string) $s['fecha_resolucion'])) ?>):</strong> <?= esc((string) $s['respuesta']) ?></td></tr>
                    <?php endif ?>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
<?= view('partials/leyenda_aaip') ?>
<?php $this->endSection() ?>
