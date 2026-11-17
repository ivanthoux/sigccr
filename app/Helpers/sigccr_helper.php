<?php

/**
 * Funciones de presentación del SIG-CCR: permisos, menú y formatos locales (RNF-02, RNF-07).
 */

declare(strict_types=1);

use App\Libraries\Finanzas\Decimal;
use CodeIgniter\I18n\Time;

if (!function_exists('tiene_permiso')) {
    /** La cuenta de la sesión tiene el permiso (la autorización real la hace PermisoFilter, RNF-01). */
    function tiene_permiso(string $permiso): bool
    {
        return in_array($permiso, session('cuenta')['permisos'] ?? [], true);
    }
}

if (!function_exists('menu_panel')) {
    /**
     * Secciones y opciones visibles para la cuenta: con permiso y con la ruta ya construida.
     *
     * @return list<array{titulo: string, icono: string, opciones: list<array{texto: string, icono: string, url: string}>}>
     */
    function menu_panel(): array
    {
        $menu = [];
        foreach (config('Menu')->secciones as $s) {
            $opciones = [];
            foreach ($s['opciones'] as $o) {
                $url = route_to($o['ruta']);
                if ($url !== false && ($o['permiso'] === null || tiene_permiso($o['permiso']))) {
                    $opciones[] = ['texto' => $o['texto'], 'icono' => $o['icono'] ?? $s['icono'], 'url' => site_url(ltrim($url, '/'))];
                }
            }
            if ($opciones !== []) {
                $menu[] = ['titulo' => $s['titulo'], 'icono' => $s['icono'], 'opciones' => $opciones];
            }
        }

        return $menu;
    }
}

if (!function_exists('alertas_pendientes')) {
    /** Alertas pendientes del usuario o de su rol, para el indicador del panel (CU-83 paso 1); una consulta por pedido. */
    function alertas_pendientes(): int
    {
        static $cantidad = null;
        $cuenta = session('cuenta');
        if ($cantidad === null) {
            $cantidad = is_array($cuenta) && in_array('alertas.consultar', $cuenta['permisos'] ?? [], true)
                ? (new \App\Services\Comunes\AlertaService())->contarPendientes((int) $cuenta['id'], (string) $cuenta['rol'])
                : 0;
        }

        return $cantidad;
    }
}

if (!function_exists('formato_importe')) {
    /** Importe con separador de miles y coma decimal: $ 1.234,56 (RNF-02). Acepta cadenas, Decimal o números. */
    function formato_importe(string|int|float|Decimal|null $importe, bool $conSigno = true): string
    {
        if ($importe === null || $importe === '') {
            return '';
        }
        $texto = $importe instanceof Decimal ? $importe->toFixed(2) : Decimal::of((string) $importe)->toFixed(2);
        $negativo = str_starts_with($texto, '-');
        [$entero, $dec] = explode('.', ltrim($texto, '-'));
        $entero = strrev(implode('.', str_split(strrev($entero), 3)));

        return ($negativo ? '-' : '') . ($conSigno ? '$ ' : '') . $entero . ',' . $dec;
    }
}

if (!function_exists('formato_fecha')) {
    /** Fecha local dd/mm/aaaa. Las fechas de negocio se muestran tal cual; los instantes, en la zona de negocio. */
    function formato_fecha(string|DateTimeInterface|null $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return implode('/', array_reverse(explode('-', $valor)));
        }

        return a_zona_negocio($valor)->format('d/m/Y');
    }
}

if (!function_exists('formato_fecha_hora')) {
    function formato_fecha_hora(string|DateTimeInterface|null $valor): string
    {
        return $valor === null || $valor === '' ? '' : a_zona_negocio($valor)->format('d/m/Y H:i');
    }
}

if (!function_exists('a_zona_negocio')) {
    function a_zona_negocio(string|DateTimeInterface $valor): Time
    {
        $t = $valor instanceof DateTimeInterface ? Time::createFromInstance($valor) : Time::parse($valor, 'UTC');

        return $t->setTimezone(config('Sigccr')->zonaNegocio);
    }
}

if (!function_exists('etiqueta_estado_cuenta')) {
    /** Etiqueta del estado de una cuenta de acceso (usuarios internos y clientes). */
    function etiqueta_estado_cuenta(string $estado): string
    {
        [$texto, $clase] = [
            'PendienteActivacion' => ['Pendiente de activación', 'text-bg-warning'],
            'Habilitada' => ['Habilitado', 'text-bg-success'],
            'BloqueadaTemporalmente' => ['Bloqueado temporalmente', 'text-bg-danger'],
            'Desactivada' => ['Desactivado', 'text-bg-secondary'],
        ][$estado] ?? [$estado, 'text-bg-light'];

        return '<span class="badge rounded-pill ' . $clase . '">' . esc($texto) . '</span>';
    }
}

if (!function_exists('campo_invalido')) {
    /**
     * Atributos para un campo con error de validación: ' is-invalid" aria-invalid="true" aria-describedby="error-…' se
     * agrega a class="...". Si el campo tiene un texto de ayuda, se pasa su id en $ayuda y el campo no declara su propio
     * aria-describedby: el helper lo emite siempre, con el error primero y la ayuda después (un atributo repetido se
     * descarta y la ayuda dejaba de leerse justo cuando hay error).
     */
    function campo_invalido(string $campo, string $ayuda = ''): string
    {
        $ayuda = esc($ayuda, 'attr');
        if (!isset((session('errores') ?? [])[$campo])) {
            return $ayuda === '' ? '' : '" aria-describedby="' . $ayuda;
        }

        return ' is-invalid" aria-invalid="true" aria-describedby="error-' . esc($campo, 'attr') . ($ayuda === '' ? '' : ' ' . $ayuda);
    }
}

if (!function_exists('grupo_invalido')) {
    /**
     * Atributos para cada opción de un grupo de radios con error (por ejemplo, el rol): la marca como inválida y le
     * asocia el mensaje de error_campo(), para que el lector de pantalla lo lea al llegar al grupo.
     */
    function grupo_invalido(string $campo): string
    {
        return isset((session('errores') ?? [])[$campo]) ? ' aria-invalid="true" aria-describedby="error-' . esc($campo, 'attr') . '"' : '';
    }
}

if (!function_exists('error_campo')) {
    /** Mensaje de error de un campo, debajo del control (Bootstrap invalid-feedback). */
    function error_campo(string $campo): string
    {
        $mensaje = (session('errores') ?? [])[$campo] ?? null;

        return $mensaje === null ? '' : '<div class="invalid-feedback d-block" id="error-' . esc($campo, 'attr') . '">' . esc($mensaje) . '</div>';
    }
}

if (!function_exists('formato_cuit')) {
    /** CUIT/CUIL con guiones: 20-12345678-6. */
    function formato_cuit(?string $cuit): string
    {
        return $cuit === null || strlen($cuit) !== 11 ? (string) $cuit : substr($cuit, 0, 2) . '-' . substr($cuit, 2, 8) . '-' . substr($cuit, 10);
    }
}

if (!function_exists('formato_porcentaje')) {
    /** Tasa en tanto por uno como porcentaje local: 0.795856 -> 79,59 % (RN-63). */
    function formato_porcentaje(string|Decimal|null $tasa, int $decimales = 2): string
    {
        if ($tasa === null || $tasa === '') {
            return '';
        }

        return str_replace('.', ',', ($tasa instanceof Decimal ? $tasa : Decimal::of($tasa))->toPercent($decimales)) . ' %';
    }
}

if (!function_exists('texto_gasto_administrativo')) {
    /** Gasto administrativo de una versión de condiciones: importe fijo o porcentaje del capital por cuota (D-28). */
    function texto_gasto_administrativo(array $version): string
    {
        return ($version['tipo_gasto_administrativo'] ?? 'Fijo') === 'Porcentual'
            ? formato_porcentaje((string) $version['gasto_administrativo_pct'], 2) . ' del capital por cuota'
            : formato_importe((string) $version['gasto_administrativo_cuota']) . ' por cuota';
    }
}

if (!function_exists('etiqueta_estado_solicitud')) {
    /** Insignia del estado de una solicitud (4.2.3). */
    function etiqueta_estado_solicitud(string $estado, ?string $tipoRechazo = null): string
    {
        $clase = match (true) {
            in_array($estado, ['Liquidada', 'AprobadaPendienteLiquidacion'], true) => 'text-bg-success',
            in_array($estado, ['Rechazada', 'Cancelada', 'Vencida'], true) => 'text-bg-secondary',
            in_array($estado, ['PendienteDocumentacion', 'DevueltaRevision', 'PendienteContingencia'], true) => 'text-bg-warning',
            default => 'text-bg-light border',
        };
        $texto = \App\Libraries\Solicitudes\EstadosSolicitud::etiqueta($estado) . ($estado === 'Rechazada' && $tipoRechazo === 'Documental' ? ' (documental)' : '');

        return '<span class="badge rounded-pill ' . $clase . '">' . esc($texto) . '</span>';
    }
}

if (!function_exists('finalizar_sesion')) {
    /**
     * Cierra la sesión actual sin perder el mensaje para la pantalla siguiente (RNF-11): borra todos sus datos y
     * cambia el identificador eliminando la sesión anterior. Con session()->destroy() el mensaje que se agrega con
     * with() se escribía en una sesión ya destruida y no llegaba a mostrarse.
     */
    function finalizar_sesion(): void
    {
        $sesion = session();
        $sesion->remove(array_keys((array) $sesion->get()));
        $sesion->regenerate(true);
    }
}

if (!function_exists('hoy_negocio')) {
    /**
     * Fecha de hoy en la zona de negocio (Y-m-d). PHP corre en UTC (3.5.1): date('Y-m-d') da el día siguiente entre
     * las 21:00 y las 24:00 de Argentina, y los formularios proponían una fecha futura que la validación rechaza.
     */
    function hoy_negocio(): string
    {
        return \CodeIgniter\I18n\Time::now(config('Sigccr')->zonaNegocio)->format('Y-m-d');
    }
}

if (!function_exists('valor_reporte')) {
    /**
     * Valor de un indicador o una celda de los reportes (SS-09, D-44) según su tipo: importe, entero, decimal,
     * porcentaje (ya expresado en %), fecha, fechahora o texto. Los vacíos se muestran con una raya.
     */
    function valor_reporte(mixed $valor, string $tipo, bool $conSigno = false): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return match ($tipo) {
            'importe' => formato_importe((string) $valor, $conSigno),
            'entero' => number_format((int) $valor, 0, ',', '.'),
            'decimal' => number_format((float) $valor, 1, ',', '.'),
            'porcentaje' => number_format((float) $valor, 2, ',', '.') . ' %',
            'fecha' => formato_fecha((string) $valor),
            'fechahora' => formato_fecha_hora((string) $valor),
            default => (string) $valor,
        };
    }
}
