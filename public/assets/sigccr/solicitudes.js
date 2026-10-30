/* CU-23: el motivo de cada documento se habilita solo si no está conforme, y los campos del resultado elegido
   (correcciones o motivo de rechazo) se muestran según la opción. El servidor valida todo igualmente. */
(function () {
    'use strict';
    document.querySelectorAll('input[data-motivo]').forEach((radio) => {
        const actualizar = () => {
            const marcado = document.querySelector(`input[name="${radio.name}"]:checked`);
            const caja = document.getElementById(radio.dataset.motivo);
            const sel = caja?.querySelector('select');
            if (sel) {
                sel.disabled = !marcado || marcado.value === 'Conforme';
            }
        };
        radio.addEventListener('change', actualizar);
        actualizar();
    });
    const resultados = document.querySelectorAll('input[name="resultado"]');
    const mostrar = () => {
        const elegido = document.querySelector('input[name="resultado"]:checked')?.value;
        document.querySelectorAll('[data-si-resultado]').forEach((el) => {
            el.hidden = el.dataset.siResultado !== elegido && !el.querySelector('.is-invalid');
        });
    };
    resultados.forEach((r) => r.addEventListener('change', mostrar));
    mostrar();
})();
