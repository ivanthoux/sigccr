<?php

declare(strict_types=1);

namespace App\Libraries\Validacion;

use App\Libraries\Seguridad\ReglasUsuarios;

/**
 * Datos de un usuario interno (CU-05 paso 4, CU-06 paso 5). La unicidad del correo y del documento (RN-08) la
 * verifica el servicio contra la base.
 */
final class ValidadorUsuarioInterno
{
    /**
     * @param array<string, mixed> $datos
     * @return array{nombre: string, apellido: string, correo: string, tipo_documento: string, nro_documento: string, rol: string}
     */
    public static function normalizar(array $datos): array
    {
        return [
            'nombre' => Campos::texto($datos['nombre'] ?? ''),
            'apellido' => Campos::texto($datos['apellido'] ?? ''),
            'correo' => Campos::correo($datos['correo'] ?? ''),
            'tipo_documento' => Campos::texto($datos['tipo_documento'] ?? ''),
            'nro_documento' => Campos::documento($datos['nro_documento'] ?? ''),
            'rol' => Campos::texto($datos['rol'] ?? ''),
        ];
    }

    /**
     * @param array{nombre: string, apellido: string, correo: string, tipo_documento: string, nro_documento: string, rol: string} $d datos normalizados
     * @param bool $conRol el registro exige el rol; la modificación no lo cambia (CU-10)
     * @return array<string, string> campo => mensaje; vacío si los datos son válidos
     */
    public static function validar(array $d, bool $conRol): array
    {
        $reglas = [
            'nombre' => [Campos::obligatorio($d['nombre'], 'el nombre'), Campos::nombrePersona($d['nombre'], 'el nombre')],
            'apellido' => [Campos::obligatorio($d['apellido'], 'el apellido'), Campos::nombrePersona($d['apellido'], 'el apellido')],
            'tipo_documento' => [Campos::obligatorio($d['tipo_documento'], 'el tipo de documento'), Campos::tipoDocumento($d['tipo_documento'])],
            'nro_documento' => [Campos::obligatorio($d['nro_documento'], 'el número de documento'), Campos::numeroDocumento($d['tipo_documento'], $d['nro_documento'])],
            'correo' => [Campos::obligatorio($d['correo'], 'el correo electrónico'), Campos::formatoCorreo($d['correo'])],
        ];
        if ($conRol) {
            $reglas['rol'] = [
                $d['rol'] === '' ? 'Seleccione el rol.' : null,
                $d['rol'] !== '' && !in_array($d['rol'], ReglasUsuarios::ROLES, true) ? 'Seleccione un rol válido.' : null,
            ];
        }
        $errores = [];
        foreach ($reglas as $campo => $resultados) {
            foreach ($resultados as $mensaje) {
                if ($mensaje !== null) {
                    $errores[$campo] = $mensaje;
                    break;
                }
            }
        }

        return $errores;
    }
}
