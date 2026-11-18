<?php

declare(strict_types=1);

namespace Tests\Unit\Configuracion;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * D-51 · En el entorno de pruebas ninguna conexión puede apuntar a la base de desarrollo, aunque SIGCCR_DB_GRUPO pida
 * otro grupo o un componente (copias de seguridad, migraciones) se conecte con el suyo.
 *
 * @internal
 */
final class BaseDePruebasTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        putenv('SIGCCR_DB_GRUPO');
        putenv('SIGCCR_BASE_PRUEBAS');
        parent::tearDown();
    }

    public function testTodosLosGruposUsanLaBaseDePruebas(): void
    {
        $config = new Database();

        $this->assertSame('testing', ENVIRONMENT);
        $this->assertSame('tests', $config->defaultGroup);
        foreach (['default', 'tests', 'migraciones', 'copias'] as $grupo) {
            $this->assertSame('sigccr_pruebas', $config->{$grupo}['database'], "El grupo {$grupo} apunta a otra base");
        }
    }

    public function testElGrupoPedidoPorVariableTambienUsaLaBaseDePruebas(): void
    {
        putenv('SIGCCR_DB_GRUPO=migraciones');
        $config = new Database();

        $this->assertSame('migraciones', $config->defaultGroup);
        $this->assertSame('sigccr_pruebas', $config->migraciones['database']);
    }

    public function testElGrupoTestsUsaLasCredencialesDeLaAplicacion(): void
    {
        $config = new Database();

        $this->assertSame($config->default['username'], $config->tests['username']);
        $this->assertSame($config->default['password'], $config->tests['password']);
    }
}
