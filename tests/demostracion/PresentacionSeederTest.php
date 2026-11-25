<?php

declare(strict_types=1);

namespace Tests\Demostracion;

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
        // Como se ejecuta en una instalación: spark con el propietario del esquema (los usuarios de cada rol ya existen)
        exec('env SIGCCR_BASE_PRUEBAS=1 SIGCCR_DB_GRUPO=migraciones php spark db:seed PresentacionSeeder 2>&1', $salida, $codigo);
        $texto = implode("\n", $salida);
        fwrite(STDERR, $texto . "\n");

        $this->assertSame(0, $codigo, $texto);
        $this->assertStringContainsString('Datos de la presentación cargados.', $texto);
        $this->assertStringNotContainsString('✗', $texto, 'Hay escenarios omitidos');
        $this->assertDoesNotMatchRegularExpression('/^\[[A-Za-z\\\\]*Exception\]/m', $texto);
        $this->seeInDatabase('creditos', ['estado' => 'Refinanciado']);
        $this->seeInDatabase('campanas', ['estado' => 'Activa']);
        $this->seeInDatabase('pre_registros', ['estado' => 'PendienteValidacion']);
        $this->seeInDatabase('solicitudes_credito', ['estado' => 'PendienteDocumentacion']);
        $this->seeInDatabase('solicitudes_credito', ['estado' => 'AprobadaPendienteLiquidacion']);
    }
}
