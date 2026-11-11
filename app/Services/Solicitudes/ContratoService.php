<?php

declare(strict_types=1);

namespace App\Services\Solicitudes;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Clientes\ArchivoLegajo;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Solicitudes\Contrato;
use App\Services\BaseService;
use App\Services\Clientes\LegajoService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ParametroService;
use Throwable;

/**
 * Contrato de préstamo (SS-04): generación para cada aprobación vigente y CU-103 Registrar Contrato Firmado.
 * RN-25, RN-78, RN-79, D-31.
 */
class ContratoService extends BaseService
{
    /**
     * RN-78: contrato de la aprobación vigente de la solicitud. Se genera al aprobarse; si la aprobación es anterior a
     * D-31, se genera al consultarlo. Debe llamarse con la aprobación ya registrada.
     *
     * @return array<string, mixed> fila de contratos_prestamo
     */
    public function generar(int $solicitudId): array
    {
        $r = $this->db->query("SELECT id FROM dbo.resoluciones WHERE solicitud_id = ? AND decision = 'Aprobada' AND vigente = 1", [$solicitudId])->getRowArray();
        if ($r === null) {
            throw new ReglaNegocioException('La solicitud no tiene una aprobación vigente: no hay contrato que generar.');
        }
        $c = $this->db->table('contratos_prestamo')->where('resolucion_id', (int) $r['id'])->get()->getRowArray();
        if ($c === null) {
            $this->db->table('contratos_prestamo')->insert(['solicitud_id' => $solicitudId, 'resolucion_id' => (int) $r['id'], 'fecha_generacion' => $this->ahoraUtc()]);
            $c = $this->db->table('contratos_prestamo')->where('id', (int) $this->db->insertID())->get()->getRowArray();
        }

        return (array) $c;
    }

    /**
     * CU-103 paso 1: solicitudes aprobadas pendientes de liquidación con el estado de su contrato.
     *
     * @return list<array<string, mixed>>
     */
    public function pendientes(string $texto = ''): array
    {
        $filas = (new LiquidacionService($this->db))->pendientes($texto);
        foreach ($filas as &$f) {
            $f['constancia'] = $this->db->query(
                "SELECT k.fecha_recepcion FROM dbo.contratos_prestamo k JOIN dbo.resoluciones r ON r.id = k.resolucion_id AND r.vigente = 1 AND r.decision = 'Aprobada' "
                . 'WHERE k.solicitud_id = ? AND k.documento_legajo_id IS NOT NULL',
                [(int) $f['id']],
            )->getRowArray()['fecha_recepcion'] ?? null;
        }

        return $filas;
    }

    /**
     * CU-103 paso 2: datos del contrato (RN-78).
     *
     * @return array<string, mixed>
     */
    public function obtener(int $solicitudId): array
    {
        $s = (new LiquidacionService($this->db))->datos($solicitudId);
        if ($s['estado'] !== 'AprobadaPendienteLiquidacion' && $s['liquidacion'] === null) {
            throw new ReglaNegocioException('La solicitud no está aprobada pendiente de liquidación: no tiene un contrato para firmar.');
        }
        if ($s['importe_aprobado'] === null) {
            throw new ReglaNegocioException('La solicitud no tiene una aprobación vigente.');
        }
        $s['contrato'] = $s['liquidacion'] === null ? $this->transaccion(fn (): array => $this->generar($solicitudId))
            : $this->db->table('contratos_prestamo')->where('solicitud_id', $solicitudId)->orderBy('id', 'DESC')->get(1)->getRowArray();
        $s['cliente'] = $this->db->query(
            'SELECT c.domicilio, c.correo, c.codigo_postal, l.nombre AS localidad, p.nombre AS provincia FROM dbo.clientes c '
            . 'LEFT JOIN dbo.localidades l ON l.id = c.localidad_id LEFT JOIN dbo.provincias p ON p.id = l.provincia_id WHERE c.id = ?',
            [(int) $s['cliente_id']],
        )->getRowArray();
        $s['fecha_aprobacion'] = $this->db->table('resoluciones')->select('fecha')->where('id', (int) $s['resolucion_id'])->get()->getRow()->fecha ?? null;
        $morosidad = (new ParametroService($this->db))->vigente('Morosidad')['valores'];
        $s['punitorios'] = Contrato::punitorios($morosidad, Decimal::of((string) $s['tna']));
        $s['entidad'] = ['razon_social' => config('Sigccr')->entidadRazonSocial, 'cuit' => config('Sigccr')->entidadCuit, 'domicilio' => config('Sigccr')->entidadDomicilio];

        return $s;
    }

    /**
     * CU-103 pasos 4 a 6 (A1 a A3): copia escaneada de la constancia firmada y fecha de recepción del contrato.
     *
     * @param array{nombre: string, temporal: string, tamano: int}|null $archivo
     */
    public function registrarConstancia(int $solicitudId, ?array $archivo, string $fechaTexto, int $usuarioId): void
    {
        $errores = [];
        $doc = null;
        if ($archivo === null) {
            $errores['constancia'] = 'Adjunte la copia escaneada de la constancia firmada (PDF, JPG o PNG).';
        } else {
            $mime = is_file($archivo['temporal']) ? (string) (new \finfo(FILEINFO_MIME_TYPE))->file($archivo['temporal']) : '';
            $error = ArchivoLegajo::validar(ArchivoLegajo::nombreSeguro($archivo['nombre']), $archivo['tamano'], $mime);
            if ($error !== null) {
                $errores['constancia'] = $error;
            } else {
                $doc = $archivo + ['mime' => $mime];
            }
        }
        $ruta = null;
        try {
            $this->transaccion(function () use ($solicitudId, $doc, $fechaTexto, $usuarioId, &$errores, &$ruta): void {
                $s = $this->bloquear('solicitudes_credito', [$solicitudId])[0] ?? null;
                if ($s === null || $s['estado'] !== 'AprobadaPendienteLiquidacion') {
                    throw new ReglaNegocioException('La solicitud no está aprobada pendiente de liquidación: no se puede registrar la constancia (A2).');
                }
                $contrato = $this->generar($solicitudId);
                $aprobacion = (string) $this->db->table('resoluciones')->select('fecha')->where('id', (int) $contrato['resolucion_id'])->get()->getRow()->fecha;
                $fechaAprobacion = (new \DateTimeImmutable($aprobacion))->setTimezone(new \DateTimeZone(config('Sigccr')->zonaNegocio))->format('Y-m-d');
                $f = Contrato::validarRecepcion($fechaTexto, $fechaAprobacion, $this->hoyNegocio());
                if ($f['error'] !== null) {
                    $errores['fecha_recepcion'] = $f['error'];
                }
                if ($errores !== [] || $doc === null) {
                    throw new ValidacionException($errores, 'RN-78');
                }
                $tipo = $this->db->table('tipos_documentacion')->select('id')->where('codigo', Contrato::TIPO_DOCUMENTO)->get()->getRow();
                if ($tipo === null) {
                    throw new ReglaNegocioException('Falta el tipo de documento de la constancia firmada: ejecute las migraciones.');
                }
                // A3: una nueva constancia reemplaza a la anterior, que se conserva (RN-44)
                $g = (new LegajoService($this->db))->guardar((int) $s['cliente_id'], $doc + ['tipo_id' => (int) $tipo->id, 'reemplaza' => $contrato['documento_legajo_id']], $usuarioId);
                $ruta = $g['ruta'];
                $this->db->table('contratos_prestamo')->where('id', (int) $contrato['id'])->update([
                    'documento_legajo_id' => $g['id'], 'fecha_recepcion' => $f['fecha'], 'fecha_registro' => $this->ahoraUtc(), 'usuario_id' => $usuarioId,
                ]);
                (new AuditoriaService($this->db))->registrar('contratos.registrar_firmado', 'solicitudes_credito', $solicitudId, 'Exitoso', null, [
                    'contrato' => (int) $contrato['id'], 'documento' => $g['id'], 'fecha_recepcion' => $f['fecha'], 'reemplaza' => $contrato['documento_legajo_id'],
                ]);
            });
        } catch (Throwable $e) {
            if ($ruta !== null) {
                LegajoService::descartar([$ruta]);
            }
            throw $e;
        }
    }

    /** RN-25: la aprobación vigente tiene su constancia firmada en el legajo. */
    public function constanciaRegistrada(int $solicitudId): bool
    {
        return $this->db->query(
            "SELECT 1 AS si FROM dbo.contratos_prestamo k JOIN dbo.resoluciones r ON r.id = k.resolucion_id AND r.vigente = 1 AND r.decision = 'Aprobada' "
            . 'WHERE k.solicitud_id = ? AND k.documento_legajo_id IS NOT NULL',
            [$solicitudId],
        )->getRowArray() !== null;
    }
}
