<?php

namespace Config;

use CodeIgniter\Database\Config;

/**
 * Conexiones del SIG-CCR (reemplaza el archivo del appstarter). Los valores reales se cargan desde .env
 * (database.<grupo>.<clave>); aquí quedan solo los valores por defecto sin credenciales (RNF-04).
 *
 * - default:     usuario de la aplicación (rol rol_sigccr_app: SELECT, INSERT, UPDATE).
 * - migraciones: propietario del esquema, para php spark migrate -g migraciones y los seeders.
 * - tests:       base de pruebas de integración.
 */
class Database extends Config
{
    public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;

    public string $defaultGroup = 'default';

    /**
     * @var array<string, mixed>
     */
    public array $default = [
        'DSN'        => '',
        'hostname'   => 'db',
        'username'   => '',
        'password'   => '',
        'database'   => 'sigccr',
        'schema'     => 'dbo',
        'DBDriver'   => 'SQLSRV',
        'DBPrefix'   => '',
        'pConnect'   => false,
        'DBDebug'    => true,
        'charset'    => 'utf8',
        'swapPre'    => '',
        'encrypt'    => false,
        'failover'   => [],
        'port'       => 1433,
        'dateFormat' => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    /**
     * @var array<string, mixed>
     */
    public array $migraciones = [
        'DSN'        => '',
        'hostname'   => 'db',
        'username'   => '',
        'password'   => '',
        'database'   => 'sigccr',
        'schema'     => 'dbo',
        'DBDriver'   => 'SQLSRV',
        'DBPrefix'   => '',
        'pConnect'   => false,
        'DBDebug'    => true,
        'charset'    => 'utf8',
        'swapPre'    => '',
        'encrypt'    => false,
        'failover'   => [],
        'port'       => 1433,
        'dateFormat' => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    /**
     * @var array<string, mixed>
     */
    public array $tests = [
        'DSN'         => '',
        'hostname'    => 'db',
        'username'    => '',
        'password'    => '',
        'database'    => 'sigccr_pruebas',
        'schema'      => 'dbo',
        'DBDriver'    => 'SQLSRV',
        'DBPrefix'    => '',
        'pConnect'    => false,
        'DBDebug'     => true,
        'charset'     => 'utf8',
        'swapPre'     => '',
        'encrypt'     => false,
        'failover'    => [],
        'port'        => 1433,
        'foreignKeys' => true,
        'busyTimeout' => 1000,
        'dateFormat'  => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    public function __construct()
    {
        parent::__construct();

        // Durante las pruebas se usa el grupo "tests" para no tocar la base de desarrollo.
        if (ENVIRONMENT === 'testing') {
            $this->defaultGroup = 'tests';
        }
    }
}
