<?php

declare(strict_types=1);

namespace App\Libraries\Solicitudes;

use App\Libraries\Validacion\Campos;

/**
 * Verificación del legajo de una solicitud (CU-23; RN-11, RN-19).
 *
 * El Analista indica el resultado de cada documento presentado (conforme, observado o rechazado, con el motivo de la
 * observación) y el resultado de la revisión. La revisión solo puede quedar Verificada si cada documento exigido por
 * la versión de condiciones para la categoría del cliente está presente y conforme; Observada exige el detalle de lo
 * que debe corregirse; Rechazada, el motivo del rechazo documental, que se distingue del crediticio (RN-19).
 */
final class RevisionLegajo
{
    public const RESULTADOS = ['Verificada', 'Observada', 'Rechazada'];
    public const RESULTADOS_DOCUMENTO = ['Conforme' => 'Conforme', 'Observado' => 'Observado', 'Rechazado' => 'Rechazado'];
    public const MOTIVOS = [
        'Faltante' => 'Faltante',
        'Ilegible' => 'Ilegible',
        'Vencido' => 'Vencido',
        'Inconsistente' => 'Inconsistente con los datos del cliente',
        'NoCorresponde' => 'No corresponde al tipo exigido',
    ];

    /**
     * @param list<string> $exigidos códigos de documentación exigidos (RequisitosDocumentales::exigidosPorLinea)
     * @param array<string, list<int>> $presentados código => documentos vigentes del legajo de ese tipo
     * @param array<int|string, array{resultado?: string, motivo?: string, observacion?: string}> $verificaciones por documento
     * @return array{errores: array<string, string>, faltantes: list<string>, verificaciones: array<int, array{resultado: string, motivo: ?string, observacion: ?string}>}
     */
    public static function validar(array $exigidos, array $presentados, array $verificaciones, string $resultado, string $observaciones, string $motivoRechazo): array
    {
        $errores = [];
        $normalizadas = [];
        foreach ($presentados as $ids) {
            foreach ($ids as $docId) {
                $v = $verificaciones[$docId] ?? [];
                $res = (string) ($v['resultado'] ?? '');
                $motivo = Campos::texto($v['motivo'] ?? '');
                $obs = Campos::texto($v['observacion'] ?? '');
                if (!isset(self::RESULTADOS_DOCUMENTO[$res])) {
                    $errores["doc_{$docId}"] = 'Indique el resultado de este documento.';
                    continue;
                }
                if ($res !== 'Conforme' && !isset(self::MOTIVOS[$motivo])) {
                    $errores["doc_{$docId}"] = 'Indique el motivo de la observación del documento.';
                } elseif (mb_strlen($obs) > 500) {
                    $errores["doc_{$docId}"] = 'La observación admite hasta 500 caracteres.';
                }
                $normalizadas[(int) $docId] = ['resultado' => $res, 'motivo' => $res === 'Conforme' ? null : $motivo, 'observacion' => $obs === '' ? null : $obs];
            }
        }
        $conforme = static fn (int $id): bool => ($normalizadas[$id]['resultado'] ?? '') === 'Conforme';
        $faltantes = array_values(array_filter($exigidos, static fn (string $c) => ($presentados[$c] ?? []) === []));
        $exigidosConformes = $faltantes === [] && array_reduce($exigidos, static fn (bool $ok, string $c) => $ok
            && array_filter($presentados[$c], static fn (int $id) => !$conforme($id)) === [], true);
        $todoConforme = $exigidosConformes && array_filter(array_merge([], ...array_values($presentados)), static fn (int $id) => !$conforme($id)) === [];

        $texto = Campos::texto($observaciones);
        if (!in_array($resultado, self::RESULTADOS, true)) {
            $errores['resultado'] = 'Seleccione el resultado de la revisión: verificada, observada o rechazada.';
        } elseif ($resultado === 'Verificada' && !$exigidosConformes) {
            $errores['resultado'] = $faltantes !== []
                ? 'No puede verificarse: falta documentación exigida por la línea (RN-11, RN-19).'
                : 'No puede verificarse: hay documentación exigida que no está conforme.';
        } elseif ($resultado === 'Observada') {
            if ($todoConforme) {
                $errores['resultado'] = 'Toda la documentación está conforme: corresponde verificar el legajo.';
            } elseif (mb_strlen($texto) < 10) {
                $errores['observaciones'] = 'Detalle los faltantes o inconsistencias que el cliente debe corregir (al menos 10 caracteres).';
            } elseif (mb_strlen($texto) > 1000) {
                $errores['observaciones'] = 'Las observaciones admiten hasta 1000 caracteres.';
            }
        } elseif ($resultado === 'Rechazada' && ($m = Campos::motivo(Campos::texto($motivoRechazo))) !== null) {
            $errores['motivo_rechazo'] = 'Indique el motivo del rechazo documental (RN-19). ' . $m;
        }

        return ['errores' => $errores, 'faltantes' => $faltantes, 'verificaciones' => $normalizadas];
    }

    /** Estado de la solicitud que corresponde al resultado de la revisión (CU-23 paso 7, A1, A2). */
    public static function estadoSolicitud(string $resultado): string
    {
        return match ($resultado) {
            'Verificada' => 'PendienteEvaluacion',
            'Observada' => 'PendienteDocumentacion',
            default => 'Rechazada',
        };
    }
}
