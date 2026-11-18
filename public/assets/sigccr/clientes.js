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
    // El resultado de la carga se anuncia a los lectores de pantalla (el texto del select no se lee al cambiar)
    const anuncio = document.createElement('div');
    anuncio.className = 'visually-hidden';
    anuncio.setAttribute('aria-live', 'polite');
    localidad.insertAdjacentElement('afterend', anuncio);
    provincia.addEventListener('change', async () => {
        localidad.replaceChildren(opcion('', provincia.value ? 'Cargando localidades…' : 'Elija primero la provincia'));
        localidad.disabled = true;
        anuncio.textContent = provincia.value ? 'Cargando localidades…' : '';
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
            anuncio.textContent = lista.length === 0 ? 'No hay localidades cargadas para la provincia.'
                : `${lista.length} ${lista.length === 1 ? 'localidad disponible' : 'localidades disponibles'}.`;
        } catch (e) {
            localidad.replaceChildren(opcion('', 'No se pudieron cargar las localidades; intente de nuevo'));
            anuncio.textContent = 'No se pudieron cargar las localidades; elija la provincia de nuevo.';
        }
    });

    // CU-11 A9 (D-58): al cargar un CUIL válido de una persona humana, el padrón de ARCA completa el documento, el nombre
    // y el apellido; el Analista los confirma o corrige. La consulta automática completa los campos vacíos o los que
    // completó una consulta anterior, sin pisar lo que se escribió a mano; el botón vuelve a consultar y reemplaza todo.
    const form = document.querySelector('form.formulario-cliente');
    const padron = form?.querySelector('[data-padron]');
    const resultado = form?.querySelector('[data-padron-resultado]');
    const campoCuil = form?.querySelector('#cuit_cuil');
    // Mismo control que el servidor (Campos::cuit): prefijo de persona humana y dígito verificador
    const cuilValido = (c) => {
        if (!/^(20|23|24|27)\d{9}$/.test(c)) return false;
        const pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        const r = pesos.reduce((s, p, i) => s + p * Number(c[i]), 0) % 11;
        const dv = r === 0 ? 0 : 11 - r;
        return dv !== 10 && dv === Number(c[10]);
    };
    const esPersonaHumana = () => (form?.querySelector('input[name="tipo_cliente"]:checked')?.value || form?.querySelector('input[name="tipo_cliente"]')?.value) === 'PersonaHumana';
    let ultimoConsultado = '';
    const consultar = async (reemplazar) => {
        const cuil = (campoCuil?.value || '').replace(/[-\s.]/g, '');
        resultado.classList.remove('text-danger');
        resultado.textContent = 'Consultando el padrón…';
        padron.disabled = true;
        ultimoConsultado = cuil;
        try {
            const r = await fetch(padron.dataset.padron + '?cuil=' + encodeURIComponent(cuil), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const j = await r.json();
            if (!r.ok) {
                resultado.classList.add('text-danger');
                resultado.textContent = j.mensaje;
                return;
            }
            const completados = [];
            const poner = (id, valor, etiqueta) => {
                const el = form.querySelector('#' + id);
                // Sin reemplazar: solo un campo vacío o con el dato que puso una consulta anterior y nadie cambió
                if (!el || !valor || (!reemplazar && el.value.trim() !== '' && el.value !== el.dataset.delPadron)) return false;
                if (el.value !== valor) { el.value = valor; el.dispatchEvent(new Event('change')); }
                el.dataset.delPadron = valor;
                completados.push(etiqueta);
                return true;
            };
            // El tipo de documento acompaña al número: solo cambia si el padrón completó el número
            if (poner('nro_documento', j.datos.nro_documento, 'el DNI')) {
                const tipo = form.querySelector('#tipo_documento');
                if (tipo && j.datos.tipo_documento) { tipo.value = j.datos.tipo_documento; tipo.dispatchEvent(new Event('change')); }
            }
            poner('nombre', j.datos.nombre, 'el nombre');
            poner('apellido', j.datos.apellido, 'el apellido');
            const domicilio = form.querySelector('#domicilio');
            if (domicilio && domicilio.value.trim() === '') {
                domicilio.value = j.datos.domicilio_fiscal.split(',')[0];
            }
            const lista = completados.length > 1 ? completados.slice(0, -1).join(', ') + ' y ' + completados.at(-1) : completados[0];
            resultado.textContent = (completados.length ? 'Se completaron ' + lista + ' con el padrón de ARCA: confírmelos o corríjalos antes de registrar.' : j.mensaje)
                + ' Domicilio fiscal: ' + j.datos.domicilio_fiscal + '.';
        } catch (e) {
            resultado.classList.add('text-danger');
            resultado.textContent = 'No se pudo consultar el padrón: complete los datos manualmente.';
        } finally {
            padron.disabled = false;
        }
    };
    if (padron && resultado) {
        padron.addEventListener('click', () => consultar(true));
        // Consulta automática cuando el CUIL queda completo y es válido, una vez por CUIL
        campoCuil?.addEventListener('input', () => {
            const cuil = campoCuil.value.replace(/[-\s.]/g, '');
            if (esPersonaHumana() && cuil !== ultimoConsultado && cuilValido(cuil)) consultar(false);
        });
    }
})();
