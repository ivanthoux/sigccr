<?php

declare(strict_types=1);

namespace App\Libraries\Clientes;

use App\Libraries\Validacion\Campos;

/**
 * CU-104 pasos 2 a 4 (A4): datos del pre-registro de una persona humana. La unicidad del documento, del CUIL y del
 * correo frente a los clientes la verifica el servicio contra la base (A1, A2).
 */
final class ValidadorPreRegistro
{
    /**
     * @param array<string, mixed> $d
     * @return array{nombre: string, apellido: string, tipo_documento: string, nro_documento: string, cuit_cuil: string, correo: string, telefono: string, acepta_politica: bool}
     */
    public static function normalizar(array $d): array
    {
        return [
            'nombre' => Campos::texto($d['nombre'] ?? ''),
            'apellido' => Campos::texto($d['apellido'] ?? ''),
            'tipo_documento' => Campos::texto($d['tipo_documento'] ?? '') ?: 'DNI',
            'nro_documento' => Campos::documento($d['nro_documento'] ?? ''),
            'cuit_cuil' => str_replace(['-', ' ', '.'], '', Campos::texto($d['cuit_cuil'] ?? '')),
            'correo' => Campos::correo($d['correo'] ?? ''),
            'telefono' => Campos::texto($d['telefono'] ?? ''),
            'acepta_politica' => !empty($d['acepta_politica']),
        ];
    }

    /**
     * @param array<string, mixed> $d datos normalizados
     * @return array<string, string> campo => mensaje
     */
    public static function validar(array $d): array
    {
        $r = [
            'nombre' => [Campos::obligatorio($d['nombre'], 'el nombre'), Campos::nombrePersona($d['nombre'], 'el nombre')],
            'apellido' => [Campos::obligatorio($d['apellido'], 'el apellido'), Campos::nombrePersona($d['apellido'], 'el apellido')],
            'tipo_documento' => [Campos::tipoDocumento($d['tipo_documento'])],
            'nro_documento' => [Campos::obligatorio($d['nro_documento'], 'el número de documento'), Campos::numeroDocumento($d['tipo_documento'], $d['nro_documento'])],
            'cuit_cuil' => [
                Campos::obligatorio($d['cuit_cuil'], 'el CUIL'),
                Campos::cuit($d['cuit_cuil'], 'PersonaHumana'),
                $d['cuit_cuil'] !== '' && $d['tipo_documento'] === 'DNI' && Campos::numeroDocumento('DNI', $d['nro_documento']) === null
                    && Campos::cuit($d['cuit_cuil'], 'PersonaHumana') === null && !Campos::cuilCorrespondeADni($d['cuit_cuil'], $d['nro_documento'])
                    ? 'El CUIL no corresponde al número de documento.' : null,
            ],
            'correo' => [Campos::obligatorio($d['correo'], 'el correo electrónico'), Campos::formatoCorreo($d['correo'])],
            'telefono' => [Campos::telefono($d['telefono'])],
            // A4: la aceptación de la política de tratamiento de datos es obligatoria (RN-64, Ley 25.326)
            'acepta_politica' => [$d['acepta_politica'] ? null : 'Para continuar, acepte la política de tratamiento de datos personales.'],
        ];
        $errores = [];
        foreach ($r as $campo => $mensajes) {
            foreach ($mensajes as $m) {
                if ($m !== null) {
                    $errores[$campo] = $m;
                    break;
                }
            }
        }

        return $errores;
    }
}
