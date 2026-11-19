<?php
/**
 * CU-21, CU-22 y CU-33 pasos 1 y 2: búsqueda del cliente (CU-54) para simular, registrar una solicitud o cobrar.
 *
 * @var CodeIgniter\View\View $this
 * @var string $texto
 * @var list<array<string, mixed>> $clientes
 * @var string $destino nombre de la ruta a la que continúa
 * @var array<string, mixed>|null $jornada jornada de caja operable (solo para el cobro)
 */
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1><?= esc($titulo) ?></h1>
        <p><?= match ($destino) {
            'solicitudes.simular' => 'Plan preliminar con las condiciones vigentes de una línea. No genera solicitud ni deuda (RN-17).',
            'simulaciones.comparar' => 'Compara todas las líneas para un cliente que no sabe qué crédito le conviene. No genera solicitud ni deuda (RN-17).',
            'creditos.revocar' => 'Revocación de un préstamo dentro de los 10 días hábiles desde la recepción del contrato o el desembolso (RN-79).',
            'cobros.registrar' => 'Cobro de cuotas por transferencia, depósito o tarjeta, con su número de operación (RN-29, RN-77).',
            'refinanciaciones.simular' => 'Plan preliminar para refinanciar los créditos en mora del cliente. No modifica la deuda ni genera una propuesta (RN-36, RN-70).',
            'refinanciaciones.registrar-propuesta' => 'Propuesta formal a partir de una simulación vigente, para la resolución del Gerente (RN-36).',
            'punitorios.condonar' => 'Reducción autorizada de los intereses punitorios pendientes, con fundamento y hasta el máximo configurado (RN-38).',
            'creditos.cancelar-anticipadamente' => 'Cancelación anticipada total de un crédito, sin intereses ni gastos no devengados y sin comisión (RN-67).',
            'saldos-favor.aplicar' => 'Aplicación del saldo a favor del cliente a sus cuotas pendientes, con su conformidad (RN-57).',
            'saldos-favor.autorizar-devolucion' => 'Devolución del saldo a favor por el medio de pago original o a una cuenta verificada del cliente (RN-73).',
            'pagos-electronicos.generar-orden' => 'Orden de pago de importe fijo para que el cliente pague sus cuotas con la pasarela (RN-54).',
            default => 'Formalice una simulación vigente del cliente.',
        } ?></p>
    </div>
</div>
<?php if ($destino === 'cobros.registrar' && ($jornada ?? null) === null): ?>
    <div class="alert alert-warning">No tiene una caja abierta hoy: <a href="<?= url_to('cajas.abrir') ?>">ábrala</a> para registrar cobros (CU-31, RN-28). Puede consultar la deuda del cliente igualmente.</div>
<?php endif ?>
<form method="get" action="<?= url_to($destino) ?>" class="hoja p-3 mb-3" role="search" aria-label="Buscar cliente" novalidate>
    <div class="row g-2 align-items-end">
        <div class="col-md-8 col-lg-6">
            <label for="texto" class="form-label small">Cliente: nombre, apellido o razón social</label>
            <input type="search" class="form-control" id="texto" name="texto" value="<?= esc($texto, 'attr') ?>" aria-describedby="ayuda-texto">
        </div>
        <div class="col-auto"><button type="submit" class="btn btn-primary"><i class="bi bi-search me-1" aria-hidden="true"></i>Buscar</button></div>
    </div>
    <div class="form-text" id="ayuda-texto">Escriba al menos dos letras del nombre, el apellido o la razón social.</div>
</form>
<?php if ($texto !== ''): ?>
    <?php if ($clientes === []): ?>
        <div class="hoja p-4"><p class="mb-0">No hay clientes activos que coincidan. Revise el nombre o búsquelo en <a href="<?= url_to('clientes.localizar') ?>">Clientes</a>.</p></div>
    <?php else: ?>
        <div class="hoja p-3">
            <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Cliente</th><th>Documento</th><th>CUIT/CUIL</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($clientes as $cl): ?>
                    <tr>
                        <td class="fw-medium"><?= esc($cl['denominacion']) ?></td>
                        <td class="text-nowrap"><?= $cl['nro_documento'] ? esc($cl['tipo_documento'] . ' ' . $cl['nro_documento']) : '' ?></td>
                        <td class="text-nowrap"><?= esc(formato_cuit($cl['cuit_cuil'])) ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= url_to($destino) ?>?cliente=<?= (int) $cl['id'] ?>">Seleccionar<span class="visually-hidden"> a <?= esc($cl['denominacion']) ?></span></a></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif ?>
<?php endif ?>
<?php $this->endSection() ?>
