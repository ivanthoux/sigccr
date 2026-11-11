<?php
/**
 * Insignia del estado de una propuesta de refinanciación.
 *
 * @var string $estado
 */
use App\Services\Cartera\RefinanciacionService;

$colores = ['PendienteResolucion' => 'text-bg-warning', 'AprobadaPendienteAceptacion' => 'text-bg-info', 'Observada' => 'text-bg-danger', 'Rechazada' => 'text-bg-secondary', 'Aplicada' => 'text-bg-success', 'Vencida' => 'text-bg-secondary', 'Cancelada' => 'text-bg-secondary'];
?>
<span class="badge <?= $colores[$estado] ?? 'text-bg-light' ?>"><?= esc(RefinanciacionService::ESTADOS[$estado] ?? $estado) ?></span>
