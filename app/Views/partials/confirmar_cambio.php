<?php
/**
 * Confirmación de una nueva versión de condiciones o de parámetros cuando hay solicitudes en trámite con la versión
 * anterior (D-24). Se muestra solo después de que el servidor la pide: el aviso llega como error del campo
 * confirmar_cambio y el usuario reenvía el formulario con la casilla marcada.
 */
if (campo_invalido('confirmar_cambio') === '' && old('confirmar_cambio') === null) {
    return;
}
?>
<div class="alert alert-warning mt-3 mb-0" role="alert">
    <p class="mb-2"><?= esc((string) ((session('errores') ?? [])['confirmar_cambio'] ?? 'Hay solicitudes en trámite con la versión anterior.')) ?></p>
    <div class="form-check">
        <input class="form-check-input<?= campo_invalido('confirmar_cambio') !== '' ? ' is-invalid' : '' ?>" type="checkbox" id="confirmar_cambio" name="confirmar_cambio" value="1"<?= old('confirmar_cambio') ? ' checked' : '' ?>>
        <label class="form-check-label fw-medium" for="confirmar_cambio">Confirmo el cambio: las solicitudes en trámite conservan la versión con la que se registraron y las nuevas usarán esta.</label>
    </div>
</div>
