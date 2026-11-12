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
        // D-26: la categoría del monotributo solo para monotributistas
        const categoria = form.querySelector('#categoria_ingresos')?.value;
        form.querySelectorAll('[data-si-categoria]').forEach((el) => { el.hidden = tipo !== 'PersonaHumana' || el.dataset.siCategoria !== categoria; });
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

/* D-18: al elegir la provincia se cargan sus localidades. Sin JavaScript, el servidor valida que la localidad
   pertenezca a la provincia y vuelve a mostrar el formulario con las localidades de la provincia elegida. */
(function () {
    'use strict';
    const provincia = document.getElementById('provincia_id');
    const localidad = document.getElementById('localidad_id');
    if (!provincia || !localidad) {
        return;
    }
    const opcion = (valor, texto) => {
        const o = document.createElement('option');
        o.value = valor;
        o.textContent = texto;
        return o;
    };
    provincia.addEventListener('change', async () => {
        localidad.replaceChildren(opcion('', provincia.value ? 'Cargando localidades…' : 'Elija primero la provincia'));
        localidad.disabled = true;
        if (!provincia.value) {
            return;
        }
        try {
            const r = await fetch(`${provincia.dataset.localidades}/${provincia.value}`, { headers: { Accept: 'application/json' } });
            if (!r.ok) {
                throw new Error(String(r.status));
            }
            const lista = await r.json();
            localidad.replaceChildren(opcion('', lista.length ? 'Seleccione…' : 'No hay localidades cargadas para la provincia'), ...lista.map((l) => opcion(l.id, l.nombre)));
            localidad.disabled = lista.length === 0;
        } catch (e) {
            localidad.replaceChildren(opcion('', 'No se pudieron cargar las localidades; intente de nuevo'));
        }
    });

    // CU-11 A9: el padrón de ARCA propone los datos del CUIL; el Analista los confirma o corrige
    const padron = form.querySelector('[data-padron]');
    const resultado = form.querySelector('[data-padron-resultado]');
    if (padron && resultado) {
        padron.addEventListener('click', async () => {
            const cuil = (form.querySelector('#cuit_cuil')?.value || '').trim();
            resultado.classList.remove('text-danger');
            resultado.textContent = 'Consultando el padrón…';
            padron.disabled = true;
            try {
                const r = await fetch(padron.dataset.padron + '?cuil=' + encodeURIComponent(cuil), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const j = await r.json();
                if (!r.ok) {
                    resultado.classList.add('text-danger');
                    resultado.textContent = j.mensaje;
                    return;
                }
                const poner = (id, valor) => { const el = form.querySelector('#' + id); if (el && valor) { el.value = valor; el.dispatchEvent(new Event('change')); } };
                poner('nombre', j.datos.nombre);
                poner('apellido', j.datos.apellido);
                poner('tipo_documento', j.datos.tipo_documento);
                poner('nro_documento', j.datos.nro_documento);
                const domicilio = form.querySelector('#domicilio');
                if (domicilio && domicilio.value.trim() === '') {
                    domicilio.value = j.datos.domicilio_fiscal.split(',')[0];
                }
                resultado.textContent = j.mensaje + ' Domicilio fiscal: ' + j.datos.domicilio_fiscal + '.';
            } catch (e) {
                resultado.classList.add('text-danger');
                resultado.textContent = 'No se pudo consultar el padrón: complete los datos manualmente.';
            } finally {
                padron.disabled = false;
            }
        });
    }
})();
