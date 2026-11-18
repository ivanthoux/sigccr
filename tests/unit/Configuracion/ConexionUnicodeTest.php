<?php

declare(strict_types=1);

namespace Tests\Unit\Configuracion;

use App\Database\SQLSRV\Connection;
use CodeIgniter\Database\RawSql;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * D-49, H-09 · Los textos con caracteres fuera de ASCII se escriben como N'...' para que SQL Server no los convierta a
 * la página de códigos de la intercalación (un emoji se guardaba como «??»). No necesita la base: escape() no conecta.
 *
 * @internal
 */
final class ConexionUnicodeTest extends CIUnitTestCase
{
    private function conexion(): Connection
    {
        return new Connection(['DBDriver' => 'App\Database\SQLSRV', 'hostname' => 'db', 'database' => 'x', 'username' => '', 'password' => '']);
    }

    public function testLosTextosNoAsciiLlevanElPrefijoN(): void
    {
        $db = $this->conexion();

        $this->assertSame("N'Ana 😀'", $db->escape('Ana 😀'));
        $this->assertSame("N'Núñez'", $db->escape('Núñez'));
        $this->assertSame("N'O''Brien Łukasz'", $db->escape("O'Brien Łukasz"));
    }

    public function testLosDemasValoresNoCambian(): void
    {
        $db = $this->conexion();

        $this->assertSame("'ana@prueba.local'", $db->escape('ana@prueba.local'));
        $this->assertSame("'O''Brien'", $db->escape("O'Brien"));
        $this->assertSame(12, $db->escape(12));
        $this->assertSame(1, $db->escape(true));
        $this->assertSame('NULL', $db->escape(null));
        $this->assertSame('GETDATE()', $db->escape(new RawSql('GETDATE()')));
        $this->assertSame(["N'ñ'", "'n'"], $db->escape(['ñ', 'n']));
    }

    public function testLaPlataformaSigueSiendoSqlsrvYTodosLosGruposUsanElControlador(): void
    {
        $this->assertSame('SQLSRV', $this->conexion()->getPlatform());
        $config = new Database();
        foreach (['default', 'tests', 'migraciones', 'copias'] as $grupo) {
            $this->assertSame('App\Database\SQLSRV', $config->{$grupo}['DBDriver'], $grupo);
        }
    }
}
