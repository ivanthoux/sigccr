<?php

declare(strict_types=1);

namespace Tests\Unit\Seguridad;

use App\Libraries\Seguridad\ReglasUsuarios;
use PHPUnit\Framework\TestCase;

/** Reglas de los usuarios internos (RN-04, RN-05, RN-06; CU-08 a CU-10). */
final class ReglasUsuariosTest extends TestCase
{
    public function testNoSePuedeDesactivarLaPropiaCuenta(): void
    {
        $m = ReglasUsuarios::motivosContraDesactivar(7, 7, 'Gerente', 'Habilitada', 2, []);
        $this->assertSame(['No puede desactivar su propia cuenta.'], $m);
    }

    public function testNoSePuedeDesactivarAlUltimoAdministrador(): void
    {
        $this->assertCount(1, ReglasUsuarios::motivosContraDesactivar(1, 2, 'Administrador', 'Habilitada', 1, []));
        $this->assertSame([], ReglasUsuarios::motivosContraDesactivar(1, 2, 'Administrador', 'Habilitada', 2, []));
        // Un Administrador pendiente de activación no sostiene la administración: puede desactivarse
        $this->assertSame([], ReglasUsuarios::motivosContraDesactivar(1, 2, 'Administrador', 'PendienteActivacion', 1, []));
    }

    public function testResponsabilidadesPendientesImpidenLaDesactivacion(): void
    {
        $m = ReglasUsuarios::motivosContraDesactivar(1, 3, 'Cajero', 'Habilitada', 1, ['una jornada de caja abierta', 'una caja asignada']);
        $this->assertSame(['Tiene pendiente: una jornada de caja abierta.', 'Tiene pendiente: una caja asignada.'], $m);
        $this->assertSame(['El usuario ya está desactivado.'], ReglasUsuarios::motivosContraDesactivar(1, 3, 'Cajero', 'Desactivada', 1, []));
    }

    public function testCambioDeRol(): void
    {
        $this->assertNull(ReglasUsuarios::motivosContraCambiarRol('Gerente', 'Gerente', 'Habilitada', 2, false, false));
        $this->assertSame([], ReglasUsuarios::motivosContraCambiarRol('Gerente', 'Cajero', 'Habilitada', 1, false, false));
        $this->assertCount(1, ReglasUsuarios::motivosContraCambiarRol('Administrador', 'Gerente', 'Habilitada', 1, false, false));
        $this->assertCount(2, ReglasUsuarios::motivosContraCambiarRol('Cajero', 'AnalistaCredito', 'Habilitada', 1, true, true));
        $this->assertSame(['Seleccione un rol válido.'], ReglasUsuarios::motivosContraCambiarRol('Cajero', 'Cliente', 'Habilitada', 1, false, false));
    }

    public function testEstadoAlReactivar(): void
    {
        $this->assertSame('Habilitada', ReglasUsuarios::estadoAlReactivar(true));
        $this->assertSame('PendienteActivacion', ReglasUsuarios::estadoAlReactivar(false));
    }
}
