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

    // CU-11 A9 (D-58, D-59): al cargar un CUIT o CUIL válido, la Central de Deudores del BCRA propone el nombre y el
    // apellido o la razón social, y el DNI sale del propio CUIL; el Analista los confirma o corrige. No se completa nada
    // más: el domicilio y el empleo los carga el Analista. La consulta automática completa los campos vacíos o los que
    // completó una consulta anterior, sin pisar lo que se escribió a mano; el botón vuelve a consultar y reemplaza.
    const form = document.querySelector('form.formulario-cliente');
    const padron = form?.querySelector('[data-padron]');
    const resultado = form?.querySelector('[data-padron-resultado]');
    const campoCuil = form?.querySelector('#cuit_cuil');
    const tipoCliente = () => form?.querySelector('input[name="tipo_cliente"]:checked')?.value || form?.querySelector('input[name="tipo_cliente"]')?.value || 'PersonaHumana';
    // Mismo control que el servidor (Campos::cuit): prefijo del tipo de cliente y dígito verificador
    const cuitValido = (c) => {
        const prefijos = tipoCliente() === 'PersonaJuridica' ? /^(30|33|34)\d{9}$/ : /^(20|23|24|27)\d{9}$/;
        if (!prefijos.test(c)) return false;
        const pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        const r = pesos.reduce((s, p, i) => s + p * Number(c[i]), 0) % 11;
        const dv = r === 0 ? 0 : 11 - r;
        return dv !== 10 && dv === Number(c[10]);
    };
    const enLista = (l) => (l.length > 1 ? l.slice(0, -1).join(', ') + ' y ' + l.at(-1) : l[0]);
    let ultimoConsultado = '';
    const consultar = async (reemplazar) => {
        const cuit = (campoCuil?.value || '').replace(/[-\s.]/g, '');
        resultado.classList.remove('text-danger');
        resultado.textContent = 'Consultando los datos del CUIT/CUIL…';
        padron.disabled = true;
        ultimoConsultado = cuit;
        try {
            const r = await fetch(padron.dataset.padron + '?cuil=' + encodeURIComponent(cuit) + '&tipo=' + encodeURIComponent(tipoCliente()), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const j = await r.json();
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
            const d = j.datos || {};
            // El tipo de documento acompaña al número: solo cambia si se completó el número
            if (poner('nro_documento', d.nro_documento, 'el DNI')) {
                const tipo = form.querySelector('#tipo_documento');
                if (tipo && d.tipo_documento) { tipo.value = d.tipo_documento; tipo.dispatchEvent(new Event('change')); }
            }
            poner('nombre', d.nombre, 'el nombre');
            poner('apellido', d.apellido, 'el apellido');
            poner('razon_social', d.razon_social, 'la razón social');
            if (!r.ok) {
                // Sin la persona en el servicio, o sin respuesta: solo el aviso (el DNI del CUIL ya quedó propuesto)
                resultado.classList.add('text-danger');
                resultado.textContent = j.mensaje + (completados.length ? ' Se completó ' + enLista(completados) + ' a partir del CUIL.' : '');
                return;
            }
            resultado.textContent = completados.length
                ? (completados.length > 1 ? 'Se completaron ' : 'Se completó ') + enLista(completados) + ': '
                    + (completados.length > 1 ? 'confírmelos o corríjalos' : 'confírmelo o corríjalo') + ' antes de registrar.'
                    + (d.apellido ? ' Revise dónde termina el apellido: el BCRA informa el nombre completo sin separarlo.' : '')
                : 'Los datos del CUIT/CUIL coinciden con los cargados.';
        } catch (e) {
            resultado.classList.add('text-danger');
            resultado.textContent = 'No es posible obtener los datos de esta persona.';
        } finally {
            padron.disabled = false;
        }
    };
    if (padron && resultado) {
        padron.addEventListener('click', () => consultar(true));
        // Consulta automática cuando el CUIT o CUIL queda completo y es válido, una vez por número
        campoCuil?.addEventListener('input', () => {
            const cuit = campoCuil.value.replace(/[-\s.]/g, '');
            if (cuit !== ultimoConsultado && cuitValido(cuit)) consultar(false);
        });
    }
})();
