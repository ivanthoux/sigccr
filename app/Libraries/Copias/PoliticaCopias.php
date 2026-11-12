<?php

declare(strict_types=1);

namespace App\Libraries\Copias;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Reglas de las copias de seguridad (CU-61 a CU-63, RNF-13, D-45): nombres de archivo, espacio necesario,
 * conservación por 30 días, manifiesto que acompaña cada archivo y estado de una restauración en curso.
 */
final class PoliticaCopias
{
    public const EXTENSION = '.sccr';
    public const CONFIRMACION = 'RESTAURAR';
    /** Margen de espacio libre además del doble del tamaño de los datos (copia sin cifrar y cifrada). */
    public const MARGEN_BYTES = 67108864;

    public const ESTADOS = [
        'Solicitada' => 'Solicitada', 'EnCurso' => 'En curso', 'Verificada' => 'Verificada', 'Fallida' => 'Fallida', 'Corrupta' => 'Corrupta', 'Depurada' => 'Depurada',
    ];
    public const ORIGENES = ['Manual' => 'Manual', 'Automatica' => 'Automática', 'PreviaRestauracion' => 'Previa a una restauración'];

    /** Estados de la restauración, que se guardan fuera de la base porque esta se reemplaza. */
    public const RESTAURACION_ACTIVA = ['Pendiente', 'EnCurso'];

    public static function nombreArchivo(DateTimeImmutable $instante, string $origen, string $sufijo): string
    {
        $o = ['Manual' => 'manual', 'Automatica' => 'automatica', 'PreviaRestauracion' => 'previa'][$origen] ?? 'copia';

        return 'sigccr_' . $instante->setTimezone(new DateTimeZone('UTC'))->format('Ymd_His') . "_{$o}_" . preg_replace('/[^a-z0-9]/', '', strtolower($sufijo)) . self::EXTENSION;
    }

    public static function nombreValido(string $archivo): bool
    {
        return preg_match('/^sigccr_\d{8}_\d{6}_[a-z]+_[a-z0-9]+\.sccr$/', $archivo) === 1;
    }

    /** CU-61 paso 4 (A2): espacio que necesita la copia: el archivo sin cifrar, el cifrado y un margen. */
    public static function espacioRequerido(int $bytesDatos, int $bytesAdjuntos): int
    {
        return 2 * ($bytesDatos + $bytesAdjuntos) + self::MARGEN_BYTES;
    }

    /**
     * RNF-13: copias verificadas con más de $dias días, sin contar la más reciente, que se conserva siempre.
     *
     * @param list<array{id: int|string, estado: string, fecha_generacion: string}> $copias
     * @return list<int>
     */
    public static function aDepurar(array $copias, DateTimeImmutable $ahora, int $dias = 30): array
    {
        $verificadas = array_values(array_filter($copias, static fn ($c) => $c['estado'] === 'Verificada'));
        usort($verificadas, static fn ($a, $b) => strcmp(self::utc($b['fecha_generacion']), self::utc($a['fecha_generacion'])));
        $limite = $ahora->modify("-{$dias} days")->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $r = [];
        foreach (array_slice($verificadas, 1) as $c) {
            if (self::utc($c['fecha_generacion']) < $limite) {
                $r[] = (int) $c['id'];
            }
        }

        return $r;
    }

    /**
     * Datos de la copia que acompañan al archivo (.json), para rehacer el catálogo después de restaurar una copia
     * anterior, que no contiene los registros de las copias posteriores.
     *
     * @param array<string, mixed> $c
     */
    public static function manifiesto(array $c): string
    {
        $campos = ['archivo', 'fecha_generacion', 'fecha_fin', 'tamano_bytes', 'origen', 'hash_verificacion', 'descripcion', 'responsable', 'incluye_adjuntos'];

        return (string) json_encode(array_intersect_key($c, array_flip($campos)) + ['version' => 1], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @return array<string, mixed>|null null si el manifiesto no es válido */
    public static function leerManifiesto(string $json): ?array
    {
        $m = json_decode($json, true);
        if (!is_array($m) || !isset($m['archivo'], $m['fecha_generacion'], $m['origen'], $m['hash_verificacion'])) {
            return null;
        }
        $valido = self::nombreValido((string) $m['archivo']) && isset(self::ORIGENES[$m['origen']]) && preg_match('/^[0-9a-f]{64}$/', (string) $m['hash_verificacion']) === 1;

        return $valido ? $m : null;
    }

    /** CU-63 paso 5 (A2): la confirmación explícita es escribir RESTAURAR. */
    public static function confirmacionValida(string $texto): bool
    {
        return strtoupper(trim($texto)) === self::CONFIRMACION;
    }

    /** @param array<string, mixed>|null $estado contenido del archivo de la restauración */
    public static function restauracionActiva(?array $estado): bool
    {
        return $estado !== null && in_array($estado['estado'] ?? '', self::RESTAURACION_ACTIVA, true);
    }

    /**
     * CU-63 paso 12: operaciones registradas después de la copia que deben reconstruirse (plan correctivo de R2).
     *
     * @param array<string, int> $conteos tipo => cantidad
     * @return list<string>
     */
    public static function posteriores(array $conteos): array
    {
        $textos = [
            'solicitudes' => ['solicitud de crédito', 'solicitudes de crédito'], 'creditos' => ['crédito liquidado', 'créditos liquidados'],
            'cobros' => ['cobro', 'cobros'], 'propuestas' => ['propuesta de refinanciación', 'propuestas de refinanciación'],
            'movimientos' => ['movimiento de fondos', 'movimientos de fondos'], 'clientes' => ['cliente registrado', 'clientes registrados'],
            'auditoria' => ['registro de auditoría', 'registros de auditoría'],
        ];
        $r = [];
        foreach ($textos as $k => [$uno, $varios]) {
            $n = (int) ($conteos[$k] ?? 0);
            if ($n > 0) {
                $r[] = $n . ' ' . ($n === 1 ? $uno : $varios);
            }
        }

        return $r;
    }

    public static function tamano(int|string|null $bytes): string
    {
        if ($bytes === null || $bytes === '') {
            return '—';
        }
        $b = (float) $bytes;
        foreach (['B', 'KB', 'MB', 'GB'] as $i => $u) {
            if ($b < 1024 || $u === 'GB') {
                return number_format($b, $i === 0 ? 0 : 1, ',', '.') . ' ' . $u;
            }
            $b /= 1024;
        }

        return '';
    }

    private static function utc(string $instante): string
    {
        return (new DateTimeImmutable($instante))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
