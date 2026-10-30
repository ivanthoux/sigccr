/* Formulario de clientes (CU-11, CU-12): secciones según el tipo de cliente, documentación de identificación
   obligatoria según el tipo (RN-11, D-14) y comprobación de identidad al cambiar el correo (RN-58). */
(function () {
    'use strict';
    const form = document.querySelector('form.formulario-cliente');
    if (!form) {
        return;
    }
    const requisitos = JSON.parse(document.getElementById('requisitos-documentales')?.textContent || '{}');

    const tipoActual = () => form.querySelector('input[name="tipo_cliente"]:checked')?.value
        || form.querySelector('input[name="tipo_cliente"]')?.value || 'PersonaHumana';

    function actualizar() {
        const tipo = tipoActual();
        form.querySelectorAll('[data-solo]').forEach((el) => { el.hidden = el.dataset.solo !== tipo; });
        form.querySelectorAll('[data-aplica]').forEach((el) => { el.hidden = !['Ambos', tipo].includes(el.dataset.aplica); });
        const exigidos = requisitos[tipo] || [];
        form.querySelectorAll('[data-obligatorio]').forEach((el) => { el.hidden = !exigidos.includes(el.dataset.obligatorio); });
    }

    form.querySelectorAll('input[name="tipo_cliente"], #categoria_ingresos').forEach((el) => el.addEventListener('change', actualizar));
    actualizar();

    const correo = form.querySelector('#correo');
    const evidencia = form.querySelector('[data-evidencia]');
    if (correo && evidencia) {
        const revisar = () => { evidencia.hidden = correo.value.trim().toLowerCase() === (correo.dataset.original || '').toLowerCase(); };
        correo.addEventListener('input', revisar);
        revisar();
        if (evidencia.querySelector('.is-invalid')) {
            evidencia.hidden = false;
        }
    }
})();
