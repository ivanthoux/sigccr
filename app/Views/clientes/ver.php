<?php
/**
 * CU-15 Consultar Perfil del cliente, con su legajo, consentimientos y operaciones disponibles.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $c
 * @var list<array{id: int, codigo: string, nombre: string, aplica_a: string}> $tipos
 */
use App\Libraries\Clientes\RequisitosDocumentales;
use App\Libraries\Validacion\ValidadorCliente;

$id = (int) $c['id'];
$juridica = $c['tipo_cliente'] === 'PersonaJuridica';
$activo = $c['estado'] === 'Activo';
$categoria = RequisitosDocumentales::CATEGORIAS[$c['tipo_cliente']][$c['categoria_ingresos']] ?? $c['categoria_ingresos'];
$presentes = array_column($c['documentos'], 'codigo');
$faltantes = RequisitosDocumentales::faltantes(RequisitosDocumentales::identificacion($c['tipo_cliente']), $presentes);
$sugeridosFaltantes = RequisitosDocumentales::faltantes(RequisitosDocumentales::sugeridos($c['categoria_ingresos']), $presentes);
$nombresTipo = array_column($tipos, 'nombre', 'codigo');
$fila = static fn (string $etiqueta, ?string $valor): string => $valor === null || $valor === '' ? '' : '<dt class="col-sm-5 fw-medium">' . $etiqueta . '</dt><dd class="col-sm-7">' . $valor . '</dd>';
$politica = $c['consentimientos']['PoliticaDatos'];
$comerciales = $c['consentimientos']['ComunicacionesComerciales'];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('clientes.localizar') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Clientes</a></nav>
<div class="encabezado">
    <div>
        <h1><?= esc($c['denominacion']) ?></h1>
        <p><?= $juridica ? 'Persona jurídica' : 'Persona humana' ?>, cliente desde el <?= esc(formato_fecha($c['fecha_alta'])) ?>
            <span class="badge rounded-pill ms-1 <?= $activo ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $activo ? 'Activo' : 'Desactivado' ?></span></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= site_url("admin/clientes/{$id}/historial") ?>" class="btn btn-primary"><i class="bi bi-clock-history me-1" aria-hidden="true"></i>Ver historial crediticio</a>
        <?php if ($activo && tiene_permiso('clientes.modificar')): ?><a href="<?= site_url("admin/clientes/{$id}/editar") ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Modificar datos</a><?php endif ?>
        <?php if ($activo && tiene_permiso('clientes.desactivar')): ?><a href="<?= site_url("admin/clientes/{$id}/desactivar") ?>" class="btn btn-outline-danger"><i class="bi bi-person-x me-1" aria-hidden="true"></i>Desactivar</a><?php endif ?>
        <?php if (!$activo && tiene_permiso('clientes.reactivar')): ?><a href="<?= site_url("admin/clientes/{$id}/reactivar") ?>" class="btn btn-outline-secondary"><i class="bi bi-person-check me-1" aria-hidden="true"></i>Reactivar</a><?php endif ?>
    </div>
</div>

<?php if (!$activo && $c['desactivacion'] !== null): ?>
    <div class="alert alert-info" role="status">Desactivado el <?= esc(formato_fecha_hora($c['desactivacion']['fecha_hora'])) ?> por <?= esc($c['desactivacion']['responsable']) ?>. Motivo: <?= esc((string) $c['desactivacion']['motivo']) ?></div>
<?php endif ?>

<div class="row g-3">
    <div class="col-xl-7">
        <section class="hoja p-4 h-100" aria-labelledby="titulo-datos">
            <h2 class="h5" id="titulo-datos">Datos del cliente</h2>
            <dl class="row mb-0">
                <?php if ($juridica): ?>
                    <?= $fila('CUIT', esc(formato_cuit($c['cuit_cuil']))) ?>
                    <?= $fila('Fecha de constitución', esc(formato_fecha($c['fecha_constitucion']))) ?>
                    <?= $fila('Actividad principal', esc($c['actividad_principal'])) ?>
                    <?= $fila('Representante', esc($c['representante_nombre'] . ' (DNI ' . $c['representante_documento'] . '), ' . $c['representante_cargo'])) ?>
                <?php else: ?>
                    <?= $fila('Documento', esc($c['tipo_documento'] . ' ' . $c['nro_documento'])) ?>
                    <?= $fila('CUIT/CUIL', $c['cuit_cuil'] ? esc(formato_cuit($c['cuit_cuil'])) : '<span class="text-body-secondary">Sin informar (obligatorio para solicitar crédito)</span>') ?>
                    <?= $fila('Fecha de nacimiento', esc(formato_fecha($c['fecha_nacimiento']))) ?>
                    <?= $fila('Estado civil', $c['estado_civil'] ? esc(ValidadorCliente::ESTADOS_CIVILES[$c['estado_civil']] ?? $c['estado_civil']) : null) ?>
                    <?= $fila('Ocupación', $c['ocupacion'] ? esc($c['ocupacion']) : null) ?>
                    <?= $fila('Empleador o actividad', $c['empleador'] ? esc($c['empleador']) : null) ?>
                <?php endif ?>
                <?= $fila('Categoría de ingresos', esc($categoria)) ?>
                <?= $fila($juridica ? 'Ingresos netos mensuales' : 'Ingresos declarados', '<span class="importe d-inline">' . esc(formato_importe($c['ingresos_declarados'])) . '</span>') ?>
                <?= $fila($juridica ? 'Domicilio legal' : 'Domicilio', esc($c['domicilio'])) ?>
                <?= $fila('Teléfono', $c['telefono'] ? esc($c['telefono']) : null) ?>
                <?= $fila('Correo electrónico', esc((string) $c['correo']) . ((int) $c['correo_verificado'] === 1 ? ' <span class="badge rounded-pill text-bg-success">Verificado</span>' : ' <span class="badge rounded-pill text-bg-light border">Sin verificar</span>')
                    . ($c['correo_pendiente'] ? '<div class="small text-body-secondary mt-1">Correo nuevo pendiente de verificación: ' . esc($c['correo_pendiente']) . '</div>' : '')) ?>
            </dl>
        </section>
    </div>
    <div class="col-xl-5">
        <section class="hoja p-4 mb-3" aria-labelledby="titulo-portal">
            <h2 class="h5" id="titulo-portal">Acceso al portal</h2>
            <p class="mb-1"><?= etiqueta_estado_cuenta((string) ($c['estado_cuenta'] ?? 'Sin cuenta')) ?></p>
            <p class="small text-body-secondary mb-0"><?= ($c['estado_cuenta'] ?? '') === 'PendienteActivacion'
                ? 'El cliente activa su acceso desde el portal una vez verificado el correo (CU-04).'
                : 'El registro en el padrón no habilita por sí mismo el portal.' ?></p>
        </section>
        <section class="hoja p-4" aria-labelledby="titulo-consentimientos">
            <h2 class="h5" id="titulo-consentimientos">Tratamiento de datos</h2>
            <ul class="list-unstyled mb-0">
                <li class="mb-2"><i class="bi <?= ($politica['otorgado'] ?? 0) ? 'bi-check-circle text-success' : 'bi-x-circle text-danger' ?> me-1" aria-hidden="true"></i>Política de datos personales: <?= ($politica['otorgado'] ?? 0) ? 'aceptada' : 'sin aceptar' ?>
                    <?php if ($politica): ?><span class="small text-body-secondary">(<?= esc(formato_fecha($politica['fecha'])) ?>, <?= esc(mb_strtolower($politica['medio'])) ?>)</span><?php endif ?></li>
                <li><i class="bi <?= ($comerciales['otorgado'] ?? 0) ? 'bi-check-circle text-success' : 'bi-dash-circle text-secondary' ?> me-1" aria-hidden="true"></i>Comunicaciones comerciales: <?= ($comerciales['otorgado'] ?? 0) ? 'acepta' : 'no acepta' ?>
                    <?php if ($comerciales): ?><span class="small text-body-secondary">(<?= esc(formato_fecha($comerciales['fecha'])) ?>, <?= esc(mb_strtolower($comerciales['medio'])) ?>)</span><?php endif ?></li>
            </ul>
        </section>
    </div>

    <div class="col-12">
        <section class="hoja p-4" id="legajo" aria-labelledby="titulo-legajo">
            <h2 class="h5" id="titulo-legajo">Legajo</h2>
            <?php if ($faltantes !== []): ?>
                <div class="alert alert-warning small" role="status">Falta documentación de identificación: <?= esc(implode(', ', array_map(static fn ($f) => $nombresTipo[$f] ?? $f, $faltantes))) ?>.</div>
            <?php elseif ($sugeridosFaltantes !== []): ?>
                <p class="small text-body-secondary">Documentación habitual de su categoría todavía no incorporada: <?= esc(implode(', ', array_map(static fn ($f) => $nombresTipo[$f] ?? $f, $sugeridosFaltantes))) ?>. Se exige solo si la línea que solicite la requiere (D-14).</p>
            <?php endif ?>
            <?php if ($c['documentos'] === []): ?>
                <p class="text-body-secondary">El legajo no tiene documentos.</p>
            <?php else: ?>
                <table class="table align-middle">
                    <thead><tr><th>Documento</th><th>Archivo</th><th class="importe">Tamaño</th><th>Incorporado</th><th><span class="visually-hidden">Descargar</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($c['documentos'] as $d): ?>
                        <tr>
                            <td><?= esc($d['tipo']) ?></td>
                            <td class="text-break"><?= esc($d['nombre_original']) ?></td>
                            <td class="importe"><?= number_format((int) $d['tamano_bytes'] / 1024, 0, ',', '.') ?> KB</td>
                            <td><?= esc(formato_fecha_hora($d['fecha_incorporacion'])) ?></td>
                            <td class="text-end"><a href="<?= site_url("admin/clientes/{$id}/documentos/{$d['id']}") ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1" aria-hidden="true"></i>Descargar</a></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            <?php endif ?>
            <?php if ($activo && tiene_permiso('clientes.modificar')): ?>
                <form method="post" action="<?= site_url("admin/clientes/{$id}/documentos") ?>" enctype="multipart/form-data" class="row g-2 align-items-end mt-1">
                    <?= csrf_field() ?>
                    <div class="col-md-5">
                        <label for="tipo-documento-nuevo" class="form-label small">Agregar documento</label>
                        <select class="form-select" id="tipo-documento-nuevo" data-destino="archivo-nuevo">
                            <?php foreach ($tipos as $t): if (!in_array($t['aplica_a'], ['Ambos', $c['tipo_cliente']], true)) { continue; } ?>
                                <option value="<?= esc($t['codigo'], 'attr') ?>"<?= ($faltantes[0] ?? $sugeridosFaltantes[0] ?? '') === $t['codigo'] ? ' selected' : '' ?>><?= esc($t['nombre']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="archivo-nuevo" class="form-label small">Archivos (PDF, JPG o PNG, hasta 5 MB)</label>
                        <input type="file" class="form-control<?= campo_invalido('documentos') ?>" id="archivo-nuevo" name="documentos[<?= esc($faltantes[0] ?? $sugeridosFaltantes[0] ?? 'DOC_IDENTIDAD', 'attr') ?>][]" accept=".pdf,.jpg,.jpeg,.png" multiple required>
                    </div>
                    <div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-secondary"><i class="bi bi-upload me-1" aria-hidden="true"></i>Incorporar</button></div>
                    <div class="col-12"><?= error_campo('documentos') ?><?php foreach ($tipos as $t) { echo error_campo('doc_' . $t['codigo']); } ?></div>
                </form>
            <?php endif ?>
        </section>
    </div>
</div>
<?php $this->endSection() ?>

<?php $this->section('scripts') ?>
<script>
    // El tipo elegido define el nombre del campo de archivo: documentos[<código>][]
    document.querySelectorAll('select[data-destino]').forEach((s) => s.addEventListener('change', () => {
        document.getElementById(s.dataset.destino).name = 'documentos[' + s.value + '][]';
    }));
</script>
<?php $this->endSection() ?>
