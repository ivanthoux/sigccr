<?php
/**
 * Botón «Filtrar» de los listados (RNF-07): en el celular los filtros se pliegan para que la lista quede a la vista.
 * El formulario de filtros lleva class="collapse d-md-block" (y "show" si hay criterios) y el id que se indica aquí.
 *
 * @var string $id id del formulario de filtros
 * @var string $que qué se filtra: «clientes», «usuarios»…
 * @var bool $abierto si hay criterios aplicados
 */
?>
<button class="btn btn-outline-secondary w-100 mb-3 d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#<?= esc($id, 'attr') ?>" aria-expanded="<?= $abierto ? 'true' : 'false' ?>" aria-controls="<?= esc($id, 'attr') ?>">
    <i class="bi bi-funnel me-1" aria-hidden="true"></i>Filtrar <?= esc($que) ?><?= $abierto ? ' (con criterios aplicados)' : '' ?>
</button>
