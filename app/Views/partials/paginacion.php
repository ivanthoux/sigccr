<?php

declare(strict_types=1);

/**
 * Barra de paginación: cantidad de registros, página actual con acceso a la primera, la anterior, las vecinas, la siguiente
 * y la última, y selector de registros por página.
 *
 * @var int                       $actual     página actual (desde 1)
 * @var int                       $paginas    cantidad de páginas
 * @var int                       $total      cantidad de registros
 * @var int                       $porPagina  registros por página
 * @var list<int>                 $opciones   cantidades de registros por página que se pueden elegir
 * @var callable(int): string     $enlace     dirección de una página
 * @var array<string, string>     $criterios  criterios de búsqueda vigentes, que el selector conserva
 * @var string                    $accion     dirección a la que envía el selector
 */
$pie = $pie ?? false;
$ventana = array_values(array_unique(array_filter(
    [1, $actual - 2, $actual - 1, $actual, $actual + 1, $actual + 2, $paginas],
    static fn (int $n): bool => $n >= 1 && $n <= $paginas,
)));
sort($ventana);
$desde = $total === 0 ? 0 : (($actual - 1) * $porPagina) + 1;
$hasta = min($total, $actual * $porPagina);
?>
<nav class="barra-paginas d-flex flex-wrap justify-content-between align-items-center gap-2 small<?= $pie ? ' mt-2' : ' mb-2' ?>" aria-label="Paginación">
    <span class="text-body-secondary"><?= number_format($desde, 0, ',', '.') ?>–<?= number_format($hasta, 0, ',', '.') ?> de <?= number_format($total, 0, ',', '.') ?> registros</span>
    <ul class="pagination pagination-sm mb-0">
        <li class="page-item<?= $actual <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= esc($enlace(1)) ?>" aria-label="Primera página"<?= $actual <= 1 ? ' tabindex="-1" aria-disabled="true"' : '' ?>>«</a></li>
        <li class="page-item<?= $actual <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= esc($enlace(max(1, $actual - 1))) ?>" aria-label="Página anterior"<?= $actual <= 1 ? ' tabindex="-1" aria-disabled="true"' : '' ?>>Anterior</a></li>
        <?php $previa = 0; foreach ($ventana as $n): ?>
            <?php if ($previa !== 0 && $n - $previa > 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif ?>
            <li class="page-item<?= $n === $actual ? ' active' : '' ?>"><a class="page-link" href="<?= esc($enlace($n)) ?>"<?= $n === $actual ? ' aria-current="page"' : '' ?>><?= $n ?></a></li>
            <?php $previa = $n; ?>
        <?php endforeach ?>
        <li class="page-item<?= $actual >= $paginas ? ' disabled' : '' ?>"><a class="page-link" href="<?= esc($enlace(min($paginas, $actual + 1))) ?>" aria-label="Página siguiente"<?= $actual >= $paginas ? ' tabindex="-1" aria-disabled="true"' : '' ?>>Siguiente</a></li>
        <li class="page-item<?= $actual >= $paginas ? ' disabled' : '' ?>"><a class="page-link" href="<?= esc($enlace($paginas)) ?>" aria-label="Última página"<?= $actual >= $paginas ? ' tabindex="-1" aria-disabled="true"' : '' ?>>»</a></li>
    </ul>
    <form method="get" action="<?= esc($accion) ?>" class="d-flex align-items-center gap-2 mb-0">
        <?php foreach ($criterios as $nombre => $valor): ?><input type="hidden" name="<?= esc((string) $nombre, 'attr') ?>" value="<?= esc((string) $valor, 'attr') ?>"><?php endforeach ?>
        <label class="text-body-secondary" for="por-pagina-<?= $pie ? 'pie' : 'cabecera' ?>">Por página</label>
        <select class="form-select form-select-sm w-auto" id="por-pagina-<?= $pie ? 'pie' : 'cabecera' ?>" name="por_pagina" data-autoenviar>
            <?php foreach ($opciones as $o): ?><option value="<?= (int) $o ?>"<?= $o === $porPagina ? ' selected' : '' ?>><?= (int) $o ?></option><?php endforeach ?>
        </select>
        <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">Aplicar</button></noscript>
    </form>
</nav>
