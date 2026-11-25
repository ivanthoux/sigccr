<?php

declare(strict_types=1);

namespace Tests\Demostracion;

use App\Database\Seeds\PresentacionSeeder;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * Carga completa de los datos de la presentación sobre una base nueva: cada escenario debe completarse con las reglas
 * del sistema. Se ejecuta en la integración continua, después de las pruebas de integración, con la base recreada,
 * porque deja una campaña activa, una caja cerrada y el régimen generado, que alterarían las demás pruebas.
 */
final class PresentacionSeederTest extends IntegracionTestCase
{
    public function testCargaTodosLosEscenarios(): void
    {
        foreach (['AnalistaCredito', 'Cajero', 'Gerente'] as $rol) {
            $this->fabrica->usuario($rol);
        }
        putenv('SIGCCR_DB_GRUPO=migraciones');
        try {
            $seeder = new PresentacionSeeder(config('Database'));
            $seeder->run();
        } finally {
            putenv('SIGCCR_DB_GRUPO');
        }

        $omitidos = array_filter($seeder->resultados(), static fn ($r) => !str_starts_with($r[1], 'OK'));
        $this->assertCount(8, $seeder->resultados());
        $this->assertSame([], array_values($omitidos), implode("\n", array_map(static fn ($r) => "{$r[0]}: {$r[1]}", $omitidos)));
        $this->seeInDatabase('creditos', ['estado' => 'Refinanciado']);
        $this->seeInDatabase('campanas', ['estado' => 'Activa']);
        $this->seeInDatabase('pre_registros', ['estado' => 'PendienteValidacion']);
        $this->seeInDatabase('solicitudes_credito', ['estado' => 'PendienteDocumentacion']);
        $this->seeInDatabase('solicitudes_credito', ['estado' => 'AprobadaPendienteLiquidacion']);
    }
}
