/* SIG-CCR: comportamiento común del panel (RNF-02, RNF-07, RNF-12). Sin dependencias salvo donde se indica. */
(function () {
    'use strict';

    // Token CSRF para las solicitudes AJAX (RNF-12 b): fetch y jQuery lo envían en el encabezado configurado
    const nombre = document.querySelector('meta[name="csrf-nombre"]')?.content;
    const valor = document.querySelector('meta[name="csrf-valor"]')?.content;
    if (nombre && valor) {
        const fetchOriginal = window.fetch;
        window.fetch = (recurso, opciones = {}) => {
            opciones.headers = new Headers(opciones.headers || {});
            opciones.headers.set(nombre, valor);
            opciones.headers.set('X-Requested-With', 'XMLHttpRequest');
            return fetchOriginal(recurso, opciones);
        };
        if (window.jQuery) {
            window.jQuery.ajaxSetup({ headers: { [nombre]: valor } });
        }
    }

    // Botón para mostrar u ocultar una contraseña: <button data-mostrar="idDelCampo">
    document.querySelectorAll('[data-mostrar]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const campo = document.getElementById(boton.dataset.mostrar);
            const visible = campo.type === 'text';
            campo.type = visible ? 'password' : 'text';
            boton.querySelector('i')?.classList.toggle('bi-eye', visible);
            boton.querySelector('i')?.classList.toggle('bi-eye-slash', !visible);
            boton.setAttribute('aria-label', visible ? 'Mostrar la contraseña' : 'Ocultar la contraseña');
        });
    });

    // Contador de caracteres: <div data-contador="idDelCampo">
    document.querySelectorAll('[data-contador]').forEach((destino) => {
        const campo = document.getElementById(destino.dataset.contador);
        const actualizar = () => {
            const n = [...campo.value].length;
            destino.textContent = `${n} caracteres (entre ${campo.minLength} y ${campo.maxLength})`;
            destino.classList.toggle('text-danger', n > 0 && (n < campo.minLength || n > campo.maxLength));
        };
        campo.addEventListener('input', actualizar);
        actualizar();
    });

    // Confirmación antes de enviar: <form data-confirmar="¿Confirma…?">
    document.querySelectorAll('form[data-confirmar]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (!window.confirm(form.dataset.confirmar)) {
                e.preventDefault();
            }
        });
    });

    // Evita el doble envío de formularios
    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (e.defaultPrevented) {
                return;
            }
            form.querySelectorAll('button[type="submit"]').forEach((b) => { b.disabled = true; });
        });
    });

    // Tablas: <table data-tabla> se convierte en DataTable con textos en español (requiere jQuery y DataTables)
    if (window.DataTable) {
        Object.assign(window.DataTable.defaults, {
            pageLength: 25,
            language: {
                decimal: ',',
                thousands: '.',
                emptyTable: 'No hay datos para mostrar',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                infoEmpty: 'Sin registros',
                infoFiltered: '(filtrado de _MAX_ registros)',
                lengthMenu: 'Mostrar _MENU_ registros',
                loadingRecords: 'Cargando…',
                processing: 'Procesando…',
                search: 'Buscar:',
                zeroRecords: 'No se encontraron coincidencias',
                paginate: { first: 'Primera', last: 'Última', next: 'Siguiente', previous: 'Anterior' },
                aria: { orderable: 'Ordenar por esta columna', orderableReverse: 'Invertir el orden' },
            },
        });
        document.querySelectorAll('table[data-tabla]').forEach((t) => new window.DataTable(t));

        // Plan de cuotas (D-23): de a 12 cuotas por página, sin búsqueda ni orden; al imprimir se muestran todas
        document.querySelectorAll('table[data-plan-cuotas]').forEach((t) => {
            if (t.tBodies[0].rows.length <= 12) {
                return;
            }
            const plan = new window.DataTable(t, {
                pageLength: 12,
                lengthMenu: [[12, 24, 60, -1], [12, 24, 60, 'Todas']],
                searching: false,
                ordering: false,
                language: { info: 'Cuotas _START_ a _END_ de _TOTAL_', lengthMenu: 'Mostrar _MENU_ cuotas' },
            });
            let largo = 12;
            window.addEventListener('beforeprint', () => { largo = plan.page.len(); plan.page.len(-1).draw(false); });
            window.addEventListener('afterprint', () => { plan.page.len(largo).draw(false); });
        });
    }

    // Adjuntos de hasta 5 MB (RNF-12): se avisa junto al campo apenas se elige el archivo y no se envía el formulario,
    // en lugar de que el servidor rechace la solicitud completa (error 413). El servidor vuelve a validarlo.
    const MAXIMO_ADJUNTO = 5 * 1024 * 1024;
    const revisarAdjuntos = (campo) => {
        const grandes = [...(campo.files || [])].filter((f) => f.size > MAXIMO_ADJUNTO).map((f) => f.name);
        let aviso = campo.parentElement.querySelector('.aviso-adjunto');
        if (grandes.length === 0) {
            aviso?.remove();
            campo.classList.remove('is-invalid');
            return true;
        }
        if (!aviso) {
            aviso = document.createElement('div');
            aviso.className = 'invalid-feedback d-block aviso-adjunto';
            campo.insertAdjacentElement('afterend', aviso);
        }
        aviso.textContent = grandes.map((n) => `El archivo ${n} supera los 5 MB.`).join(' ');
        campo.classList.add('is-invalid');
        return false;
    };
    const campos = [...document.querySelectorAll('input[type="file"]')];
    campos.forEach((campo) => campo.addEventListener('change', () => revisarAdjuntos(campo)));
    new Set(campos.map((c) => c.form).filter(Boolean)).forEach((form) => {
        form.addEventListener('submit', (evento) => {
            const invalidos = [...form.querySelectorAll('input[type="file"]')].filter((c) => !revisarAdjuntos(c));
            if (invalidos.length > 0) {
                evento.preventDefault();
                evento.stopImmediatePropagation();
                invalidos[0].focus();
            }
        });
    });
})();
