<?php

declare(strict_types=1);

/**
 * Funciones de presentación del SIG-CCR: permisos, menú y formatos locales (RNF-02, RNF-07).
 */

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
     * @return list<array{titulo: string, icono: string, opciones: list<array{texto: string, url: string}>}>
     */
    function menu_panel(): array
    {
        $menu = [];
        foreach (config('Menu')->secciones as $s) {
            $opciones = [];
            foreach ($s['opciones'] as $o) {
                $url = route_to($o['ruta']);
                if ($url !== false && ($o['permiso'] === null || tiene_permiso($o['permiso']))) {
                    $opciones[] = ['texto' => $o['texto'], 'url' => site_url(ltrim($url, '/'))];
                }
            }
            if ($opciones !== []) {
                $menu[] = ['titulo' => $s['titulo'], 'icono' => $s['icono'], 'opciones' => $opciones];
            }
        }

        return $menu;
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
