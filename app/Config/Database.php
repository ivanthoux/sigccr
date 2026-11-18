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
     * Inicio de sesión de mantenimiento de las copias (CU-61 a CU-63, D-45): BACKUP DATABASE en sigccr
     * (db_backupoperator) y dbcreator para verificar y restaurar. Se conecta a master porque la restauración reemplaza
     * la base sigccr. Lo usa solo el contenedor de procesos.
     *
     * @var array<string, mixed>
     */
    public array $copias = [
        'DSN'        => '',
        'hostname'   => 'db',
        'username'   => '',
        'password'   => '',
        'database'   => 'master',
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

        // Durante las pruebas se usa el grupo "tests" para no tocar la base de desarrollo (D-49): todos los grupos
        // apuntan a la base de pruebas, de modo que ni SIGCCR_DB_GRUPO ni un componente que pida otro grupo
        // (por ejemplo, las copias de seguridad) llegan a la base de desarrollo.
        if (ENVIRONMENT === 'testing') {
            $this->defaultGroup = 'tests';
            $this->aislarPruebas();
        }

        // Las migraciones y los seeders se ejecutan con el propietario del esquema: CodeIgniter crea y actualiza la
        // tabla "migrations" con el grupo por defecto, y sigccr_app no tiene permiso CREATE TABLE (4.3.5).
        //   SIGCCR_DB_GRUPO=migraciones php spark migrate --all
        // En pruebas, SIGCCR_DB_GRUPO=migraciones prepara la base de pruebas con el propietario (tests/feature/preparar_base.sh)
        $grupo = getenv('SIGCCR_DB_GRUPO');
        if (is_string($grupo) && $grupo !== '' && property_exists($this, $grupo) && is_array($this->{$grupo})) {
            $this->defaultGroup = $grupo;
        }
    }

    /**
     * D-49: en el entorno de pruebas todos los grupos usan la base de pruebas, que nunca puede ser la de desarrollo.
     * Las credenciales que el grupo "tests" no define se toman del grupo de la aplicación (default), de modo que las
     * pruebas operan con los mismos permisos que la aplicación (rol_sigccr_app); el grupo "migraciones" conserva las
     * del propietario del esquema para preparar la base.
     */
    private function aislarPruebas(): void
    {
        $base = (string) $this->tests['database'];
        if ($base === '' || $base === (string) $this->default['database']) {
            throw new \RuntimeException('La base de pruebas (database.tests.database) no puede estar vacía ni ser la base de la aplicación.');
        }
        foreach (['username', 'password'] as $clave) {
            if ((string) $this->tests[$clave] === '') {
                $this->tests[$clave] = $this->default[$clave];
            }
        }
        foreach (['default', 'migraciones', 'copias'] as $grupo) {
            $this->{$grupo}['database'] = $base;
            $this->{$grupo}['hostname'] = $this->tests['hostname'];
            $this->{$grupo}['port'] = $this->tests['port'];
        }
        $this->default['username'] = $this->tests['username'];
        $this->default['password'] = $this->tests['password'];
    }
}
