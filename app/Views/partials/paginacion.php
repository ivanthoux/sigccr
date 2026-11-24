<?php

declare(strict_types=1);

/**
 * Paginación en el servidor con la misma forma que los listados con DataTables (D-59): arriba «Mostrar [n] registros» a la
 * izquierda; abajo «Mostrando X a Y de Z registros» a la izquierda; las páginas (Primera, Anterior, vecinas, Siguiente,
 * Última) a la derecha, arriba y abajo, para no tener que bajar hasta el final para cambiar de página.
 *
 * @var string                    $parte      'cabecera' (antes de la tabla) o 'pie' (después)
 * @var int                       $actual     página actual (desde 1)
 * @var int                       $paginas    cantidad de páginas
 * @var int                       $total      cantidad de registros
 * @var int                       $porPagina  registros por página
 * @var list<int>                 $opciones   cantidades de registros por página que se pueden elegir
 * @var callable(int): string     $enlace     dirección de una página
 * @var array<string, string>     $criterios  criterios de búsqueda vigentes, que el selector conserva
 * @var string                    $accion     dirección a la que envía el selector
 */
$cabecera = ($parte ?? 'cabecera') === 'cabecera';
$ventana = array_values(array_unique(array_filter(
    [1, $actual - 2, $actual - 1, $actual, $actual + 1, $actual + 2, $paginas],
    static fn (int $n): bool => $n >= 1 && $n <= $paginas,
)));
sort($ventana);
$desde = $total === 0 ? 0 : (($actual - 1) * $porPagina) + 1;
$hasta = min($total, $actual * $porPagina);
$boton = static function (string $texto, int $destino, bool $inactivo) use ($enlace): string {
    return $inactivo
        ? '<li class="page-item disabled"><span class="page-link">' . $texto . '</span></li>'
        : '<li class="page-item"><a class="page-link" href="' . esc($enlace($destino)) . '">' . $texto . '</a></li>';
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 <?= $cabecera ? 'mb-2' : 'mt-2' ?>">
    <?php if ($cabecera): ?>
        <form method="get" action="<?= esc($accion) ?>" class="mb-0">
            <?php foreach ($criterios as $nombre => $valor): ?><input type="hidden" name="<?= esc((string) $nombre, 'attr') ?>" value="<?= esc((string) $valor, 'attr') ?>"><?php endforeach ?>
            <label for="por-pagina">Mostrar
                <select class="form-select form-select-sm d-inline-block w-auto mx-1" id="por-pagina" name="por_pagina" data-autoenviar>
                    <?php foreach ($opciones as $o): ?><option value="<?= (int) $o ?>"<?= $o === $porPagina ? ' selected' : '' ?>><?= (int) $o ?></option><?php endforeach ?>
                </select>
                registros</label>
            <noscript><button type="submit" class="btn btn-sm btn-outline-secondary ms-1">Aplicar</button></noscript>
        </form>
    <?php else: ?>
        <div role="status">Mostrando <?= number_format($desde, 0, ',', '.') ?> a <?= number_format($hasta, 0, ',', '.') ?> de <?= number_format($total, 0, ',', '.') ?> registros</div>
    <?php endif ?>
    <?php if ($paginas > 1): ?>
        <nav aria-label="<?= $cabecera ? 'Páginas (arriba)' : 'Páginas' ?>">
            <ul class="pagination mb-0">
                <?= $boton('Primera', 1, $actual <= 1) ?>
                <?= $boton('Anterior', max(1, $actual - 1), $actual <= 1) ?>
                <?php $previa = 0; foreach ($ventana as $n): ?>
                    <?php if ($previa !== 0 && $n - $previa > 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif ?>
                    <?php if ($n === $actual): ?>
                        <li class="page-item active"><span class="page-link" aria-current="page"><?= $n ?></span></li>
                    <?php else: ?>
                        <li class="page-item"><a class="page-link" href="<?= esc($enlace($n)) ?>" aria-label="Página <?= $n ?>"><?= $n ?></a></li>
                    <?php endif ?>
                    <?php $previa = $n; ?>
                <?php endforeach ?>
                <?= $boton('Siguiente', min($paginas, $actual + 1), $actual >= $paginas) ?>
                <?= $boton('Última', $paginas, $actual >= $paginas) ?>
            </ul>
        </nav>
    <?php endif ?>
</div>
