<?php
/**
 * CU-45 Consultar Perfil del Cliente, pasos 2 a 6 (A1, A2, A5): datos de identificación, contacto, laborales y
 * económicos, y preferencias de comunicación. La corrección se pide en la sucursal (CU-12).
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed> $c
 */
use App\Libraries\Clientes\RequisitosDocumentales;

$juridica = $c['tipo_cliente'] === 'PersonaJuridica';
$com = $c['consentimientos']['ComunicacionesComerciales'] ?? null;
$categoria = RequisitosDocumentales::CATEGORIAS[$c['tipo_cliente']][$c['categoria_ingresos']] ?? (string) $c['categoria_ingresos'];
$this->extend('layouts/portal');
$this->section('contenido');
?>
<nav aria-label="Ruta" class="mb-2"><a href="<?= url_to('portal.inicio') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Inicio</a></nav>
<div class="encabezado">
    <div>
        <h1>Mi perfil</h1>
        <p>Sus datos registrados en la entidad. La consulta no modifica ningún dato.</p>
    </div>
</div>
<div class="row g-3">
    <section class="col-lg-6" aria-labelledby="t-identificacion">
        <div class="hoja p-4 h-100">
            <h2 class="h5" id="t-identificacion">Identificación</h2>
            <dl class="row mb-0 small">
                <?php if ($juridica): ?>
                    <dt class="col-5">Razón social</dt><dd class="col-7"><?= esc((string) $c['razon_social']) ?></dd>
                    <dt class="col-5">Representante</dt><dd class="col-7"><?= esc((string) $c['representante_nombre']) ?> (<?= esc((string) $c['representante_cargo']) ?>)</dd>
                <?php else: ?>
                    <dt class="col-5">Nombre y apellido</dt><dd class="col-7"><?= esc($c['nombre'] . ' ' . $c['apellido']) ?></dd>
                    <dt class="col-5">Documento</dt><dd class="col-7"><?= esc($c['tipo_documento'] . ' ' . $c['nro_documento']) ?></dd>
                    <dt class="col-5">Fecha de nacimiento</dt><dd class="col-7"><?= esc(formato_fecha((string) $c['fecha_nacimiento'])) ?></dd>
                    <dt class="col-5">Estado civil</dt><dd class="col-7"><?= esc((string) ($c['estado_civil'] ?? '—')) ?></dd>
                <?php endif ?>
                <dt class="col-5">CUIT/CUIL</dt><dd class="col-7"><?= $c['cuit_cuil'] !== null ? esc(formato_cuit($c['cuit_cuil'])) : 'No informado' ?></dd>
            </dl>
        </div>
    </section>
    <section class="col-lg-6" aria-labelledby="t-contacto">
        <div class="hoja p-4 h-100">
            <h2 class="h5" id="t-contacto">Contacto</h2>
            <dl class="row mb-2 small">
                <dt class="col-5">Correo electrónico</dt>
                <dd class="col-7"><?= esc((string) ($c['correo_cuenta'] ?? $c['correo'])) ?>
                    <?= (int) ($c['correo_verificado'] ?? 0) === 1 ? '<span class="badge text-bg-success ms-1">Verificado</span>' : '<span class="badge text-bg-warning ms-1">Sin verificar</span>' ?>
                    <?php if ($c['correo_pendiente'] !== null): ?><div class="text-body-secondary">Cambio pendiente de verificación: <?= esc($c['correo_pendiente']) ?></div><?php endif ?>
                </dd>
                <dt class="col-5">Teléfono</dt><dd class="col-7"><?= esc((string) ($c['telefono'] ?? '—')) ?></dd>
                <dt class="col-5">Domicilio</dt><dd class="col-7"><?= esc($c['domicilio'] . ', ' . $c['localidad'] . ', ' . $c['provincia'] . ' (' . $c['codigo_postal'] . ')') ?></dd>
            </dl>
            <a href="<?= url_to('portal.cambiar-correo') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-envelope me-1" aria-hidden="true"></i>Cambiar correo electrónico</a>
            <a href="<?= url_to('portal.cambiar-contrasena') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-key me-1" aria-hidden="true"></i>Cambiar contraseña</a>
        </div>
    </section>
    <section class="col-lg-6" aria-labelledby="t-laboral">
        <div class="hoja p-4 h-100">
            <h2 class="h5" id="t-laboral">Datos laborales y económicos</h2>
            <dl class="row mb-0 small">
                <?php if ($juridica): ?>
                    <dt class="col-5">Actividad principal</dt><dd class="col-7"><?= esc((string) $c['actividad_principal']) ?></dd>
                <?php else: ?>
                    <dt class="col-5">Ocupación</dt><dd class="col-7"><?= esc((string) ($c['ocupacion'] ?? '—')) ?></dd>
                    <dt class="col-5">Empleador</dt><dd class="col-7"><?= esc((string) ($c['empleador'] ?? '—')) ?></dd>
                <?php endif ?>
                <dt class="col-5">Categoría de ingresos</dt><dd class="col-7"><?= esc($categoria) ?></dd>
                <dt class="col-5">Ingresos declarados</dt><dd class="col-7"><?= esc(formato_importe((string) $c['ingresos_declarados'])) ?> por mes</dd>
            </dl>
        </div>
    </section>
    <section class="col-lg-6" aria-labelledby="t-comunicaciones">
        <div class="hoja p-4 h-100">
            <h2 class="h5" id="t-comunicaciones">Comunicaciones comerciales</h2>
            <p class="small mb-2"><?= $com !== null && (int) $com['otorgado'] === 1 ? 'Acepta recibir ofertas y novedades comerciales.' : 'No recibe ofertas ni novedades comerciales.' ?>
                <?php if ($com !== null): ?><span class="text-body-secondary">Último cambio: <?= esc(formato_fecha_hora((string) $com['fecha'])) ?> (<?= $com['medio'] === 'Portal' ? 'portal' : 'sucursal' ?>).</span><?php endif ?></p>
            <a href="<?= url_to('portal.preferencias') ?>" class="btn btn-sm btn-outline-secondary">Modificar preferencias de comunicación</a>
        </div>
    </section>
</div>
<details class="hoja p-4 mt-3">
    <summary class="fw-medium">Solicitar corrección de datos</summary>
    <p class="small mt-2 mb-1">Para corregir sus datos personales, laborales o económicos, acérquese a la sucursal con su documento de identidad: un analista de crédito los actualizará y dejará constancia del cambio.</p>
    <p class="small mb-1">Si solo quiere cambiar su correo electrónico, puede hacerlo desde «Cambiar correo electrónico» confirmando desde el correo actual y el nuevo.</p>
    <p class="small mb-1">Si perdió el acceso a su correo actual, el cambio también se hace en la sucursal, presentando su documento.</p>
    <p class="small mb-0">También puede pedir la corrección, el acceso o la supresión de sus datos desde <a href="<?= url_to('portal.derechos') ?>">Mis derechos sobre mis datos</a>.</p>
</details>
<p class="small mt-3 mb-0"><a href="<?= url_to('portal.derechos') ?>"><i class="bi bi-shield-check me-1" aria-hidden="true"></i>Ejercer mis derechos sobre mis datos</a></p>
<?php $this->endSection() ?>
