<?php
/**
 * Pre-registros del portal (CU-104 paso 7): los verificados quedan pendientes de validación hasta que el Analista
 * completa el alta (CU-11) o los descarta.
 *
 * @var CodeIgniter\View\View $this
 * @var string $estado
 * @var string $texto
 * @var list<array<string, mixed>> $pre
 */
$estados = ['PendienteValidacion' => 'Pendientes de validación', 'PendienteVerificacion' => 'Sin verificar el correo', 'Completado' => 'Completados', 'Descartado' => 'Descartados', '' => 'Todos'];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Pre-registros del portal</h1>
        <p>Personas que dejaron sus datos en el portal. Verifique su identidad en la sucursal y complete el alta.</p>
    </div>
</div>
<form method="post" class="hoja p-3 mb-3" role="search" aria-label="Buscar pre-registros">
    <?= csrf_field() ?>
    <div class="row g-2 align-items-end">
    <div class="col-md-3">
        <label for="estado" class="form-label small">Estado</label>
        <select id="estado" name="estado" class="form-select">
            <?php foreach ($estados as $k => $t): ?><option value="<?= $k ?>"<?= $estado === $k ? ' selected' : '' ?>><?= $t ?></option><?php endforeach ?>
        </select>
    </div>
    <div class="col-md">
        <label for="texto" class="form-label small">Nombre, documento, CUIL o correo</label>
        <input type="search" id="texto" name="texto" class="form-control" value="<?= esc($texto, 'attr') ?>">
    </div>
    <?= acciones_filtro(limpiar: current_url()) ?>
    </div>
</form>
<?php if ($pre === []): ?>
    <div class="hoja p-4"><p class="mb-0">No hay pre-registros con esos criterios.</p></div>
<?php else: ?>
    <div class="hoja p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Persona</th><th>Documento</th><th>CUIL</th><th>Correo</th><th>Registrado</th><th>Verificado</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($pre as $p): ?>
                    <tr>
                        <td><a href="<?= site_url('admin/pre-registros/' . (int) $p['id']) ?>"><?= esc($p['apellido'] . ', ' . $p['nombre']) ?></a></td>
                        <td><?= esc($p['tipo_documento'] . ' ' . $p['nro_documento']) ?></td>
                        <td><?= esc(formato_cuit($p['cuit_cuil'])) ?></td>
                        <td><?= esc($p['correo']) ?></td>
                        <td><?= esc(formato_fecha_hora((string) $p['creado_en'])) ?></td>
                        <td><?= esc(formato_fecha_hora((string) $p['fecha_verificacion'])) ?: '—' ?></td>
                        <td><?= esc($estados[$p['estado']] ?? $p['estado']) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
