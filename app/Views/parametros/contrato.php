<?php
/**
 * CU-106 Configurar Modelo de Contrato (D-56; RN-15, RN-78, RN-79).
 *
 * @var CodeIgniter\View\View $this
 * @var array{id: ?int, numero: ?int, clausulas: list<array{estilo: string, titulo: string, texto: string}>} $vigente
 * @var list<array{estilo: string, titulo: string, texto: string}> $clausulas
 * @var bool $inicial
 * @var list<array<string, mixed>> $historial
 */
use App\Libraries\Solicitudes\ModeloContrato;

$fila = static function (array $c, int $n): string {
    $opciones = '';
    foreach (ModeloContrato::ESTILOS as $k => $t) {
        $opciones .= '<option value="' . $k . '"' . ($c['estilo'] === $k ? ' selected' : '') . ">{$t}</option>";
    }
    $id = static fn (string $campo): string => "clausula-{$n}-{$campo}";

    return '<fieldset class="clausula border rounded p-3 mb-3" data-clausula>'
        . '<legend class="h6 float-none w-auto px-1 mb-2">Cláusula <span data-numero>' . $n . '</span></legend>'
        . '<div class="row g-2 align-items-end">'
        . '<div class="col-md-3"><label class="form-label small" for="' . $id('estilo') . '">Forma</label><select class="form-select form-select-sm' . campo_invalido("clausula_{$n}_estilo") . '" id="' . $id('estilo') . '" name="estilo[]">' . $opciones . '</select>' . error_campo("clausula_{$n}_estilo") . '</div>'
        . '<div class="col-md-6"><label class="form-label small" for="' . $id('titulo') . '">Título</label><input type="text" class="form-control form-control-sm' . campo_invalido("clausula_{$n}_titulo") . '" id="' . $id('titulo') . '" name="titulo[]" maxlength="' . ModeloContrato::MAX_TITULO . '" value="' . esc($c['titulo'], 'attr') . '">' . error_campo("clausula_{$n}_titulo") . '</div>'
        . '<div class="col-md-3 d-flex gap-1 justify-content-md-end">'
        . '<button type="button" class="btn btn-sm btn-outline-secondary" data-mover="-1"><i class="bi bi-arrow-up" aria-hidden="true"></i><span class="visually-hidden">Subir la cláusula</span></button>'
        . '<button type="button" class="btn btn-sm btn-outline-secondary" data-mover="1"><i class="bi bi-arrow-down" aria-hidden="true"></i><span class="visually-hidden">Bajar la cláusula</span></button>'
        . '<button type="button" class="btn btn-sm btn-outline-danger" data-quitar><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">Quitar la cláusula</span></button></div>'
        . '<div class="col-12"><label class="form-label small" for="' . $id('texto') . '">Texto</label><textarea class="form-control form-control-sm' . campo_invalido("clausula_{$n}_texto") . '" id="' . $id('texto') . '" name="texto[]" rows="4" maxlength="' . ModeloContrato::MAX_TEXTO . '">' . esc($c['texto']) . '</textarea>' . error_campo("clausula_{$n}_texto") . '</div>'
        . '</div></fieldset>';
};
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Modelo de contrato</h1>
        <p>Cláusulas del contrato de préstamo que se imprime y firma (RN-78). Vigente: <?= $vigente['numero'] === null ? 'modelo inicial' : 'versión ' . (int) $vigente['numero'] ?>.</p>
    </div>
    <a href="<?= url_to('parametros.contrato') ?>?inicial=1" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Cargar el modelo inicial</a>
</div>
<?php if ($inicial): ?><div class="alert alert-info">El formulario muestra el modelo inicial. Se aplica recién al registrar la nueva versión.</div><?php endif ?>

<div class="row g-3">
    <div class="col-xl-8">
        <form method="post" action="<?= url_to('parametros.contrato') ?>" novalidate id="form-modelo-contrato">
            <?= csrf_field() ?>
            <section class="hoja p-4 mb-3" aria-labelledby="titulo-clausulas">
                <h2 class="h5" id="titulo-clausulas">Cláusulas</h2>
                <p class="small text-body-secondary">Se numeran solas en el orden de la lista. Las partes, el lugar y fecha y las firmas se agregan al imprimir.</p>
                <?php if (isset(session('errores')['clausulas'])): ?><div class="alert alert-danger py-2" role="alert"><?= esc(session('errores')['clausulas']) ?></div><?php endif ?>
                <div data-lista-clausulas>
                    <?php foreach ($clausulas as $i => $c) { echo $fila($c, $i + 1); } ?>
                </div>
                <template data-plantilla-clausula><?= $fila(['estilo' => 'numerada', 'titulo' => '', 'texto' => ''], 0) ?></template>
                <button type="button" class="btn btn-outline-secondary" data-agregar><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Agregar cláusula</button>
            </section>
            <section class="hoja p-4 mb-3" aria-labelledby="titulo-version">
                <h2 class="h5" id="titulo-version">Nueva versión</h2>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="fecha_vigencia" class="form-label">Rige desde</label>
                        <input type="date" class="form-control<?= campo_invalido('fecha_vigencia') ?>" id="fecha_vigencia" name="fecha_vigencia" min="<?= hoy_negocio() ?>" value="<?= esc((string) old('fecha_vigencia', hoy_negocio()), 'attr') ?>">
                        <?= error_campo('fecha_vigencia') ?>
                    </div>
                    <div class="col-md-8">
                        <label for="motivo" class="form-label">Motivo del cambio</label>
                        <textarea class="form-control<?= campo_invalido('motivo') ?>" id="motivo" name="motivo" rows="2" maxlength="500" placeholder="Por ejemplo: nueva cláusula exigida por la Comunicación «A» …"><?= esc((string) old('motivo')) ?></textarea>
                        <?= error_campo('motivo') ?>
                    </div>
                </div>
                <p class="small text-body-secondary mt-3 mb-0">El nuevo modelo se aplica a los contratos que se generen desde esa fecha. Los contratos ya generados, firmados o no, conservan su texto (RN-15).</p>
                <div class="d-flex justify-content-end mt-3"><button type="submit" class="btn btn-primary">Registrar nueva versión</button></div>
            </section>
        </form>
    </div>
    <div class="col-xl-4">
        <section class="hoja p-4 mb-3" aria-labelledby="titulo-variables">
            <h2 class="h5" id="titulo-variables">Cómo se escribe</h2>
            <ul class="small ps-3">
                <li>Las <strong>variables</strong> se escriben entre llaves y el contrato las reemplaza con los datos del préstamo: <code>{importe}</code>.</li>
                <li><code>{cuadro_cft}</code> y <code>{plan_cuotas}</code> van una sola vez, cada uno en un párrafo propio.</li>
                <li>Una línea en blanco separa párrafos; <code>**texto**</code> se imprime en negrita.</li>
                <li>Son obligatorios el costo financiero destacado, el plan de pago (art. 36 de la Ley 24.240) y la revocación con <code>{plazo_revocacion}</code> (art. 1110 del Código Civil y Comercial).</li>
            </ul>
            <h3 class="h6 mt-3">Variables</h3>
            <dl class="small mb-0 variables-contrato">
                <?php foreach (ModeloContrato::VARIABLES + ModeloContrato::BLOQUES as $k => $d): ?>
                    <dt><code>{<?= esc($k) ?>}</code></dt><dd class="text-body-secondary"><?= esc($d) ?></dd>
                <?php endforeach ?>
            </dl>
        </section>
    </div>
</div>

<?php if ($historial !== []): ?>
    <section class="hoja p-4">
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

<?php $this->section('scripts') ?>
<script>
    // CU-106: agregar, quitar y ordenar cláusulas; los números e identificadores siguen el orden de la lista
    (() => {
        const form = document.getElementById('form-modelo-contrato');
        const lista = form.querySelector('[data-lista-clausulas]');
        const plantilla = form.querySelector('[data-plantilla-clausula]');
        const renumerar = () => lista.querySelectorAll('[data-clausula]').forEach((f, i) => {
            const n = i + 1;
            f.querySelector('[data-numero]').textContent = n;
            f.querySelectorAll('[id^="clausula-"]').forEach((el) => {
                const nuevo = el.id.replace(/^clausula-\d+-/, 'clausula-' + n + '-');
                f.querySelector('label[for="' + el.id + '"]')?.setAttribute('for', nuevo);
                el.id = nuevo;
            });
        });
        form.querySelector('[data-agregar]').addEventListener('click', () => {
            lista.appendChild(plantilla.content.cloneNode(true));
            renumerar();
            lista.lastElementChild.querySelector('input').focus();
        });
        lista.addEventListener('click', (e) => {
            const b = e.target.closest('button');
            const f = b?.closest('[data-clausula]');
            if (!f) return;
            if (b.hasAttribute('data-quitar')) {
                f.remove();
            } else if (b.dataset.mover === '-1' && f.previousElementSibling) {
                f.previousElementSibling.before(f);
            } else if (b.dataset.mover === '1' && f.nextElementSibling) {
                f.nextElementSibling.after(f);
            }
            renumerar();
            if (b.isConnected) b.focus();
        });
    })();
</script>
<?php $this->endSection() ?>
