<?php
/**
 * CU-77 Autorizar Devolución de Saldo a Favor, pasos 2 a 5 (A4, A5): saldos con su origen y formulario por saldo.
 * El medio de pago original solo se ofrece si el saldo proviene de un pago electrónico (RN-73).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $cliente
 * @var list<array<string, mixed>> $saldos
 * @var list<array<string, mixed>> $autorizaciones
 */
use App\Services\Caja\DevolucionService;

$this->extend('layouts/panel');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('saldos-favor.autorizar-devolucion') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Buscar otro cliente</a></nav>
<div class="encabezado">
    <div>
        <h1>Autorizar devolución</h1>
        <p><?= esc($cliente['denominacion']) ?> · <?= esc(formato_cuit($cliente['cuit_cuil'])) ?></p>
    </div>
</div>
<?php if ($saldos === []): ?>
    <div class="hoja p-4 mb-3"><p class="mb-0">El cliente no tiene saldos a favor.</p></div>
<?php endif ?>
<?php foreach ($saldos as $s): $abierto = (int) old('saldo_id') === (int) $s['id']; ?>
    <section class="hoja p-4 mb-3" aria-labelledby="saldo-<?= (int) $s['id'] ?>">
        <h2 class="h5" id="saldo-<?= (int) $s['id'] ?>">Saldo del <?= esc(formato_fecha_hora($s['fecha'])) ?> · <?= esc($s['origen']) ?></h2>
        <p class="mb-2">Disponible <strong><?= esc(formato_importe((string) $s['importe_disponible'])) ?></strong> · reservado <?= esc(formato_importe((string) $s['importe_reservado'])) ?> · original <?= esc(formato_importe((string) $s['importe_original'])) ?></p>
        <?php if ($s['autorizacion_activa'] !== null): ?>
            <p class="mb-0">Tiene la <a href="<?= site_url('admin/saldos-favor/autorizaciones/' . (int) $s['autorizacion_activa']) ?>">autorización N.º <?= (int) $s['autorizacion_activa'] ?></a> en curso: para cambiar importe o destino, anúlela y registre una nueva (A4, A5).</p>
        <?php elseif ((float) $s['importe_disponible'] <= 0): ?>
            <p class="mb-0 text-body-secondary">Sin importe disponible.</p>
        <?php else: ?>
            <form method="post" action="<?= site_url('admin/saldos-favor/' . (int) $s['id'] . '/devolucion') ?>" novalidate data-confirmar="¿Autoriza la devolución? El importe quedará reservado hasta su ejecución.">
                <?= csrf_field() ?>
                <input type="hidden" name="saldo_id" value="<?= (int) $s['id'] ?>">
                <?php $v = static fn (string $k, string $def = ''): string => esc((string) ($abierto ? (old($k) ?? $def) : $def), 'attr'); $inv = static fn (string $k): string => $abierto ? campo_invalido($k) : ''; ?>
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label" for="importe-<?= (int) $s['id'] ?>">Importe</label><input type="text" inputmode="decimal" class="form-control<?= $inv('importe') ?>" id="importe-<?= (int) $s['id'] ?>" name="importe" value="<?= $v('importe', (string) $s['importe_disponible']) ?>"><?= $abierto ? error_campo('importe') : '' ?></div>
                    <div class="col-md-9"><label class="form-label" for="motivo-<?= (int) $s['id'] ?>">Motivo</label><input type="text" maxlength="500" class="form-control<?= $inv('motivo') ?>" id="motivo-<?= (int) $s['id'] ?>" name="motivo" value="<?= $v('motivo') ?>"><?= $abierto ? error_campo('motivo') : '' ?></div>
                    <div class="col-md-3"><label class="form-label" for="fecha-<?= (int) $s['id'] ?>">Solicitud del cliente: fecha</label><input type="date" class="form-control<?= $inv('fecha_solicitud') ?>" id="fecha-<?= (int) $s['id'] ?>" name="fecha_solicitud" value="<?= $v('fecha_solicitud', hoy_negocio()) ?>"><?= $abierto ? error_campo('fecha_solicitud') : '' ?></div>
                    <div class="col-md-4"><label class="form-label" for="medio-<?= (int) $s['id'] ?>">Medio de la solicitud</label><input type="text" maxlength="100" class="form-control<?= $inv('medio_solicitud') ?>" id="medio-<?= (int) $s['id'] ?>" name="medio_solicitud" value="<?= $v('medio_solicitud') ?>" placeholder="Por ejemplo: nota firmada o correo"><?= $abierto ? error_campo('medio_solicitud') : '' ?></div>
                    <div class="col-md-5"><label class="form-label" for="destino-<?= (int) $s['id'] ?>">Destino</label>
                        <select class="form-select<?= $inv('destino') ?>" id="destino-<?= (int) $s['id'] ?>" name="destino">
                            <?php if ($s['transaccion_origen_id'] !== null): ?><option value="MedioOriginal"<?= $v('destino') === 'MedioOriginal' ? ' selected' : '' ?>>Medio de pago original (pasarela)</option><?php endif ?>
                            <option value="Transferencia"<?= $v('destino') === 'Transferencia' || $s['transaccion_origen_id'] === null ? ' selected' : '' ?>>Transferencia a una cuenta del cliente</option>
                        </select><?= $abierto ? error_campo('destino') : '' ?></div>
                    <div class="col-md-4"><label class="form-label" for="cbu-<?= (int) $s['id'] ?>">CBU (si es transferencia)</label><input type="text" inputmode="numeric" maxlength="22" class="form-control<?= $inv('cbu') ?>" id="cbu-<?= (int) $s['id'] ?>" name="cbu" value="<?= $v('cbu') ?>"><?= $abierto ? error_campo('cbu') : '' ?></div>
                    <div class="col-md-3"><label class="form-label" for="cuit-<?= (int) $s['id'] ?>">CUIT/CUIL del titular</label><input type="text" class="form-control<?= $inv('cuit_titular') ?>" id="cuit-<?= (int) $s['id'] ?>" name="cuit_titular" value="<?= $v('cuit_titular', (string) $cliente['cuit_cuil']) ?>"><?= $abierto ? error_campo('cuit_titular') : '' ?></div>
                    <div class="col-md-5"><label class="form-label" for="entidad-<?= (int) $s['id'] ?>">Entidad bancaria</label><input type="text" maxlength="100" class="form-control<?= $inv('entidad') ?>" id="entidad-<?= (int) $s['id'] ?>" name="entidad" value="<?= $v('entidad') ?>"><?= $abierto ? error_campo('entidad') : '' ?></div>
                    <div class="col-12"><label class="form-label" for="evidencia-<?= (int) $s['id'] ?>">Evidencia de la verificación de titularidad</label><input type="text" maxlength="300" class="form-control<?= $inv('evidencia') ?>" id="evidencia-<?= (int) $s['id'] ?>" name="evidencia" value="<?= $v('evidencia') ?>" placeholder="Por ejemplo: constancia de CBU emitida por el banco, verificada el…"><?= $abierto ? error_campo('evidencia') : '' ?></div>
                </div>
                <p class="small text-body-secondary mt-2 mb-2">No se admiten devoluciones en efectivo, a CVU de billeteras virtuales ni a cuentas de terceros (RN-73, RN-77). La autorización vence a las 72 horas si no se inicia su ejecución.</p>
                <div class="text-end"><button class="btn btn-primary" type="submit">Autorizar la devolución</button></div>
            </form>
        <?php endif ?>
    </section>
<?php endforeach ?>
<?php if ($autorizaciones !== []): ?>
    <section class="hoja p-4">
        <h2 class="h5">Autorizaciones del cliente</h2>
        <table class="table table-sm mb-0">
            <thead><tr><th>N.º</th><th>Fecha</th><th class="text-end">Importe</th><th>Destino</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($autorizaciones as $a): ?>
                <tr><td><?= (int) $a['id'] ?></td><td><?= esc(formato_fecha_hora($a['fecha'])) ?></td><td class="importe"><?= esc(formato_importe((string) $a['importe'], false)) ?></td>
                    <td><?= $a['destino'] === 'MedioOriginal' ? 'Medio original' : 'Transferencia' ?></td><td><?= esc(DevolucionService::ESTADOS[$a['estado']] ?? $a['estado']) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/saldos-favor/autorizaciones/' . (int) $a['id']) ?>">Ver</a></td></tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </section>
<?php endif ?>
<?php $this->endSection() ?>
