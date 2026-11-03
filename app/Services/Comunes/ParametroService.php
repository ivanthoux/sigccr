<?php

declare(strict_types=1);

namespace App\Services\Comunes;

use App\Exceptions\ReglaNegocioException;
use App\Libraries\Solicitudes\EstadosSolicitud;
use App\Services\BaseService;

/**
 * Parámetros de negocio versionados (RN-15, CU-51, CU-58 a CU-60). Cada operación guarda el id de la versión aplicada.
 */
class ParametroService extends BaseService
{
    /**
     * Versión vigente de un grupo en un instante (por defecto, ahora).
     *
     * @return array{id: int, numero: int, valores: array<string, mixed>}
     */
    public function vigente(string $tipo, ?string $instanteUtc = null): array
    {
        $fila = $this->db->table('versiones_parametros')
            ->where('tipo', $tipo)
            ->where('vigente_desde <=', $instanteUtc ?? $this->ahoraUtc())
            ->orderBy('vigente_desde', 'DESC')->orderBy('numero', 'DESC')
            ->get(1)->getRowArray();
        if ($fila === null) {
            throw new ReglaNegocioException("No hay parámetros vigentes de tipo {$tipo}.");
        }

        return [
            'id' => (int) $fila['id'],
            'numero' => (int) $fila['numero'],
            'valores' => json_decode((string) $fila['valores'], true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * Versiones de un grupo, de la más reciente a la más antigua (CU-58 a CU-60: la configuración anterior se conserva).
     *
     * @return list<array{id: int, numero: int, vigente_desde: string, motivo: ?string, usuario: ?string}>
     */
    public function historial(string $tipo, int $limite = 20): array
    {
        return $this->db->table('versiones_parametros v')
            ->select("v.id, v.numero, v.vigente_desde, v.motivo, u.apellido + ', ' + u.nombre AS usuario")
            ->join('usuarios_internos u', 'u.id = v.usuario_id', 'left')
            ->where('v.tipo', $tipo)->orderBy('v.numero', 'DESC')->get($limite)->getResultArray();
    }

    /** D-24: solicitudes que no finalizaron (siguen en trámite y conservan los parámetros con que se evaluaron o estructuraron). */
    public function solicitudesEnTramite(): int
    {
        return $this->db->table('solicitudes_credito')->whereNotIn('estado', EstadosSolicitud::FINALES)->countAllResults();
    }

    /**
     * Nueva versión de un grupo de parámetros con fecha de entrada en vigencia no retroactiva (CU-58 a CU-60 pasos 5
     * a 7). Si la fecha es hoy, rige desde el instante del registro; si es futura, desde las 00:00 locales de ese día.
     * Las operaciones en curso conservan la versión con la que se iniciaron (RN-15, RN-20).
     *
     * @param array<string, mixed> $valores
     * @return array{id: int, numero: int, vigente_desde: string}
     */
    public function registrarVersion(string $tipo, array $valores, string $fecha, string $motivo, int $usuarioId): array
    {
        $hoy = $this->hoyNegocio();
        if ($fecha < $hoy) {
            throw new ReglaNegocioException('La nueva configuración no puede regir desde una fecha anterior a hoy: no se admite retroactividad.');
        }

        return $this->transaccion(function () use ($tipo, $valores, $fecha, $hoy, $motivo, $usuarioId): array {
            $numero = (int) $this->db->query('SELECT ISNULL(MAX(numero), 0) + 1 AS n FROM dbo.versiones_parametros WITH (UPDLOCK, HOLDLOCK) WHERE tipo = ?', [$tipo])->getRow()->n;
            $vigenteDesde = $fecha === $hoy ? $this->ahoraUtc()
                : \CodeIgniter\I18n\Time::parse($fecha . ' 00:00:00', config('Sigccr')->zonaNegocio)->setTimezone('UTC')->format('Y-m-d H:i:s.vP');
            $this->db->table('versiones_parametros')->insert([
                'tipo' => $tipo, 'numero' => $numero, 'vigente_desde' => $vigenteDesde,
                'valores' => json_encode($valores, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'motivo' => $motivo, 'usuario_id' => $usuarioId, 'creado_en' => $this->ahoraUtc(),
            ]);
            $id = (int) $this->db->insertID();
            (new AuditoriaService($this->db))->registrar('parametros.' . strtolower($tipo), 'versiones_parametros', $id, 'Exitoso', $motivo, ['numero' => $numero, 'vigente_desde' => $vigenteDesde]);

            return ['id' => $id, 'numero' => $numero, 'vigente_desde' => $vigenteDesde];
        });
    }
}
