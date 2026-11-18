<?php
/**
 * CU-103 pasos 2 a 4: contrato de préstamo para imprimir y firmar (RN-78, art. 36 de la Ley 24.240) y constancia
 * firmada para el legajo.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $s datos de ContratoService::obtener()
 */

$c = $s['calculo'];
$plan = $c['plan'];
$k = $s['contrato'];
$juridica = $s['tipo_cliente'] === 'PersonaJuridica';
$this->extend('layouts/panel');
$this->section('contenido');
?>
<span class="documento-propio" hidden></span><?php // D-57: con encabezado propio; al imprimir lleva solo la cabecera y el pie de cada hoja ?>
<nav aria-label="Ruta" class="mb-2 d-print-none"><a href="<?= url_to('contratos.registrar-firmado') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Contratos de préstamo</a></nav>
<div class="encabezado d-print-none">
    <div>
        <h1>Contrato de la solicitud N.º <?= (int) $s['numero'] ?></h1>
        <p><?= esc($s['denominacion']) ?> · <?= esc($s['linea']) ?> · generado el <?= esc(formato_fecha_hora($k['fecha_generacion'])) ?></p>
    </div>
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir dos ejemplares</button>
</div>

<?php if ($s['liquidacion'] === null): ?>
    <section class="hoja p-4 mb-3 d-print-none" aria-labelledby="titulo-constancia">
        <h2 class="h5" id="titulo-constancia">Constancia firmada</h2>
        <?php if ($k['documento_legajo_id'] !== null): ?>
            <p class="mb-2"><span class="badge text-bg-success">Incorporada</span> El cliente recibió su ejemplar el <?= esc(formato_fecha($k['fecha_recepcion'])) ?>; registrada el <?= esc(formato_fecha_hora($k['fecha_registro'])) ?>.
                <a href="<?= site_url('admin/clientes/' . (int) $s['cliente_id'] . '/documentos/' . (int) $k['documento_legajo_id'] . '?ver=1') ?>" target="_blank" rel="noopener">Ver la copia</a></p>
            <p class="small text-body-secondary">Puede reemplazarla mientras la solicitud no se liquide; la anterior se conserva en el legajo (A3).</p>
        <?php else: ?>
            <p class="small">Entregue un ejemplar firmado al cliente y adjunte la copia escaneada del que conserva la entidad. Sin la constancia no se puede liquidar (RN-25).</p>
        <?php endif ?>
        <form method="post" action="<?= site_url('admin/contratos/' . (int) $s['id']) ?>" enctype="multipart/form-data" class="row g-3 align-items-end" novalidate>
            <?= csrf_field() ?>
            <div class="col-md-6">
                <label for="constancia" class="form-label">Copia escaneada de la constancia firmada</label>
                <input type="file" class="form-control<?= campo_invalido('constancia') ?>" id="constancia" name="constancia" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
                <div class="form-text">PDF, JPG o PNG, hasta 5 MB.</div>
                <?= error_campo('constancia') ?>
            </div>
            <div class="col-md-3">
                <label for="fecha_recepcion" class="form-label">Recepción del contrato</label>
                <input type="date" class="form-control<?= campo_invalido('fecha_recepcion') ?>" id="fecha_recepcion" name="fecha_recepcion" value="<?= esc((string) (old('fecha_recepcion') ?? hoy_negocio()), 'attr') ?>" required>
                <?= error_campo('fecha_recepcion') ?>
            </div>
            <div class="col-md-3 text-end"><button type="submit" class="btn btn-primary"><?= $k['documento_legajo_id'] !== null ? 'Reemplazar la constancia' : 'Incorporar al legajo' ?></button></div>
        </form>
    </section>
<?php else: ?>
    <div class="alert alert-info d-print-none">La solicitud ya fue liquidada: el contrato se muestra como antecedente.</div>
<?php endif ?>

<article class="hoja p-4 contrato" aria-labelledby="titulo-contrato">
    <h2 class="h4 text-center" id="titulo-contrato">Contrato de préstamo personal N.º <?= (int) $k['id'] ?></h2>
    <p class="text-center small mb-4">Solicitud N.º <?= (int) $s['numero'] ?> · <?= esc($s['linea']) ?> (versión de condiciones <?= (int) $s['version'] ?>)</p>

    <?php // D-56: cláusulas del modelo de contrato con que se generó (CU-106), con los datos del préstamo ?>
    <?= view('contratos/_clausulas', ['clausulas' => $s['clausulas'], 'calculo' => $c, 'plan' => $plan]) ?>

    <div class="row mt-5 pt-4 text-center small">
        <div class="col-6"><div class="border-top pt-2">Firma y aclaración del cliente<?= $juridica ? ' (representante)' : '' ?></div></div>
        <div class="col-6"><div class="border-top pt-2">Por la entidad</div></div>
    </div>
    <p class="small mt-4 mb-0">Lugar y fecha: ______________________________</p>
    <p class="small text-body-secondary mt-3 mb-0 d-print-none">Modelo de contrato: <?= $s['modelo']['numero'] === null ? 'inicial' : 'versión ' . (int) $s['modelo']['numero'] ?> (CU-106).</p>
</article>
<?php $this->endSection() ?>
