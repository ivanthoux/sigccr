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
<form method="get" class="hoja p-3 mb-3 d-flex flex-wrap gap-2 align-items-end">
    <div>
        <label for="estado" class="form-label small mb-1">Estado</label>
        <select id="estado" name="estado" class="form-select form-select-sm">
            <?php foreach ($estados as $k => $t): ?><option value="<?= $k ?>"<?= $estado === $k ? ' selected' : '' ?>><?= $t ?></option><?php endforeach ?>
        </select>
    </div>
    <div class="flex-grow-1">
        <label for="texto" class="form-label small mb-1">Nombre, documento, CUIL o correo</label>
        <input type="search" id="texto" name="texto" class="form-control form-control-sm" value="<?= esc($texto, 'attr') ?>">
    </div>
    <button type="submit" class="btn btn-sm btn-primary">Buscar</button>
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
