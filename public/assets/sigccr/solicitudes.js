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

/* CU-25: la tabla de deudas de la consulta manual se muestra al marcar ese antecedente. */
(function () {
    'use strict';
    document.querySelectorAll('input[data-muestra]').forEach((casilla) => {
        const destino = document.querySelector(casilla.dataset.muestra);
        if (destino) {
            casilla.addEventListener('change', () => { destino.hidden = !casilla.checked; });
        }
    });
})();

/* CU-26: los campos de cada decisión se muestran según la elegida, y el plan sigue a la alternativa marcada. */
(function () {
    'use strict';
    const decisiones = document.querySelectorAll('input[name="decision"]');
    if (decisiones.length) {
        const mostrar = () => {
            const elegida = document.querySelector('input[name="decision"]:checked')?.value;
            document.querySelectorAll('[data-si-decision]').forEach((el) => {
                el.hidden = el.dataset.siDecision !== elegida && !el.querySelector('.is-invalid');
            });
        };
        decisiones.forEach((d) => d.addEventListener('change', mostrar));
        mostrar();
    }
    const plan = document.getElementById('plan');
    document.querySelectorAll('input[data-plan]').forEach((r) => r.addEventListener('change', () => { if (plan) { plan.value = r.dataset.plan; } }));
})();

/* CU-27: los datos de la transferencia se muestran solo para ese medio de desembolso. */
(function () {
    'use strict';
    const medios = document.querySelectorAll('input[name="medio"]');
    if (!medios.length) {
        return;
    }
    const mostrar = () => {
        const elegido = document.querySelector('input[name="medio"]:checked')?.value;
        document.querySelectorAll('[data-si-medio]').forEach((el) => {
            el.hidden = el.dataset.siMedio !== elegido && !el.querySelector('.is-invalid');
        });
    };
    medios.forEach((m) => m.addEventListener('change', mostrar));
    mostrar();
})();
