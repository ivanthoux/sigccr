<?php

namespace Config;

use App\Filters\AuthFilter;
use App\Filters\LimiteIntentosFilter;
use App\Filters\MantenimientoPortalFilter;
use App\Filters\PermisoFilter;
use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

/**
 * Cadena de filtros del SIG-CCR (4.4.6, RNF-01, RNF-12).
 * Reemplaza el archivo del appstarter de CodeIgniter 4.6, conservando sus filtros requeridos.
 */
class Filters extends BaseFilters
{
    /**
     * @var array<string, class-string|list<class-string>>
     */
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,
        // SIG-CCR
        'auth'          => AuthFilter::class,
        'permiso'       => PermisoFilter::class,
        'mantenimiento' => MantenimientoPortalFilter::class,
        'limiteintentos' => LimiteIntentosFilter::class,
    ];

    /**
     * @var array{before: list<string>, after: list<string>}
     */
    public array $required = [
        'before' => [
            'forcehttps', // HTTPS obligatorio (RNF-12 a)
            'pagecache',
        ],
        'after' => [
            'pagecache',
            'performance',
            'toolbar',
        ],
    ];

    /**
     * @var array<string, array<string, array<string, string|array<string>>>>|array<string, list<string>>
     */
    public array $globals = [
        'before' => [
            'invalidchars',
            // CSRF en formularios y AJAX (RNF-12 b); las notificaciones de la pasarela se validan por firma
            'csrf' => ['except' => ['api/*']],
        ],
        'after' => [
            'secureheaders', // X-Content-Type-Options, X-Frame-Options y otras (RNF-12 e)
        ],
    ];

    /**
     * @var array<string, list<string>>
     */
    public array $methods = [];

    /**
     * Las rutas protegidas declaran sus filtros en app/Config/Routes.php ('auth:panel', 'permiso:<código>').
     *
     * @var array<string, array<string, list<string>>>
     */
    public array $filters = [
        'mantenimiento'  => ['before' => ['portal', 'portal/*']],
        'limiteintentos' => ['before' => ['admin/login', 'admin/recuperar', 'portal/login', 'portal/recuperar']],
    ];
}
