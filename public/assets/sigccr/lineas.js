/* Formulario de líneas (CU-16, CU-17): secciones según los tipos de cliente admitidos y documentación visible solo
   para las categorías marcadas. La validación definitiva la hace el servidor (RN-14). */
(function () {
    'use strict';
    const form = document.querySelector('form.formulario-linea');
    if (!form) {
        return;
    }
    const humana = form.querySelector('#admite_persona_humana');
    const juridica = form.querySelector('#admite_persona_juridica');

    function actualizar() {
        const admitidos = { PersonaHumana: humana.checked, PersonaJuridica: juridica.checked };
        form.querySelectorAll('[data-tipo]').forEach((el) => { el.hidden = !admitidos[el.dataset.tipo]; });
        form.querySelectorAll('[data-categoria]').forEach((cb) => {
            const lista = form.querySelector('[data-requisitos="' + cb.value + '"]');
            if (lista) {
                lista.hidden = !cb.checked;
            }
        });
    }

    // D-28: importe fijo o porcentaje del capital
    function actualizarGasto() {
        const elegido = form.querySelector('[data-tipo-gasto]:checked');
        form.querySelectorAll('[data-si-gasto]').forEach((el) => { el.hidden = !elegido || el.dataset.siGasto !== elegido.value; });
    }
    form.querySelectorAll('[data-tipo-gasto]').forEach((el) => el.addEventListener('change', actualizarGasto));

    form.querySelectorAll('#admite_persona_humana, #admite_persona_juridica, [data-categoria]').forEach((el) => el.addEventListener('change', actualizar));
    actualizar();
})();
