<?php
/**
 * CU-62 pasos 3 a 8: datos de la copia, código de verificación y descarga (A1); acceso a CU-63.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $c
 */
use App\Libraries\Copias\PoliticaCopias;

$enCurso = in_array($c['estado'], ['Solicitada', 'EnCurso'], true);
$this->extend('layouts/panel');
$this->section('contenido');
?>
<?php if ($enCurso): ?><script>setTimeout(() => window.location.reload(), 15000);</script><?php endif ?>
<div class="encabezado">
    <div>
        <h1>Copia de seguridad N.º <?= (int) $c['id'] ?></h1>
        <p><?= esc(PoliticaCopias::ORIGENES[$c['origen']] ?? $c['origen']) ?> · <?= esc(formato_fecha_hora((string) $c['fecha_generacion'])) ?> · <?= view('copias/_estado', ['estado' => (string) $c['estado']]) ?></p>
    </div>
    <a href="<?= url_to('copias.generar') ?>" class="btn btn-outline-secondary">Copias</a>
</div>
<?php if ($enCurso): ?><div class="alert alert-info">La copia se está generando en el contenedor de procesos. Esta pantalla se actualiza cada 15 segundos.</div><?php endif ?>
<section class="hoja p-4 mb-3" style="max-width: 56rem">
    <dl class="row mb-0">
        <dt class="col-sm-4">Archivo</dt><dd class="col-sm-8"><code><?= esc((string) $c['archivo']) ?></code></dd>
        <dt class="col-sm-4">Descripción</dt><dd class="col-sm-8"><?= esc((string) ($c['descripcion'] ?? '—')) ?></dd>
        <dt class="col-sm-4">Responsable</dt><dd class="col-sm-8"><?= esc((string) ($c['responsable'] ?? '—')) ?></dd>
        <dt class="col-sm-4">Tamaño</dt><dd class="col-sm-8"><?= esc(PoliticaCopias::tamano($c['tamano_bytes'])) ?></dd>
        <dt class="col-sm-4">Contenido</dt><dd class="col-sm-8">Base de datos<?= (int) $c['incluye_adjuntos'] === 1 ? ' y archivos del legajo' : '' ?>, cifrada (AES-256-GCM)</dd>
        <dt class="col-sm-4">Finalizada</dt><dd class="col-sm-8"><?= $c['fecha_fin'] !== null ? esc(formato_fecha_hora((string) $c['fecha_fin'])) : '—' ?></dd>
        <?php if ($c['detalle'] !== null): ?><dt class="col-sm-4">Detalle</dt><dd class="col-sm-8<?= in_array($c['estado'], ['Fallida', 'Corrupta'], true) ? ' text-danger' : '' ?>"><?= esc((string) $c['detalle']) ?></dd><?php endif ?>
        <dt class="col-sm-4">Código de verificación (SHA-256)</dt><dd class="col-sm-8"><code class="text-break"><?= esc((string) ($c['hash_verificacion'] ?? '—')) ?></code></dd>
    </dl>
</section>
<?php if ($c['disponible']): ?>
    <section class="hoja p-4 mb-3" style="max-width: 56rem">
        <h2 class="h6">Descargar</h2>
        <p class="small">Después de descargarla, compruebe que el código del archivo coincide con el informado (por ejemplo, <code>certutil -hashfile <?= esc((string) $c['archivo']) ?> SHA256</code> en Windows o <code>sha256sum</code> en Linux). Si no coincide, no la use y repita la descarga (A3). Para restaurarla fuera del sistema se descifra con <code>php spark copias:descifrar</code> y la misma clave de cifrado.</p>
        <form method="post" action="<?= site_url('admin/copias/' . (int) $c['id'] . '/descargar') ?>" class="d-flex gap-2" data-confirmar="¿Descarga la copia de seguridad N.º <?= (int) $c['id'] ?>?">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary"><i class="bi bi-download me-1" aria-hidden="true"></i>Descargar copia</button>
            <?php if (tiene_permiso('copias.restaurar')): ?><a href="<?= url_to('copias.restaurar', (int) $c['id']) ?>" class="btn btn-outline-danger"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Restaurar esta copia</a><?php endif ?>
        </form>
    </section>
<?php elseif (!$enCurso): ?>
    <div class="alert alert-warning" style="max-width: 56rem">La copia no está disponible para descargar ni restaurar<?= $c['estado'] === 'Verificada' ? ': su archivo ya no está en el volumen' : '' ?> (A1).</div>
<?php endif ?>
<?php if ($c['historial'] !== []): ?>
    <section class="hoja p-4" style="max-width: 56rem">
        <h2 class="h6">Restauraciones de esta copia</h2>
        <table class="table table-sm mb-0">
            <thead><tr><th scope="col">Fecha</th><th scope="col">Responsable</th><th scope="col">Resultado</th><th scope="col">Copia previa</th></tr></thead>
            <tbody>
            <?php foreach ($c['historial'] as $h): ?>
                <tr><td><?= esc(formato_fecha_hora((string) $h['fecha'])) ?></td><td><?= esc((string) ($h['responsable'] ?? '')) ?></td><td class="small<?= (int) $h['exitosa'] === 1 ? '' : ' text-danger' ?>"><?= esc((string) $h['resultado']) ?></td><td class="small"><?= esc((string) ($h['previa'] ?? '—')) ?></td></tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </section>
<?php endif ?>
<?php $this->endSection() ?>
