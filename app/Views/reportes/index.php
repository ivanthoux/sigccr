<?php
/**
 * Índice de los reportes de gestión (CU-65 a CU-71, D-44).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, array{0: string, 1: string, 2: string}> $reportes clave => [CU, título, permiso]
 */
$descripciones = [
    'cartera' => 'Créditos otorgados y vigentes, composición del saldo, distribución por línea y estado, e índice de morosidad.',
    'morosidad' => 'Cuotas vencidas impagas por antigüedad, línea y cliente, con los punitorios acumulados.',
    'cobranzas' => 'Cobros confirmados por medio, recupero de mora, efectividad, anulaciones, saldos a favor y órdenes pendientes.',
    'solicitudes' => 'Solicitudes por estado, importes solicitados y otorgados, tiempos de resolución y tasas de aprobación.',
    'refinanciaciones' => 'Propuestas por estado, deuda refinanciada, condonaciones, tiempos e impacto sobre la cartera.',
    'proyeccion' => 'Ingresos y egresos esperados, compromisos, reserva mínima y disponibilidad para nuevas colocaciones.',
    'campanas' => 'Ofertas emitidas, respuestas, solicitudes originadas, capital colocado y conversión de cada campaña.',
];
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado">
    <div>
        <h1>Reportes de gestión</h1>
        <p>Cada reporte se genera para un período y criterios a elección; se puede imprimir, guardar en PDF desde la impresión o exportar a Excel.</p>
    </div>
    <?php if (tiene_permiso('tablero.consultar')): ?><a href="<?= url_to('tablero.consultar') ?>" class="btn btn-outline-primary"><i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Tablero gerencial</a><?php endif ?>
</div>
<div class="row g-3">
    <?php foreach ($reportes as $clave => [$cu, $tituloReporte, $permiso]): ?>
        <div class="col-md-6 col-xl-4">
            <a href="<?= url_to($permiso) ?>" class="hoja p-3 d-block h-100 text-decoration-none text-body">
                <div class="small text-body-secondary"><?= esc($cu) ?></div>
                <h2 class="h6 mb-1"><?= esc($tituloReporte) ?></h2>
                <p class="small mb-0 text-body-secondary"><?= esc($descripciones[$clave] ?? '') ?></p>
            </a>
        </div>
    <?php endforeach ?>
</div>
<?php $this->endSection() ?>
