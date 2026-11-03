<?php
/**
 * Datos del cliente que acompañan la simulación y la comparación de líneas (CU-21, CU-99, D-23).
 *
 * @var array<string, mixed> $cliente fila de SimulacionService::cliente()
 * @var array<string, mixed> $perfil  SimulacionService::perfil()
 */
use App\Libraries\Clientes\RequisitosDocumentales;
use App\Libraries\Finanzas\Decimal;

$humana = $cliente['tipo_cliente'] === 'PersonaHumana';
$categoria = RequisitosDocumentales::CATEGORIAS[$cliente['tipo_cliente']][$cliente['categoria_ingresos']] ?? (string) $cliente['categoria_ingresos'];
$capacidad = (string) $perfil['capacidad'];
$sinCapacidad = !Decimal::of($capacidad)->isPositive();
$dato = static fn (string $etiqueta, string $valor): string => '<div class="col-6 col-md-4 col-xl-2"><div class="small text-body-secondary">' . $etiqueta . '</div><div class="fw-medium">' . $valor . '</div></div>';
?>
<section class="hoja p-3 mb-3" aria-labelledby="titulo-perfil">
    <h2 class="h6 mb-2" id="titulo-perfil">Datos del cliente</h2>
    <div class="row g-3">
        <?= $dato($humana ? 'Documento' : 'CUIT', esc($humana ? trim(($cliente['tipo_documento'] ?? '') . ' ' . ($cliente['nro_documento'] ?? '')) : (string) $cliente['cuit_cuil'])) ?>
        <?= $humana
            ? $dato('Edad', $perfil['edad'] === null ? 'Sin informar' : (int) $perfil['edad'] . ' años')
            : $dato('Antigüedad de la empresa', $perfil['antiguedad_empresa'] === null ? 'Sin informar' : intdiv((int) $perfil['antiguedad_empresa'], 12) . ' años y ' . ((int) $perfil['antiguedad_empresa'] % 12) . ' meses') ?>
        <?= $dato('Situación laboral', esc($categoria)) ?>
        <?= $dato($humana ? 'Empleador o actividad' : 'Actividad principal', esc((string) (($humana ? ($cliente['empleador'] ?? $cliente['ocupacion'] ?? null) : ($cliente['actividad_principal'] ?? null)) ?? 'Sin informar'))) ?>
        <?= $dato($humana ? 'Ingresos mensuales' : 'Ingresos netos mensuales', esc(formato_importe($perfil['ingresos']))) ?>
        <?= $dato('Cuotas vigentes y comprometidas', esc(formato_importe($perfil['obligaciones']))) ?>
    </div>
    <p class="mb-0 mt-3">
        Capacidad de pago mensual: <strong class="<?= $sinCapacidad ? 'text-danger' : '' ?>"><?= esc(formato_importe($capacidad)) ?></strong>
        <span class="small text-body-secondary">(<?= esc(formato_porcentaje($perfil['afectacion'])) ?> de los ingresos menos las cuotas vigentes y comprometidas<?= (int) $perfil['solicitudes_en_tramite'] > 0 ? '; tiene ' . (int) $perfil['solicitudes_en_tramite'] . ' solicitud(es) en trámite' : '' ?>)</span>
    </p>
</section>
