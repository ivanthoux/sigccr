/* Cobro en ventanilla (CU-33): total de las cuotas seleccionadas y propuesta del importe del primer medio. La
   validación definitiva (selección consecutiva, importe y medios) la hace el servidor (RN-29, RN-77). */
(function () {
    'use strict';
    const form = document.querySelector('form.formulario-cobro');
    if (!form) {
        return;
    }
    const total = form.querySelector('[data-cobro-total]');
    const importe = form.querySelector('#importe');
    const primero = form.querySelector('[data-cobro-primer-medio]');
    const formato = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function actualizar() {
        let centavos = 0;
        form.querySelectorAll('[data-cobro-cuota]:checked').forEach((cb) => {
            centavos += Math.round(parseFloat(cb.dataset.total) * 100);
        });
        const texto = formato.format(centavos / 100);
        total.textContent = '$ ' + texto;
        importe.placeholder = centavos > 0 ? texto : '';
        if (primero && !primero.dataset.editado) {
            primero.value = centavos > 0 && importe.value.trim() === '' ? texto : primero.value;
        }
    }
    if (primero) {
        primero.addEventListener('input', () => { primero.dataset.editado = '1'; });
    }
    form.querySelectorAll('[data-cobro-cuota]').forEach((cb) => cb.addEventListener('change', actualizar));
    actualizar();
}());
