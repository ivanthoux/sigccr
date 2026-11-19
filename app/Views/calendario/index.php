<?php
/**
 * CU-82 Actualizar Calendario Operativo (RN-49).
 *
 * @var CodeIgniter\View\View $this
 * @var int $anio
 * @var list<array{fecha: string, descripcion: string, tipo: string}> $dias
 */
use App\Libraries\Solicitudes\CalendarioOperativo;

$hoy = hoy_negocio();
$tipo = (string) (old('tipo') ?? 'Feriado');
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Calendario operativo</h1>
        <p>Feriados y días no hábiles de la entidad. Se usan para calcular el vencimiento de las aprobaciones en horas hábiles (RN-49).</p>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-6">
        <section class="hoja p-4 h-100" aria-labelledby="titulo-dias">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h5 mb-0" id="titulo-dias">Días no hábiles de <?= $anio ?></h2>
                <nav aria-label="Año" class="btn-group btn-group-sm">
                    <a class="btn btn-outline-secondary" href="<?= url_to('calendario.actualizar') ?>?anio=<?= $anio - 1 ?>"><?= $anio - 1 ?></a>
                    <a class="btn btn-outline-secondary" href="<?= url_to('calendario.actualizar') ?>?anio=<?= $anio + 1 ?>"><?= $anio + 1 ?></a>
                </nav>
            </div>
            <?php if ($dias === []): ?>
                <p class="mb-0">No hay días no hábiles registrados para <?= $anio ?>.</p>
            <?php else: ?>
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Fecha</th><th>Descripción</th><th>Tipo</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($dias as $d): $f = substr((string) $d['fecha'], 0, 10); ?>
                        <tr>
                            <td class="text-nowrap"><?= esc(formato_fecha($f)) ?></td>
                            <td><?= esc($d['descripcion']) ?></td>
                            <td class="small"><?= esc(CalendarioOperativo::TIPOS[$d['tipo']] ?? $d['tipo']) ?></td>
                            <td class="text-end">
                                <?php if ($f >= $hoy): ?>
                                    <form method="post" action="<?= site_url('admin/calendario/baja') ?>"><?= csrf_field() ?><input type="hidden" name="fecha" value="<?= esc($f, 'attr') ?>"><button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="Dar de baja el <?= esc(formato_fecha($f), 'attr') ?>">Dar de baja</button></form>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            <?php endif ?>
        </section>
    </div>
    <div class="col-lg-6">
        <form method="post" action="<?= url_to('calendario.actualizar') ?>" class="hoja p-4" novalidate aria-labelledby="titulo-agregar">
            <?= csrf_field() ?>
            <h2 class="h5" id="titulo-agregar">Agregar días no hábiles</h2>
            <?= error_campo('fechas') ?>
            <div class="row g-2">
                <div class="col-sm-5">
                    <label for="fecha" class="form-label small">Fecha</label>
                    <input type="date" class="form-control" id="fecha" name="fecha" value="<?= esc(fecha_campo((string) old('fecha')), 'attr') ?>">
                </div>
                <div class="col-sm-7">
                    <label for="tipo" class="form-label small">Tipo</label>
                    <select class="form-select" id="tipo" name="tipo">
                        <?php foreach (CalendarioOperativo::TIPOS as $k => $t): ?><option value="<?= $k ?>"<?= $tipo === $k ? ' selected' : '' ?>><?= esc($t) ?></option><?php endforeach ?>
                    </select>
                </div>
                <div class="col-12">
                    <label for="descripcion" class="form-label small">Descripción</label>
                    <input type="text" class="form-control" id="descripcion" name="descripcion" maxlength="150" placeholder="Por ejemplo: Día de la Independencia" value="<?= esc((string) old('descripcion'), 'attr') ?>">
                </div>
                <div class="col-12">
                    <label for="carga" class="form-label small">Carga anual <span class="text-body-secondary">(opcional; una fecha por línea: dd/mm/aaaa; descripción; tipo)</span></label>
                    <textarea class="form-control font-monospace small" id="carga" name="carga" rows="6" placeholder="Por ejemplo:&#10;01/01/2027; Año Nuevo&#10;24/03/2027; Día de la Memoria&#10;31/12/2027; Cierre de balance; CierreEntidad"><?= esc((string) old('carga')) ?></textarea>
                    <div class="form-text">Si no indica el tipo, se usa el elegido arriba. Cada fecha se valida por separado y se informan las rechazadas.</div>
                </div>
            </div>
            <div class="text-end mt-3"><button type="submit" class="btn btn-primary">Registrar</button></div>
        </form>
    </div>
</div>
<?php $this->endSection() ?>
