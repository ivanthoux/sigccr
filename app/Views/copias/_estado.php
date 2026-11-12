<?php
/** Insignia del estado de una copia. @var string $estado */
use App\Libraries\Copias\PoliticaCopias;

$clase = ['Verificada' => 'text-bg-success', 'Solicitada' => 'text-bg-info', 'EnCurso' => 'text-bg-info', 'Fallida' => 'text-bg-danger', 'Corrupta' => 'text-bg-danger', 'Depurada' => 'text-bg-secondary'][$estado] ?? 'text-bg-secondary';
?>
<span class="badge <?= $clase ?>"><?= esc(PoliticaCopias::ESTADOS[$estado] ?? $estado) ?></span>
