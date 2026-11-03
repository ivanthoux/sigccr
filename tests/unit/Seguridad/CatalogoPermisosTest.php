<?php

declare(strict_types=1);

namespace Tests\Unit\Seguridad;

use App\Libraries\Seguridad\CatalogoPermisos;
use PHPUnit\Framework\TestCase;

/** Catálogo de permisos y matriz de participación por actor (3.6.2, RN-01, RN-06). */
final class CatalogoPermisosTest extends TestCase
{
    /** Texto de la matriz 3.6.2 tal como figura en el documento (v9: CU-99 para el Analista, D-10; CU-100 y CU-101 del Gerente, D-25 y D-26). */
    private const MATRIZ_DOCUMENTO = [
        'Administrador' => 'CU-01 a CU-03; CU-05 a CU-10; CU-53; CU-54; CU-57; CU-61 a CU-63; CU-83',
        'Gerente' => 'CU-01 a CU-03; CU-13 a CU-20; CU-24; CU-26; CU-28 a CU-30; CU-32; CU-37; CU-41 a CU-44; CU-50; CU-51; '
            . 'CU-54 a CU-60; CU-65 a CU-73; CU-77; CU-80 a CU-83; CU-85; CU-96; CU-100; CU-101',
        'AnalistaCredito' => 'CU-01 a CU-03; CU-11 a CU-15; CU-20 a CU-25; CU-28 a CU-30; CU-39; CU-40; CU-42; CU-43; CU-50; '
            . 'CU-54; CU-64; CU-73; CU-74; CU-79; CU-83; CU-84; CU-96; CU-99',
        'Cajero' => 'CU-01 a CU-03; CU-27; CU-29; CU-31 a CU-36; CU-38; CU-42; CU-43; CU-54; CU-56; CU-74; CU-76; CU-78; '
            . 'CU-83; CU-97',
    ];

    /** @return list<int> */
    private static function expandir(string $texto): array
    {
        $casos = [];
        foreach (explode(';', $texto) as $parte) {
            if (preg_match('/CU-(\d+) a CU-(\d+)/', $parte, $m)) {
                $casos = [...$casos, ...range((int) $m[1], (int) $m[2])];
            } elseif (preg_match('/CU-(\d+)/', $parte, $m)) {
                $casos[] = (int) $m[1];
            }
        }
        // CU-01 a CU-03 no requieren permiso
        $casos = array_values(array_diff($casos, [1, 2, 3]));
        sort($casos);

        return $casos;
    }

    public function testCadaRolReproduceLaMatrizDelDocumento(): void
    {
        foreach (self::MATRIZ_DOCUMENTO as $rol => $texto) {
            $this->assertSame(self::expandir($texto), CatalogoPermisos::casosDeUso($rol), "Rol {$rol}");
        }
    }

    public function testTodosLosCasosDeLaMatrizTienenPermiso(): void
    {
        foreach (array_keys(CatalogoPermisos::MATRIZ) as $rol) {
            foreach (CatalogoPermisos::casosDeUso($rol) as $cu) {
                $this->assertTrue(isset(CatalogoPermisos::PERMISOS[$cu]), "CU-{$cu} de {$rol} sin permiso");
            }
        }
    }

    public function testCadaPermisoLoUsaAlMenosUnRol(): void
    {
        $usados = [];
        foreach (array_keys(CatalogoPermisos::MATRIZ) as $rol) {
            $usados = [...$usados, ...CatalogoPermisos::casosDeUso($rol)];
        }
        foreach (array_keys(CatalogoPermisos::PERMISOS) as $cu) {
            $this->assertTrue(in_array($cu, $usados, true), "CU-{$cu} no asignado a ningún rol");
        }
    }

    public function testCodigosUnicosYConFormatoArea(): void
    {
        $codigos = array_column(CatalogoPermisos::PERMISOS, 0);
        $this->assertSame(count($codigos), count(array_unique($codigos)));
        foreach ($codigos as $c) {
            $this->assertTrue(preg_match('/^[a-z-]+\.[a-z-]+$/', $c) === 1, "Código inválido: {$c}");
        }
    }

    public function testSeparacionDeFunciones(): void
    {
        // El Administrador no opera crédito ni caja; el Cajero no resuelve ni configura (RN-06)
        $admin = CatalogoPermisos::permisosDelRol('Administrador');
        $this->assertFalse(in_array('cobros.registrar', $admin, true));
        $this->assertFalse(in_array('solicitudes.resolver', $admin, true));
        $cajero = CatalogoPermisos::permisosDelRol('Cajero');
        $this->assertTrue(in_array('cobros.registrar', $cajero, true));
        $this->assertFalse(in_array('solicitudes.resolver', $cajero, true));
        $this->assertFalse(in_array('parametros.morosidad', $cajero, true));
        $this->assertSame('CU-11 Registrar Cliente', CatalogoPermisos::descripcion(11));
    }
}
