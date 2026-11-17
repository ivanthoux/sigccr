<?php
/**
 * CU-11 Registrar Cliente (persona humana o jurídica, A8) y CU-12 Modificar Datos del Cliente.
 *
 * @var CodeIgniter\View\View $this
 * @var array<string, mixed>|null $c
 * @var list<array{id: int, codigo: string, nombre: string, aplica_a: string}> $tipos
 * @var list<array{id: int, nombre: string}> $provincias
 * @var list<array{id: int, nombre: string}> $localidades de la provincia elegida (D-18)
 * @var array<string, array{tope: string, vigente_desde: string}> $escalaMonotributo categorías vigentes (D-26)
 * @var array<string, string>|null $pre datos de un pre-registro verificado del portal (CU-104), propuestos en el alta
 */
use App\Libraries\Clientes\RequisitosDocumentales;
use App\Libraries\Validacion\Campos;
use App\Libraries\Validacion\ValidadorCliente;

$alta = $c === null;
$pre ??= null;
$v = static function (string $k) use ($c, $pre): string {
    $valor = old($k, $c[$k] ?? $pre[$k] ?? '');
    if ($k === 'ingresos_declarados' && old($k) === null && $valor !== '') {
        return str_replace('$ ', '', formato_importe((string) $valor));
    }

    return (string) $valor;
};
$tipo = $alta ? (old('tipo_cliente') ?? 'PersonaHumana') : $c['tipo_cliente'];
$comunicaciones = $alta ? old('acepta_comunicaciones') !== null : (old('version_fila') !== null ? old('acepta_comunicaciones') !== null : (bool) ($c['consentimientos']['ComunicacionesComerciales']['otorgado'] ?? false));
// D-14: en el alta solo es obligatoria la identificación del cliente, según su tipo
$requisitos = RequisitosDocumentales::IDENTIFICACION;
$campo = static fn (string $nombre, string $etiqueta, string $html, string $ayuda = '', string $col = 'col-md-6'): string =>
    "<div class=\"{$col}\"><label for=\"{$nombre}\" class=\"form-label\">{$etiqueta}</label>{$html}" . error_campo($nombre) . ($ayuda !== '' ? "<div class=\"form-text\">{$ayuda}</div>" : '') . '</div>';
$texto = static fn (string $nombre, int $max, string $extra = '') => "<input type=\"text\" class=\"form-control" . campo_invalido($nombre) . "\" id=\"{$nombre}\" name=\"{$nombre}\" maxlength=\"{$max}\" value=\"" . esc($v($nombre), 'attr') . "\" {$extra}>";
$this->extend('layouts/panel');
$this->section('contenido');
?>
<?php if (!$alta): ?><nav aria-label="Ruta" class="mb-2"><a href="<?= site_url('admin/clientes/' . $c['id']) ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= esc($c['denominacion']) ?></a></nav><?php endif ?>
<div class="encabezado">
    <div>
        <h1><?= $alta ? 'Registrar cliente' : 'Modificar cliente' ?></h1>
        <p><?= $alta ? 'El alta no otorga crédito ni habilita el portal: crea la cuenta pendiente de activación y envía la verificación del correo.' : esc($c['denominacion']) ?></p>
    </div>
</div>

<form method="post" action="<?= $alta ? site_url('admin/clientes') : site_url('admin/clientes/' . $c['id'] . '/editar') ?>" enctype="multipart/form-data" class="formulario-cliente" style="max-width: 60rem" novalidate data-tipo="<?= esc($tipo, 'attr') ?>">
    <?= csrf_field() ?>
    <?php if (!$alta): ?><input type="hidden" name="version_fila" value="<?= (int) $c['version_fila'] ?>"><?php endif ?>
    <?php if ($alta && ($pre !== null || old('pre_registro_id') !== null)): ?>
        <input type="hidden" name="pre_registro_id" value="<?= esc((string) old('pre_registro_id', $pre['pre_registro_id'] ?? ''), 'attr') ?>">
        <div class="alert alert-info">Alta desde un pre-registro del portal: los datos los ingresó la persona y su correo ya está verificado. Antes de registrar, verifique su identidad con el documento original y complete el resto de los datos (CU-104).</div>
    <?php endif ?>

    <?php if ($alta): ?>
        <fieldset class="hoja p-4 mb-3">
            <legend class="h5">Tipo de cliente</legend>
            <div class="row g-2">
                <?php foreach (['PersonaHumana' => 'Persona humana', 'PersonaJuridica' => 'Persona jurídica (pyme)'] as $k => $t): ?>
                    <div class="col-sm-6">
                        <input type="radio" class="btn-check" name="tipo_cliente" id="tipo-<?= $k ?>" value="<?= $k ?>"<?= $tipo === $k ? ' checked' : '' ?>>
                        <label class="btn btn-outline-secondary w-100 text-start" for="tipo-<?= $k ?>"><?= $t ?></label>
                    </div>
                <?php endforeach ?>
            </div>
        </fieldset>
    <?php else: ?>
        <input type="hidden" name="tipo_cliente" value="<?= esc($tipo, 'attr') ?>">
    <?php endif ?>

    <fieldset class="hoja p-4 mb-3">
        <legend class="h5">Identificación</legend>
        <div class="row g-3" data-solo="PersonaHumana">
            <?= $campo('nombre', 'Nombre', $texto('nombre', 80)) ?>
            <?= $campo('apellido', 'Apellido', $texto('apellido', 80)) ?>
            <div class="col-md-3">
                <label for="tipo_documento" class="form-label">Tipo de documento</label>
                <select class="form-select<?= campo_invalido('tipo_documento') ?>" id="tipo_documento" name="tipo_documento">
                    <?php foreach (Campos::TIPOS_DOCUMENTO as $t): ?><option<?= $v('tipo_documento') === $t ? ' selected' : '' ?>><?= $t ?></option><?php endforeach ?>
                </select>
                <?= error_campo('tipo_documento') ?>
            </div>
            <?= $campo('nro_documento', 'Número de documento', $texto('nro_documento', 15, 'inputmode="numeric"'), 'Sin puntos.', 'col-md-4') ?>
            <?= $campo('fecha_nacimiento', 'Fecha de nacimiento', '<input type="date" class="form-control' . campo_invalido('fecha_nacimiento') . '" id="fecha_nacimiento" name="fecha_nacimiento" value="' . esc($v('fecha_nacimiento'), 'attr') . '">', '', 'col-md-5') ?>
            <div class="col-md-4">
                <label for="estado_civil" class="form-label">Estado civil <span class="text-body-secondary fw-normal">(opcional)</span></label>
                <select class="form-select<?= campo_invalido('estado_civil') ?>" id="estado_civil" name="estado_civil">
                    <option value="">Sin informar</option>
                    <?php foreach (ValidadorCliente::ESTADOS_CIVILES as $k => $t): ?><option value="<?= $k ?>"<?= $v('estado_civil') === $k ? ' selected' : '' ?>><?= $t ?></option><?php endforeach ?>
                </select>
                <?= error_campo('estado_civil') ?>
            </div>
            <?= $campo('nacionalidad', 'Nacionalidad', $texto('nacionalidad', 60, 'list="nacionalidades" autocomplete="off"'), 'Exigida por la Resolución UIF 200/2024 (RN-80).', 'col-md-4') ?>
            <datalist id="nacionalidades"><option value="Argentina"><option value="Boliviana"><option value="Brasileña"><option value="Chilena"><option value="Paraguaya"><option value="Peruana"><option value="Uruguaya"><option value="Venezolana"></datalist>
        </div>
        <div class="row g-3" data-solo="PersonaJuridica">
            <?= $campo('razon_social', 'Razón social', $texto('razon_social', 200), '', 'col-md-8') ?>
            <?= $campo('fecha_constitucion', 'Fecha de constitución', '<input type="date" class="form-control' . campo_invalido('fecha_constitucion') . '" id="fecha_constitucion" name="fecha_constitucion" value="' . esc($v('fecha_constitucion'), 'attr') . '">', '', 'col-md-4') ?>
            <?= $campo('actividad_principal', 'Actividad principal', $texto('actividad_principal', 150), '', 'col-12') ?>
        </div>
        <div class="row g-3 mt-0">
            <?= $campo('cuit_cuil', '<span data-solo="PersonaHumana">CUIT/CUIL <span class="text-body-secondary fw-normal">(opcional en el alta; obligatorio para solicitar crédito)</span></span><span data-solo="PersonaJuridica">CUIT</span>', $texto('cuit_cuil', 13, 'inputmode="numeric"'), '11 dígitos, con o sin guiones.', 'col-md-6') ?>
            <?php if ($alta): ?>
                <div class="col-md-6 d-flex flex-column justify-content-end" data-solo="PersonaHumana">
                    <button type="button" class="btn btn-outline-secondary align-self-start" data-padron="<?= site_url('admin/clientes/padron') ?>"><i class="bi bi-search me-1" aria-hidden="true"></i>Consultar el padrón de ARCA</button>
                    <div class="form-text" data-padron-resultado role="status">Propone el nombre, el documento y el domicilio fiscal del CUIL para que los confirme (A9).</div>
                </div>
            <?php endif ?>
        </div>
    </fieldset>

    <fieldset class="hoja p-4 mb-3" data-solo="PersonaJuridica">
        <legend class="h5">Representante</legend>
        <div class="row g-3">
            <?= $campo('representante_nombre', 'Nombre y apellido', $texto('representante_nombre', 150), '', 'col-md-6') ?>
            <?= $campo('representante_documento', 'DNI', $texto('representante_documento', 15, 'inputmode="numeric"'), '', 'col-md-3') ?>
            <?= $campo('representante_cargo', 'Cargo', $texto('representante_cargo', 80), '', 'col-md-3') ?>
        </div>
    </fieldset>

    <fieldset class="hoja p-4 mb-3">
        <legend class="h5">Contacto</legend>
        <div class="row g-3">
            <?= $campo('correo', 'Correo electrónico', '<input type="email" class="form-control' . campo_invalido('correo') . '" id="correo" name="correo" maxlength="254" value="' . esc($v('correo'), 'attr') . '" data-original="' . esc($c['correo'] ?? '', 'attr') . '">', $alta ? 'Recibirá un enlace para verificarlo.' : 'Si lo cambia, el correo nuevo se verifica con un enlace; hasta entonces sigue vigente el actual.') ?>
            <?= $campo('telefono', 'Teléfono <span class="text-body-secondary fw-normal">(opcional)</span>', $texto('telefono', 30, 'inputmode="tel"')) ?>
            <?php if (!$alta): ?>
                <div class="col-12" data-evidencia hidden>
                    <label for="evidencia_identidad" class="form-label">Comprobación de identidad</label>
                    <textarea class="form-control<?= campo_invalido('evidencia_identidad') ?>" id="evidencia_identidad" name="evidencia_identidad" rows="2" maxlength="500" placeholder="Por ejemplo: DNI original exhibido en sucursal, coincide con el registrado."><?= esc(old('evidencia_identidad')) ?></textarea>
                    <?= error_campo('evidencia_identidad') ?>
                    <div class="form-text">El cambio de correo en sucursal exige comprobar presencialmente el documento y la identidad (RN-58).</div>
                </div>
            <?php endif ?>
            <div class="col-12"><h2 class="h6 mb-0 mt-1"><span data-solo="PersonaHumana">Domicilio</span><span data-solo="PersonaJuridica">Domicilio legal</span></h2></div>
            <?php $provincia = (string) $v('provincia_id'); $localidad = (string) $v('localidad_id'); ?>
            <div class="col-md-4">
                <label for="provincia_id" class="form-label">Provincia</label>
                <select class="form-select<?= campo_invalido('provincia_id') ?>" id="provincia_id" name="provincia_id" required data-localidades="<?= esc(site_url('admin/clientes/localidades'), 'attr') ?>">
                    <option value="">Seleccione…</option>
                    <?php foreach ($provincias as $p): ?><option value="<?= $p['id'] ?>"<?= $provincia === (string) $p['id'] ? ' selected' : '' ?>><?= esc($p['nombre']) ?></option><?php endforeach ?>
                </select>
                <?= error_campo('provincia_id') ?>
            </div>
            <div class="col-md-5">
                <label for="localidad_id" class="form-label">Localidad</label>
                <select class="form-select<?= campo_invalido('localidad_id') ?>" id="localidad_id" name="localidad_id" required<?= $localidades === [] ? ' disabled' : '' ?>>
                    <option value=""><?= $localidades === [] ? 'Elija primero la provincia' : 'Seleccione…' ?></option>
                    <?php foreach ($localidades as $l): ?><option value="<?= $l['id'] ?>"<?= $localidad === (string) $l['id'] ? ' selected' : '' ?>><?= esc($l['nombre']) ?></option><?php endforeach ?>
                </select>
                <?= error_campo('localidad_id') ?>
            </div>
            <?= $campo('codigo_postal', 'Código postal <span class="text-body-secondary fw-normal">(opcional)</span>', $texto('codigo_postal', 8, 'autocomplete="postal-code"'), '', 'col-md-3') ?>
            <?= $campo('domicilio', 'Calle y número', $texto('domicilio', 200, 'autocomplete="street-address"'), 'Con piso, departamento o referencias si corresponde.', 'col-12') ?>
        </div>
    </fieldset>

    <fieldset class="hoja p-4 mb-3">
        <legend class="h5">Datos laborales y económicos</legend>
        <div class="row g-3">
            <div class="col-md-6" data-solo="PersonaHumana"><?= str_replace('col-md-6', 'p-0', $campo('ocupacion', 'Actividad laboral o profesional principal', $texto('ocupacion', 100), 'Por ejemplo: empleado administrativo, docente, comerciante (RN-80).')) ?></div>
            <div class="col-md-6" data-solo="PersonaHumana"><?= str_replace('col-md-6', 'p-0', $campo('empleador', 'Empleador o actividad <span class="text-body-secondary fw-normal">(opcional)</span>', $texto('empleador', 150))) ?></div>
            <div class="col-md-6" data-solo="PersonaHumana">
                <label for="categoria_ingresos" class="form-label">Categoría de ingresos</label>
                <select class="form-select<?= campo_invalido('categoria_ingresos') ?>" id="categoria_ingresos" name="categoria_ingresos">
                    <option value="">Seleccione</option>
                    <?php foreach (RequisitosDocumentales::CATEGORIAS['PersonaHumana'] as $k => $t): ?><option value="<?= $k ?>"<?= $v('categoria_ingresos') === $k ? ' selected' : '' ?>><?= $t ?></option><?php endforeach ?>
                </select>
                <?= error_campo('categoria_ingresos') ?>
            </div>
            <div class="col-md-6" data-solo="PersonaHumana" data-si-categoria="Monotributista"<?= $v('categoria_ingresos') === 'Monotributista' ? '' : ' hidden' ?>>
                <label for="categoria_monotributo" class="form-label">Categoría del monotributo</label>
                <select class="form-select<?= campo_invalido('categoria_monotributo') ?>" id="categoria_monotributo" name="categoria_monotributo">
                    <option value="">Seleccione</option>
                    <?php foreach ($escalaMonotributo as $k => $e): ?><option value="<?= $k ?>"<?= trim($v('categoria_monotributo')) === $k ? ' selected' : '' ?>><?= $k ?> · hasta <?= esc(formato_importe($e['tope'])) ?> anuales</option><?php endforeach ?>
                </select>
                <?= error_campo('categoria_monotributo') ?>
                <div class="form-text">Topes de ingresos brutos anuales vigentes<?= $escalaMonotributo === [] ? '' : ' desde el ' . esc(formato_fecha(reset($escalaMonotributo)['vigente_desde'])) ?> (ARCA).</div>
            </div>
            <?= $campo('ingresos_declarados', '<span data-solo="PersonaHumana">Ingresos mensuales declarados ($)</span><span data-solo="PersonaJuridica">Ingresos netos mensuales promedio ($)</span>', $texto('ingresos_declarados', 25, 'inputmode="decimal"'), 'Por ejemplo 850.000,00.') ?>
        </div>
    </fieldset>

    <?php if ($alta): ?>
        <fieldset class="hoja p-4 mb-3" id="documentacion">
            <legend class="h5">Documentación respaldatoria</legend>
            <?php if (!empty(session('errores'))): ?>
                <div class="alert alert-warning py-2 small" role="status">Si había elegido archivos, vuelva a adjuntarlos: el navegador no los conserva cuando el formulario vuelve con observaciones.</div>
            <?php endif ?>
            <p class="text-body-secondary small">PDF, JPG o PNG de hasta 5 MB cada uno. Solo es obligatoria la identificación del cliente. La documentación de ingresos puede incorporarse ahora o más adelante: la exige cada línea de crédito al registrar la solicitud (RN-11). Incorporar un documento no equivale a verificarlo.</p>
            <div class="row g-3">
                <?php foreach ($tipos as $t): ?>
                    <div class="col-md-6" data-aplica="<?= esc($t['aplica_a'], 'attr') ?>">
                        <label for="doc-<?= esc($t['codigo'], 'attr') ?>" class="form-label"><?= esc($t['nombre']) ?> <span class="badge text-bg-light border" data-obligatorio="<?= esc($t['codigo'], 'attr') ?>" hidden>Obligatorio</span></label>
                        <input type="file" class="form-control<?= campo_invalido('doc_' . $t['codigo']) ?>" id="doc-<?= esc($t['codigo'], 'attr') ?>" name="documentos[<?= esc($t['codigo'], 'attr') ?>][]" accept=".pdf,.jpg,.jpeg,.png" multiple>
                        <?= error_campo('doc_' . $t['codigo']) ?>
                    </div>
                <?php endforeach ?>
            </div>
        </fieldset>
    <?php endif ?>

    <fieldset class="hoja p-4 mb-3">
        <legend class="h5">Persona expuesta políticamente</legend>
        <p class="small">Declaración jurada del cliente<span data-solo="PersonaJuridica"> sobre sus representantes y beneficiarios</span>: ¿ejerce o ejerció en los últimos años una función pública relevante, o es familiar o allegado de quien la ejerce? (Resolución UIF 200/2024, RN-80).</p>
        <?php $pep = old('persona_expuesta') ?? ($c === null || $c['persona_expuesta'] === null ? '' : ((int) $c['persona_expuesta'] === 1 ? 'Si' : 'No')); ?>
        <div class="<?= campo_invalido('persona_expuesta') !== '' ? 'is-invalid' : '' ?>">
            <div class="form-check form-check-inline">
                <input class="form-check-input<?= campo_invalido('persona_expuesta') ?>" type="radio" name="persona_expuesta" id="pep-no" value="No"<?= $pep === 'No' ? ' checked' : '' ?>>
                <label class="form-check-label" for="pep-no">No es persona expuesta políticamente</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input<?= campo_invalido('persona_expuesta') ?>" type="radio" name="persona_expuesta" id="pep-si" value="Si"<?= $pep === 'Si' ? ' checked' : '' ?>>
                <label class="form-check-label" for="pep-si">Es persona expuesta políticamente</label>
            </div>
        </div>
        <?= error_campo('persona_expuesta') ?>
        <p class="small text-body-secondary mt-2 mb-0">Si se declara expuesta, el Gerente recibe una alerta para aplicar la debida diligencia reforzada.<?= $c !== null && $c['persona_expuesta'] === null ? ' Este cliente se registró antes de la regla: complete la declaración.' : '' ?></p>
    </fieldset>

    <fieldset class="hoja p-4 mb-3">
        <legend class="h5">Tratamiento de datos</legend>
        <?php if ($alta): ?>
            <div class="form-check mb-2">
                <input class="form-check-input<?= campo_invalido('acepta_politica') ?>" type="checkbox" id="acepta_politica" name="acepta_politica" value="1"<?= old('acepta_politica') !== null ? ' checked' : '' ?>>
                <label class="form-check-label" for="acepta_politica">El cliente aceptó la política de tratamiento de datos personales (obligatorio)</label>
                <?= error_campo('acepta_politica') ?>
            </div>
        <?php endif ?>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="acepta_comunicaciones" name="acepta_comunicaciones" value="1"<?= $comunicaciones ? ' checked' : '' ?>>
            <label class="form-check-label" for="acepta_comunicaciones">El cliente acepta recibir comunicaciones comerciales (opcional, puede revocarlo en cualquier momento)</label>
        </div>
        <p class="small text-body-secondary mt-2 mb-0">Se registra con la fecha de hoy y el medio "Sucursal" (RN-64).</p>
    </fieldset>

    <div class="d-flex gap-2 justify-content-end">
        <a href="<?= $alta ? url_to('clientes.localizar') : site_url('admin/clientes/' . $c['id']) ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= $alta ? 'Registrar cliente' : 'Guardar cambios' ?></button>
    </div>
</form>
<?php $this->endSection() ?>

<?php $this->section('scripts') ?>
<script type="application/json" id="requisitos-documentales"><?= json_encode($requisitos, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= recurso('assets/sigccr/clientes.js') ?>"></script>
<?php $this->endSection() ?>
