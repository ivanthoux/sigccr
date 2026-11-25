<?php
/**
 * CU-65 a CU-71: criterios (paso 2, A2) y reporte generado (pasos 3 a 8). Impresión y PDF con la vista de impresión
 * del navegador; exportación a Excel con formato=xlsx (D-44).
 *
 * @var CodeIgniter\View\View $this
 * @var string $clave reporte
 * @var array<string, string> $f criterios
 * @var array{lineas: list<array<string, mixed>>, analistas: list<array<string, mixed>>, cajeros: list<array<string, mixed>>, cajas: list<array<string, mixed>>} $opciones
 * @var array<string, mixed>|null $r
 */
use App\Libraries\Reportes\Indicadores;
use App\Libraries\Reportes\Periodo;
use App\Libraries\Solicitudes\EstadosSolicitud;
use App\Libraries\Validacion\ValidadorParametrosCampanas as C;
use App\Services\Cartera\RefinanciacionService;
use App\Services\Reportes\ReporteService;

$v = static fn (string $k): string => (string) ($f[$k] ?? '');
$select = static function (string $k, string $etiqueta, array $opc, string $todos = 'Todos', string $col = 'col-6 col-md-3') use ($v): string {
    $h = "<div class=\"{$col}\"><label for=\"{$k}\" class=\"form-label small mb-1\">{$etiqueta}</label><select id=\"{$k}\" name=\"{$k}\" class=\"form-select form-select-sm\"><option value=\"\">{$todos}</option>";
    foreach ($opc as $valor => $texto) {
        $h .= '<option value="' . esc((string) $valor, 'attr') . '"' . ($v($k) === (string) $valor ? ' selected' : '') . '>' . esc((string) $texto) . '</option>';
    }

    return $h . '</select></div>';
};
$lista = static fn (array $filas): array => array_column($filas, 'nombre', 'id');
$texto = static fn (string $k, string $etiqueta, string $ayuda = '', string $col = 'col-6 col-md-3'): string => "<div class=\"{$col}\"><label for=\"{$k}\" class=\"form-label small mb-1\">{$etiqueta}</label>"
    . "<input type=\"text\" id=\"{$k}\" name=\"{$k}\" class=\"form-control form-control-sm\" value=\"" . esc($v($k), 'attr') . '"' . ($ayuda !== '' ? ' placeholder="' . esc($ayuda, 'attr') . '"' : '') . '></div>';
$fecha = static fn (string $k, string $etiqueta): string => "<div class=\"col-6 col-md-2\"><label for=\"{$k}\" class=\"form-label small mb-1\">{$etiqueta}</label>"
    . "<input type=\"date\" id=\"{$k}\" name=\"{$k}\" class=\"form-control form-control-sm" . campo_invalido($k) . '" value="' . esc($v($k), 'attr') . '">' . error_campo($k) . '</div>';
$proyeccion = $clave === 'proyeccion';
$ruta = url_to(ReporteService::REPORTES[$clave][2]);
$this->extend('layouts/panel');
$this->section('contenido');
?>
<div class="encabezado d-print-none">
    <div>
        <h1><?= esc($titulo) ?></h1>
        <p><?= $proyeccion ? 'Proyección para un período futuro con los parámetros de campañas vigentes. Los ingresos proyectados no son dinero recibido (RN-39).' : 'Los saldos son a la fecha de generación; el período rige los movimientos y su evolución.' ?></p>
    </div>
    <a href="<?= url_to('reportes.index') ?>" class="btn btn-outline-secondary">Reportes</a>
</div>
<form method="get" action="<?= $ruta ?>" class="hoja p-3 mb-3 row g-2 align-items-end d-print-none" novalidate>
    <input type="hidden" name="generar" value="1">
    <?php if ($proyeccion): ?>
        <input type="hidden" name="tipo" value="rango">
    <?php else: ?>
        <?= $select('tipo', 'Período', Periodo::TIPOS, 'Mes', 'col-6 col-md-2') ?>
        <?= $fecha('fecha', 'Fecha de referencia') ?>
    <?php endif ?>
    <?= $fecha('desde', $proyeccion ? 'Desde' : 'Rango: desde') ?>
    <?= $fecha('hasta', $proyeccion ? 'Hasta' : 'Rango: hasta') ?>
    <?= $select('linea', 'Línea de crédito', $lista($opciones['lineas']), 'Todas') ?>
    <?php if ($clave === 'cartera'): ?>
        <?= $select('estado', 'Estado del crédito', ['Activo' => 'Activo', 'EnMora' => 'En mora', 'Cancelado' => 'Cancelado', 'Refinanciado' => 'Refinanciado', 'Revocado' => 'Revocado']) ?>
        <?= $select('riesgo', 'Categoría de riesgo', ['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'E' => 'E'], 'Todas', 'col-6 col-md-2') ?>
    <?php elseif ($clave === 'morosidad'): ?>
        <?= $select('antiguedad', 'Antigüedad de la mora', Indicadores::TRAMOS) ?>
        <?= $select('riesgo', 'Categoría de riesgo', ['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'E' => 'E'], 'Todas', 'col-6 col-md-2') ?>
    <?php elseif ($clave === 'cobranzas'): ?>
        <?= $select('caja', 'Caja', $lista($opciones['cajas']), 'Todas') ?>
        <?= $select('cajero', 'Cajero', $lista($opciones['cajeros'])) ?>
        <?= $select('medio', 'Medio de pago', ReporteService::medios()) ?>
    <?php elseif ($clave === 'solicitudes'): ?>
        <?= $select('estado', 'Estado', EstadosSolicitud::ETIQUETAS) ?>
        <?= $select('analista', 'Analista de Crédito', $lista($opciones['analistas']) + ['portal' => 'Portal (ofertas)']) ?>
    <?php elseif ($clave === 'refinanciaciones'): ?>
        <?= $select('estado', 'Estado', RefinanciacionService::ESTADOS) ?>
        <?= $select('analista', 'Analista de Crédito', $lista($opciones['analistas'])) ?>
    <?php elseif ($clave === 'proyeccion'): ?>
        <?= $texto('campana', 'Campaña activa (N.º)', 'Todas', 'col-6 col-md-2') ?>
    <?php elseif ($clave === 'campanas'): ?>
        <?= $select('estado', 'Estado', ['Activa' => 'Activa', 'Finalizada' => 'Finalizada'], 'Todos', 'col-6 col-md-2') ?>
        <?= $select('canal', 'Canal', C::CANALES, 'Todos', 'col-6 col-md-2') ?>
        <?= $select('categoria', 'Categoría de ingresos', C::categorias(), 'Todas') ?>
    <?php endif ?>
    <?php if (in_array($clave, ['cartera', 'morosidad', 'cobranzas', 'solicitudes', 'refinanciaciones'], true)): ?>
        <?= $texto('cliente', 'Cliente', 'Nombre o razón social') ?>
    <?php endif ?>
    <p class="col-12 order-last form-text mt-1 mb-0"><?= $proyeccion ? 'Sin fechas, se proyecta desde hoy por el período configurado en los parámetros de campañas.' : 'Día, semana, mes o trimestre que contiene la fecha de referencia (hoy si se deja vacía); el rango personalizado usa las fechas «desde» y «hasta».' ?></p>
    <?= acciones_filtro(limpiar: $ruta, texto: 'Generar reporte', icono: 'bi-bar-chart') ?>
</form>
<?php if ($r !== null): ?>
    <?php $acciones = '<span class="d-flex gap-2 d-print-none"><button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir o PDF</button>'
        . '<a class="btn btn-sm btn-outline-success" href="' . $ruta . '?' . esc(http_build_query(array_filter($f, static fn ($x) => $x !== '') + ['generar' => 1, 'formato' => 'xlsx'])) . '"><i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i>Exportar a Excel</a></span>'; ?>
    <?= view('reportes/_resultado', ['r' => $r, 'acciones' => $acciones]) ?>
<?php endif ?>
<?php $this->endSection() ?>
