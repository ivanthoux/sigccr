<?php
/**
 * CU-61 Generar (pasos 2 y 3) y CU-62 Descargar Copia de Seguridad (paso 2): destino, formulario y copias.
 *
 * @var CodeIgniter\View\View $this
 * @var list<array<string, mixed>> $copias
 * @var array{ruta: string, disponible: bool, libre: ?int, en_curso: bool, restauracion: bool, adjuntos: bool} $destino
 * @var array<string, mixed>|null $ultima
 */
use App\Libraries\Copias\PoliticaCopias;

$this->extend('layouts/panel');
$this->section('contenido');
?>
<?php if ($destino['en_curso']): ?><script>setTimeout(() => window.location.reload(), 20000);</script><?php endif ?>
<div class="encabezado">
    <div>
        <h1>Copias de seguridad</h1>
        <p>Copias cifradas de la base de datos<?= $destino['adjuntos'] ? ' y de los archivos del legajo' : '' ?>, con su código de verificación SHA-256. La copia automática se genera todos los días a las 02:00 y se conservan <?= (int) config('Sigccr')->diasConservacionCopias ?> días (RNF-13).</p>
    </div>
</div>
<?php if ($ultima !== null): ?>
    <div class="alert <?= !empty($ultima['exitosa']) ? 'alert-success' : 'alert-warning' ?>">
        <div class="fw-semibold">Última restauración (<?= esc(formato_fecha_hora((string) ($ultima['fin'] ?? ''))) ?>, <?= esc((string) $ultima['responsable']) ?>)</div>
        <div><?= esc((string) $ultima['mensaje']) ?></div>
        <?php if (($ultima['posteriores'] ?? []) !== []): ?><div class="small mt-1">Operaciones posteriores a la copia que deben reconstruirse: <?= esc(implode(', ', $ultima['posteriores'])) ?><?= ($ultima['copia_previa'] ?? null) !== null ? '. Quedan en la copia previa ' . esc((string) $ultima['copia_previa']) . '.' : '.' ?></div><?php endif ?>
    </div>
<?php endif ?>
<form method="post" action="<?= url_to('copias.generar') ?>" class="hoja p-4 mb-3" style="max-width: 56rem" novalidate data-confirmar="¿Genera una copia de seguridad ahora?">
    <?= csrf_field() ?>
    <h2 class="h5">Generar una copia</h2>
    <p class="small mb-2">Destino: <code><?= esc($destino['ruta']) ?></code> ·
        <?= $destino['disponible'] ? 'disponible, ' . esc(PoliticaCopias::tamano($destino['libre'])) . ' libres' : '<span class="text-danger">no disponible</span>' ?></p>
    <label for="descripcion" class="form-label">Descripción <span class="text-body-secondary small">(opcional)</span></label>
    <input type="text" id="descripcion" name="descripcion" maxlength="300" class="form-control<?= campo_invalido('descripcion') ?>" value="<?= esc((string) old('descripcion'), 'attr') ?>" placeholder="Por ejemplo: antes de actualizar los parámetros de morosidad">
    <?= error_campo('descripcion') ?>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <span class="small text-body-secondary"><?= $destino['en_curso'] ? 'Hay una copia en curso: espere a que finalice (A1).' : 'La genera el contenedor de procesos en el próximo minuto.' ?></span>
        <button type="submit" class="btn btn-primary"<?= $destino['en_curso'] || !$destino['disponible'] ? ' disabled' : '' ?>><i class="bi bi-database-down me-1" aria-hidden="true"></i>Generar copia</button>
    </div>
</form>
<?php if ($copias === []): ?>
    <div class="hoja p-4"><p class="mb-0">Todavía no se generó ninguna copia de seguridad.</p></div>
<?php else: ?>
    <div class="hoja p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th scope="col">N.º</th><th scope="col">Fecha y hora</th><th scope="col">Origen</th><th scope="col">Descripción</th><th scope="col">Responsable</th><th scope="col" class="importe">Tamaño</th><th scope="col">Estado</th></tr></thead>
                <tbody>
                <?php foreach ($copias as $c): ?>
                    <tr>
                        <td><a href="<?= url_to('copias.descargar', (int) $c['id']) ?>"><?= (int) $c['id'] ?></a></td>
                        <td><?= esc(formato_fecha_hora((string) $c['fecha_generacion'])) ?></td>
                        <td><?= esc(PoliticaCopias::ORIGENES[$c['origen']] ?? $c['origen']) ?></td>
                        <td class="small"><?= esc((string) ($c['descripcion'] ?? '')) ?><?= in_array($c['estado'], ['Fallida', 'Corrupta'], true) && $c['detalle'] !== null ? '<div class="text-danger">' . esc((string) $c['detalle']) . '</div>' : '' ?></td>
                        <td class="small"><?= esc((string) ($c['responsable'] ?? '')) ?></td>
                        <td class="importe"><?= esc(PoliticaCopias::tamano($c['tamano_bytes'])) ?></td>
                        <td><?= view('copias/_estado', ['estado' => (string) $c['estado']]) ?><?= (int) $c['restauraciones'] > 0 ? ' <span class="small text-body-secondary">restaurada</span>' : '' ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>
<?php $this->endSection() ?>
