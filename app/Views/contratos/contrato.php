<?php
/**
 * CU-103 pasos 2 a 4: contrato de préstamo para imprimir y firmar (RN-78, art. 36 de la Ley 24.240) y constancia
 * firmada para el legajo.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $s datos de ContratoService::obtener()
 */
use App\Libraries\Finanzas\GastoAdministrativo;
use App\Libraries\Solicitudes\Contrato;

$c = $s['calculo'];
$plan = $c['plan'];
$k = $s['contrato'];
$juridica = $s['tipo_cliente'] === 'PersonaJuridica';
$gasto = GastoAdministrativo::deVersion($s);
$domicilio = trim(($s['cliente']['domicilio'] ?? '') . ($s['cliente']['localidad'] ? ', ' . $s['cliente']['localidad'] . ', ' . $s['cliente']['provincia'] : ''));
$this->extend('layouts/panel');
$this->section('contenido');
?>
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

    <p><strong>Partes.</strong> <?= esc($s['entidad']['razon_social']) ?><?= $s['entidad']['cuit'] !== '' ? ', CUIT ' . esc(formato_cuit($s['entidad']['cuit'])) : '' ?><?= $s['entidad']['domicilio'] !== '' ? ', con domicilio en ' . esc($s['entidad']['domicilio']) : '' ?> (en adelante, «la entidad»), y
        <?= esc($s['denominacion']) ?>, <?= $juridica ? 'CUIT ' . esc(formato_cuit($s['cuit_cuil'])) . ', representada por ' . esc((string) $s['representante_nombre']) . ' (DNI ' . esc((string) $s['representante_documento']) . ')' : esc((string) $s['tipo_documento']) . ' ' . esc((string) $s['nro_documento']) . ', CUIL ' . esc(formato_cuit($s['cuit_cuil'])) ?>,
        con domicilio en <?= esc($domicilio) ?> y correo electrónico <?= esc((string) $s['cliente']['correo']) ?> (en adelante, «el cliente»), celebran este contrato de préstamo de consumo.</p>

    <p><strong>1. Importe.</strong> La entidad presta al cliente <strong><?= esc(formato_importe($s['importe_aprobado'])) ?></strong>. Del importe se descuentan el gasto de otorgamiento de <?= esc(formato_importe($c['gasto_otorgamiento'])) ?> y su IVA de <?= esc(formato_importe($c['iva_otorgamiento'])) ?>; el importe neto a desembolsar es de <strong><?= esc(formato_importe($c['neto'])) ?></strong>, que se transfiere a una cuenta bancaria de titularidad del cliente identificada con su CBU (RN-25, RN-77).</p>

    <p><strong>2. Costo financiero.</strong></p>
    <div class="cft-destacado text-center my-3 p-3 border border-2 rounded">
        <div class="small text-uppercase">Costo financiero total efectivo anual (CFTEA) con IVA</div>
        <div class="cft-valor fw-bold"><?= esc(formato_porcentaje($c['cftea'])) ?></div>
    </div>
    <p>Tasa nominal anual (TNA) fija: <?= esc(formato_porcentaje((string) $s['tna'])) ?>. Tasa efectiva anual (TEA): <?= esc(formato_porcentaje($c['tea'])) ?>. Tasa efectiva mensual: <?= esc(formato_porcentaje($c['tasa_mensual'], 4)) ?>.
        Total de intereses: <?= esc(formato_importe($c['total_intereses'])) ?>. Total a pagar: <?= esc(formato_importe($c['total_a_pagar'])) ?>.</p>

    <p><strong>3. Devolución.</strong> El préstamo se devuelve en <?= (int) $s['cuotas_aprobadas'] ?> cuotas mensuales y consecutivas por el sistema de amortización francés, con cuota constante. La primera vence a los 30 días corridos del desembolso y las siguientes el mismo día de cada mes; si ese día no es hábil, el siguiente día hábil (RN-52). Cada cuota incluye
        <?= $gasto->tipo === 'Porcentual' ? 'un gasto administrativo del ' . esc(formato_porcentaje((string) $s['gasto_administrativo_pct'])) . ' del capital' : 'un gasto administrativo de ' . esc(formato_importe((string) $s['gasto_administrativo_cuota'])) ?>
        y el IVA del interés y del gasto. Los vencimientos definitivos se informan en la constancia de desembolso.</p>
    <div class="table-responsive mb-3">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Cuota</th><th class="text-end">Capital</th><th class="text-end">Interés</th><th class="text-end">Gasto administrativo</th><th class="text-end">IVA</th><th class="text-end">Total</th></tr></thead>
            <tbody>
            <?php foreach ($plan->cuotas as $q): ?>
                <tr><td><?= $q->numero ?></td><td class="importe"><?= esc(formato_importe($q->capital, false)) ?></td><td class="importe"><?= esc(formato_importe($q->interes, false)) ?></td><td class="importe"><?= esc(formato_importe($q->gastos, false)) ?></td><td class="importe"><?= esc(formato_importe($q->iva, false)) ?></td><td class="importe fw-medium"><?= esc(formato_importe($q->total(), false)) ?></td></tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <p><strong>4. Pagos.</strong> Las cuotas se pagan por transferencia o depósito bancario, con tarjeta de débito o de crédito en la sucursal, o por el medio de pago electrónico habilitado. La entidad no recibe pagos en efectivo (art. 1 de la Ley 25.345, RN-77). Cada pago se imputa a punitorios, intereses, gastos, IVA y capital, en ese orden (RN-29).</p>

    <p><strong>5. Mora.</strong> La mora es automática al vencimiento. Si el pago se realiza dentro de los <?= (int) $s['punitorios']['tolerancia'] ?> días corridos posteriores al vencimiento no se cobran punitorios; superado ese plazo, se devenga un interés punitorio diario del <?= esc(formato_porcentaje($s['punitorios']['diaria'], 4)) ?> (<?= esc(formato_porcentaje($s['punitorios']['anual'])) ?> nominal anual) sobre el capital vencido, más su IVA, desde el primer día de atraso, que no supera el 50 % de la tasa compensatoria (art. 769 del Código Civil y Comercial, RN-34).</p>

    <p><strong>6. Cancelación anticipada.</strong> El cliente puede cancelar el total del préstamo en cualquier momento sin comisión. Paga las cuotas vencidas con sus punitorios, el capital de las cuotas no vencidas, el interés de la cuota en curso proporcional a los días transcurridos y el IVA correspondiente; no paga los intereses ni los gastos de las cuotas no vencidas (art. 36 de la Ley 24.240, RN-67).</p>

    <p><strong>7. Información.</strong> El cliente puede consultar su estado de cuenta, sus comprobantes y este contrato en el portal de autogestión. La entidad informa la situación del cliente a la Central de Deudores del BCRA (RN-84). Los datos personales se tratan conforme a la Ley 25.326 y a la política de tratamiento aceptada por el cliente.</p>

    <p><strong>8. Jurisdicción.</strong> Para cualquier controversia son competentes los tribunales del domicilio real del cliente (art. 36 de la Ley 24.240).</p>

    <div class="revocacion border border-2 border-dark rounded p-3 my-4">
        <p class="mb-0 fw-bold">DERECHO DE REVOCACIÓN. El cliente puede revocar este préstamo dentro de los <?= Contrato::DIAS_REVOCACION ?> días hábiles contados desde que recibe este contrato o desde el desembolso, lo que ocurra último, sin costo ni responsabilidad alguna. Para hacerlo, lo comunica a la entidad por cualquier medio y restituye por transferencia el capital recibido más el interés devengado por los días transcurridos, sin gastos ni comisiones. Este derecho no puede renunciarse (arts. 1110 a 1112 del Código Civil y Comercial, RN-79).</p>
    </div>

    <p>Se firman dos ejemplares de un mismo tenor; el cliente recibe uno en este acto.</p>
    <div class="row mt-5 pt-4 text-center small">
        <div class="col-6"><div class="border-top pt-2">Firma y aclaración del cliente<?= $juridica ? ' (representante)' : '' ?></div></div>
        <div class="col-6"><div class="border-top pt-2">Por la entidad</div></div>
    </div>
    <p class="small mt-4 mb-0">Lugar y fecha: ______________________________</p>
</article>
<?php $this->endSection() ?>
