<?php

declare(strict_types=1);

namespace App\Libraries\Solicitudes;

use App\Libraries\Validacion\Campos;

/**
 * CU-82 Actualizar Calendario Operativo (RN-49): validación de los días no hábiles que se agregan o se dan de baja.
 * No se aceptan fechas pasadas, porque alterarían cómputos ya realizados (A1), ni fechas ya registradas (A2). La carga
 * anual (A3) recibe una fecha por línea con su descripción y, opcionalmente, su tipo: «dd/mm/aaaa; descripción; tipo».
 */
final class CalendarioOperativo
{
    public const TIPOS = ['Feriado' => 'Feriado nacional', 'NoLaborable' => 'Día no laborable', 'CierreEntidad' => 'Día no hábil de la entidad'];

    /**
     * @param list<array{fecha?: string, descripcion?: string, tipo?: string}> $filas
     * @param list<string> $registradas fechas ya cargadas (aaaa-mm-dd)
     * @return array{validas: list<array{fecha: string, descripcion: string, tipo: string}>, rechazadas: list<string>}
     */
    public static function validar(array $filas, string $hoy, array $registradas): array
    {
        $validas = [];
        $rechazadas = [];
        foreach ($filas as $i => $f) {
            $texto = trim((string) ($f['fecha'] ?? ''));
            $descripcion = Campos::texto($f['descripcion'] ?? '');
            $tipo = (string) ($f['tipo'] ?? '');
            if ($texto === '' && $descripcion === '') {
                continue;
            }
            $fecha = Campos::fecha($texto);
            $etiqueta = $texto === '' ? 'Fila ' . ($i + 1) : $texto;
            $motivo = match (true) {
                $fecha === null => 'la fecha no es válida (dd/mm/aaaa)',
                $fecha < $hoy => 'es una fecha pasada (A1)',
                in_array($fecha, $registradas, true) || in_array($fecha, array_column($validas, 'fecha'), true) => 'ya está registrada (A2)',
                $descripcion === '' || mb_strlen($descripcion) > 150 => 'falta la descripción (hasta 150 caracteres)',
                !isset(self::TIPOS[$tipo]) => 'el tipo no es válido',
                default => null,
            };
            if ($motivo !== null) {
                $rechazadas[] = "{$etiqueta}: {$motivo}";
                continue;
            }
            $validas[] = ['fecha' => (string) $fecha, 'descripcion' => $descripcion, 'tipo' => $tipo];
        }

        return ['validas' => $validas, 'rechazadas' => $rechazadas];
    }

    /**
     * A3: líneas «dd/mm/aaaa; descripción; tipo» (el tipo puede omitirse y se usa el indicado para la carga).
     *
     * @return list<array{fecha: string, descripcion: string, tipo: string}>
     */
    public static function filasDeTexto(string $texto, string $tipoPorDefecto): array
    {
        $filas = [];
        foreach (preg_split('/\R/', $texto) ?: [] as $linea) {
            if (trim($linea) === '') {
                continue;
            }
            $partes = array_map('trim', explode(';', $linea));
            $tipo = $partes[2] ?? '';
            $porNombre = array_search(mb_strtolower($tipo), array_map('mb_strtolower', self::TIPOS), true);
            $filas[] = [
                'fecha' => $partes[0],
                'descripcion' => $partes[1] ?? '',
                'tipo' => $tipo === '' ? $tipoPorDefecto : (isset(self::TIPOS[$tipo]) ? $tipo : (string) ($porNombre ?: $tipo)),
            ];
        }

        return $filas;
    }
}
