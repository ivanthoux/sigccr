<?php

declare(strict_types=1);

namespace Tests\Unit\Alertas;

use App\Libraries\Alertas\OperacionAlerta;
use App\Libraries\Seguridad\CatalogoPermisos;
use PHPUnit\Framework\TestCase;

/** «Ir a la operación» de una alerta según el objeto, el rol y las rutas construidas (CU-83 pasos 6 y 7, A3). */
final class OperacionAlertaTest extends TestCase
{
    private const RUTAS = ['admin/clientes/(:num)', 'admin/lineas/(:num)', 'admin/usuarios/(:num)'];

    public function testLlevaAlObjetoCuandoElRolPuede(): void
    {
        $op = OperacionAlerta::para('clientes', '15', CatalogoPermisos::permisosDelRol('AnalistaCredito'), self::RUTAS);
        $this->assertSame(['texto' => 'Ver el cliente', 'url' => 'admin/clientes/15'], $op);
    }

    public function testA3SinPermisoSoloSeMuestraLaInformacion(): void
    {
        // El Cajero no consulta líneas de crédito
        $this->assertNull(OperacionAlerta::para('lineas_credito', '3', CatalogoPermisos::permisosDelRol('Cajero'), self::RUTAS));
    }

    public function testOperacionDeUnaIteracionPosteriorNoSeOfreceHastaTenerRuta(): void
    {
        $permisos = CatalogoPermisos::permisosDelRol('AnalistaCredito');
        $this->assertNull(OperacionAlerta::para('solicitudes_credito', '8', $permisos, self::RUTAS));
        $this->assertSame('admin/solicitudes/8', OperacionAlerta::para('solicitudes_credito', '8', $permisos, [...self::RUTAS, 'admin/solicitudes/(:num)'])['url'] ?? null);
    }

    public function testObjetosSinPantalla(): void
    {
        $permisos = CatalogoPermisos::permisosDelRol('Administrador');
        $this->assertNull(OperacionAlerta::para('comunicaciones', '12', $permisos, self::RUTAS));
        $this->assertNull(OperacionAlerta::para(null, null, $permisos, self::RUTAS));
        $this->assertNull(OperacionAlerta::para('clientes', 'x1', $permisos, self::RUTAS));
    }
}
