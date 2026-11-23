/* SIG-CCR: comportamiento común del panel (RNF-02, RNF-07, RNF-12). Sin dependencias salvo donde se indica. */
(function () {
    'use strict';

    const esteScript = document.currentScript;

    // Tema claro u oscuro (D-50): partials/tema.php lo aplica antes de los estilos; acá se atiende el selector, se
    // sigue al sistema operativo mientras la persona no elija uno y se imprime siempre en claro.
    const raiz = document.documentElement;
    const temaGuardado = () => { try { return localStorage.getItem('sigccr-tema'); } catch (e) { return null; } };
    const selectores = [...document.querySelectorAll('[data-cambiar-tema]')];
    const aplicarTema = (tema) => {
        raiz.dataset.bsTheme = tema;
        selectores.forEach((b) => b.setAttribute('aria-pressed', String(tema === 'dark')));
    };
    aplicarTema(raiz.dataset.bsTheme === 'dark' ? 'dark' : 'light');
    selectores.forEach((boton) => boton.addEventListener('click', () => {
        const tema = raiz.dataset.bsTheme === 'dark' ? 'light' : 'dark';
        try { localStorage.setItem('sigccr-tema', tema); } catch (e) { /* se aplica solo en esta página */ }
        aplicarTema(tema);
    }));
    const sistemaOscuro = window.matchMedia('(prefers-color-scheme: dark)');
    sistemaOscuro.addEventListener('change', (e) => {
        if (temaGuardado() === null) {
            aplicarTema(e.matches ? 'dark' : 'light');
        }
    });
    let temaAntesDeImprimir = null;
    window.addEventListener('beforeprint', () => { temaAntesDeImprimir = raiz.dataset.bsTheme; raiz.dataset.bsTheme = 'light'; });
    window.addEventListener('afterprint', () => { if (temaAntesDeImprimir) { raiz.dataset.bsTheme = temaAntesDeImprimir; } });

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

    // Toda tabla del contenido se desplaza dentro de su recuadro en pantallas angostas (WCAG 1.4.10), aunque la vista no
    // la haya envuelto en .table-responsive: así ninguna tabla ensancha la página en el celular. Las de DataTables se
    // envuelven más abajo, después de inicializarlas.
    const enMarco = (t) => {
        if (t.closest('.table-responsive')) {
            return;
        }
        const marco = document.createElement('div');
        marco.className = 'table-responsive';
        t.parentNode.insertBefore(marco, t);
        marco.appendChild(t);
    };
    document.querySelectorAll('main table.table:not([data-tabla])').forEach(enMarco);

    // Formulario devuelto con observaciones del servidor: el foco va al primer campo marcado y visible, para no
    // recorrer el formulario buscándolo (la alerta general ya se anuncia con role="alert"). Se espera a que los
    // scripts de cada pantalla oculten las secciones que no corresponden (por ejemplo, según el tipo de cliente).
    document.addEventListener('DOMContentLoaded', () => {
        const primero = [...document.querySelectorAll('main input[aria-invalid="true"], main select[aria-invalid="true"], main textarea[aria-invalid="true"]')]
            .find((campo) => campo.offsetParent !== null || campo.classList.contains('btn-check'));
        primero?.focus();
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

    // Tablas: <table data-tabla> se convierte en DataTable con textos en español. jQuery y DataTables se cargan solo
    // en las pantallas del panel que tienen una tabla así o un plan de más de 12 cuotas (D-50): el resto no las
    // descarga ni las ejecuta.
    const iniciarTablas = () => {
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
        // data-orden-servidor: conserva el orden en que llegan las filas (por ejemplo, los registrados más recientemente)
        // data-sin-busqueda: la pantalla ya tiene filtros del servidor; un segundo buscador solo filtraría las filas
        // cargadas y podría decir «sin coincidencias» de un registro que existe
        document.querySelectorAll('table[data-tabla]').forEach((t) => {
            const opciones = {};
            if (t.hasAttribute('data-orden-servidor')) {
                opciones.order = [];
            }
            if (t.hasAttribute('data-sin-busqueda')) {
                opciones.searching = false;
            }
            new window.DataTable(t, opciones);
            // En pantallas angostas la tabla se desplaza dentro de su recuadro, sin ensanchar la página (360 px)
            enMarco(t);
        });

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
    };
    const conTablas = esteScript?.hasAttribute('data-tablas') && (document.querySelector('table[data-tabla]')
        || [...document.querySelectorAll('table[data-plan-cuotas]')].some((t) => t.tBodies[0]?.rows.length > 12));
    if (conTablas) {
        const proveedores = new URL('../vendor/', esteScript.src);
        const estilos = document.createElement('link');
        estilos.rel = 'stylesheet';
        estilos.href = new URL('datatables/dataTables.bootstrap5.min.css', proveedores).href;
        document.head.appendChild(estilos);
        const cargar = (ruta) => new Promise((resolver, rechazar) => {
            const s = document.createElement('script');
            s.src = new URL(ruta, proveedores).href;
            s.onload = resolver;
            s.onerror = rechazar;
            document.body.appendChild(s);
        });
        cargar('jquery/jquery.min.js')
            .then(() => {
                if (nombre && valor) {
                    window.jQuery.ajaxSetup({ headers: { [nombre]: valor } });
                }
                return cargar('datatables/dataTables.min.js');
            })
            .then(() => cargar('datatables/dataTables.bootstrap5.min.js'))
            .then(iniciarTablas)
            .catch(() => document.querySelectorAll('main table[data-tabla]').forEach(enMarco)); // sin DataTables, la tabla completa
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

/* CU-108 (D-64): el pedido de revisión de una decisión automatizada pide además la solicitud de crédito. */
(() => {
    const tipo = document.querySelector('[data-derechos-tipo]');
    const solicitud = document.querySelector('[data-derechos-solicitud]');
    if (tipo === null || solicitud === null) {
        return;
    }
    tipo.addEventListener('change', () => {
        solicitud.hidden = tipo.value !== 'RevisionAutomatizada';
    });
})();

/* Los selectores marcados con data-autoenviar envían su formulario al cambiar (por ejemplo, registros por página). */
document.querySelectorAll('select[data-autoenviar]').forEach((campo) => {
    campo.addEventListener('change', () => campo.form?.requestSubmit());
});
