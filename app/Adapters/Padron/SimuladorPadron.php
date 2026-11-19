<?php

declare(strict_types=1);

namespace App\Adapters\Padron;

use App\Adapters\ServicioNoDisponibleException;

/**
 * Simulador de la consulta por CUIT o CUIL para desarrollo, pruebas y demostraciones (RNF-05, D-59). Responde como la
 * Central de Deudores del BCRA: solo la denominación, en mayúsculas, con el apellido primero. Según el CUIT o CUIL:
 * - los de la tabla CONOCIDOS devuelven su denominación (clientes de los datos de prueba);
 * - si los ocho dígitos centrales empiezan con 99, el servicio no responde;
 * - si empiezan con 98, la persona no está en el servicio;
 * - cualquier otro devuelve una denominación de ejemplo, siempre la misma para el mismo número.
 */
final class SimuladorPadron implements PadronInterface
{
    /** @var array<string, string> CUIT o CUIL => denominación */
    public const CONOCIDOS = [
        '27394445520' => 'DÍAZ MARTINA',
        '27394445660' => 'DÍAZ MARTINA',
        '27415551040' => 'BENÍTEZ SOFÍA',
        '20402223090' => 'ACOSTA MATEO',
    ];

    private const NOMBRES = ['SOFÍA', 'MATEO', 'VALERIA', 'BENJAMÍN', 'JULIETA', 'SANTIAGO', 'CAROLINA', 'FACUNDO'];
    private const APELLIDOS = ['GONZÁLEZ', 'FERNÁNDEZ', 'RODRÍGUEZ', 'BENÍTEZ', 'ACOSTA', 'ROMERO', 'SOSA', 'RAMÍREZ'];
    private const EMPRESAS = ['DISTRIBUIDORA DEL NORESTE', 'YERBATERA APÓSTOLES', 'TRANSPORTES MISIONES', 'AGROINSUMOS DEL SUR'];
    private const FORMAS = ['S.A.', 'S.R.L.', 'S.A.S.'];

    public function consultar(string $cuit): ?string
    {
        $centro = substr($cuit, 2, 8);
        if (str_starts_with($centro, '99')) {
            throw new ServicioNoDisponibleException('La consulta por CUIT o CUIL no respondió (simulado).');
        }
        if (str_starts_with($centro, '98')) {
            return null;
        }
        if (isset(self::CONOCIDOS[$cuit])) {
            return self::CONOCIDOS[$cuit];
        }
        $h = crc32($cuit);
        if (in_array(substr($cuit, 0, 2), ['30', '33', '34'], true)) {
            return self::EMPRESAS[$h % count(self::EMPRESAS)] . ' ' . self::FORMAS[intdiv($h, 8) % count(self::FORMAS)];
        }

        return self::APELLIDOS[intdiv($h, 8) % count(self::APELLIDOS)] . ' ' . self::NOMBRES[$h % count(self::NOMBRES)];
    }
}
