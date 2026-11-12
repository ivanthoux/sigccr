<?php

declare(strict_types=1);

namespace App\Adapters\Padron;

use App\Adapters\ServicioNoDisponibleException;

/**
 * Simulador del padrón de ARCA para desarrollo, pruebas y demostraciones (RNF-05). Responde según el CUIL:
 * - los CUIL de la tabla CONOCIDOS devuelven sus datos (clientes de los datos de prueba);
 * - un DNI que empieza con 99 simula el servicio sin respuesta;
 * - un DNI que empieza con 98 no tiene datos en el padrón;
 * - cualquier otro devuelve un nombre y un domicilio de ejemplo, siempre los mismos para el mismo CUIL.
 * El documento es siempre el DNI contenido en el CUIL.
 */
final class SimuladorPadron implements PadronInterface
{
    /** @var array<string, array{0: string, 1: string, 2: string}> CUIL => nombre, apellido, domicilio fiscal */
    public const CONOCIDOS = [
        '27394445520' => ['Martina', 'Díaz', 'Av. San Martín 2020, Apóstoles, Misiones'],
        '27394445660' => ['Martina', 'Díaz', 'Av. San Martín 2020, Apóstoles, Misiones'],
    ];

    private const NOMBRES = ['Sofía', 'Mateo', 'Valeria', 'Benjamín', 'Julieta', 'Santiago', 'Carolina', 'Facundo'];
    private const APELLIDOS = ['González', 'Fernández', 'Rodríguez', 'Benítez', 'Acosta', 'Romero', 'Sosa', 'Ramírez'];
    private const CALLES = ['Belgrano', 'Sarmiento', 'Rivadavia', 'Mitre', 'Colón', 'Alem'];

    public function consultar(string $cuil): ?array
    {
        $dni = ltrim(substr($cuil, 2, 8), '0');
        if (str_starts_with(substr($cuil, 2, 8), '99')) {
            throw new ServicioNoDisponibleException('El padrón de ARCA no respondió (simulado).');
        }
        if (str_starts_with(substr($cuil, 2, 8), '98')) {
            return null;
        }
        if (isset(self::CONOCIDOS[$cuil])) {
            [$nombre, $apellido, $domicilio] = self::CONOCIDOS[$cuil];
        } else {
            $h = crc32($cuil);
            $nombre = self::NOMBRES[$h % count(self::NOMBRES)];
            $apellido = self::APELLIDOS[intdiv($h, 8) % count(self::APELLIDOS)];
            $domicilio = self::CALLES[intdiv($h, 64) % count(self::CALLES)] . ' ' . (100 + $h % 1900) . ', Apóstoles, Misiones';
        }

        return ['nombre' => $nombre, 'apellido' => $apellido, 'tipo_documento' => 'DNI', 'nro_documento' => $dni, 'domicilio_fiscal' => $domicilio];
    }
}
