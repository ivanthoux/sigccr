<?php

declare(strict_types=1);

namespace App\Services\Clientes;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Clientes\DerechosTitular;
use App\Libraries\Validacion\Campos;
use App\Services\BaseService;
use App\Services\Comunes\AlertaService;
use App\Services\Comunes\AuditoriaService;
use CodeIgniter\I18n\Time;
use DateTimeImmutable;

/**
 * CU-108 Atender Solicitud de Derechos del Titular (D-64; Ley 25.326, arts. 14, 16 y 20; RN-85, RN-86).
 *
 * El cliente la registra en el portal o el Analista en la sucursal; el Analista (o el Gerente, si pide revisar una
 * decisión automatizada) la atiende o la deniega con su respuesta antes de la fecha límite. Queda en la auditoría
 * (RNF-14). Sin sesión de portal el cliente es siempre el que indica el controlador, nunca el recibido en la petición.
 */
class DerechosTitularService extends BaseService
{
    /** Registra la solicitud de un cliente (portal o sucursal). Devuelve su identificador. */
    public function registrar(int $clienteId, array $datos, string $canal): int
    {
        $d = [
            'tipo' => (string) ($datos['tipo'] ?? ''), 'detalle' => Campos::texto($datos['detalle'] ?? ''),
            'solicitud_id' => Campos::identificador($datos['solicitud_id'] ?? null),
        ];
        $errores = DerechosTitular::validar($d);
        if ($errores !== []) {
            throw new ValidacionException($errores);
        }

        return $this->transaccion(function () use ($clienteId, $d, $canal): int {
            $c = $this->bloquear('clientes', [$clienteId])[0] ?? null;
            if ($c === null) {
                throw new ReglaNegocioException('El cliente no existe.');
            }
            $solicitudId = null;
            if ($d['tipo'] === 'RevisionAutomatizada') {
                $solicitudId = (int) $d['solicitud_id'];
                $ok = $this->db->query(
                    "SELECT 1 FROM dbo.solicitudes_credito WHERE id = ? AND cliente_id = ? AND estado = 'Rechazada' AND tipo_rechazo = 'Crediticio'",
                    [$solicitudId, $clienteId],
                )->getRowArray();
                if ($ok === null) {
                    throw new ValidacionException(['solicitud_id' => 'Solo se puede pedir la revisión de una solicitud suya rechazada por motivos crediticios.'], 'RN-86');
                }
            }
            // RN-86: no se repite un pedido pendiente del mismo tipo (y de la misma solicitud, en la revisión)
            $repetida = $this->db->query(
                "SELECT 1 FROM dbo.solicitudes_derechos WHERE cliente_id = ? AND tipo = ? AND estado = 'Pendiente' AND ISNULL(solicitud_id, 0) = ?",
                [$clienteId, $d['tipo'], $solicitudId ?? 0],
            )->getRowArray();
            if ($repetida !== null) {
                throw new ReglaNegocioException('Ya hay un pedido pendiente de este tipo: se responderá dentro del plazo legal.', 'RN-86');
            }

            $ahora = Time::now(config('Sigccr')->zonaNegocio);
            $vence = DerechosTitular::vence(new DateTimeImmutable($ahora->format('Y-m-d H:i:s')), $d['tipo'], $this->diasNoHabiles($ahora->format('Y-m-d')));
            $this->db->table('solicitudes_derechos')->insert([
                'cliente_id' => $clienteId, 'tipo' => $d['tipo'], 'detalle' => $d['detalle'], 'solicitud_id' => $solicitudId, 'canal' => $canal,
                'estado' => 'Pendiente', 'vence' => $vence->format('Y-m-d'), 'creado_en' => $this->ahoraUtc(),
            ]);
            $id = (int) $this->db->insertID();
            (new AlertaService($this->db))->generar(
                'Operativa',
                'Solicitud de ' . mb_strtolower(DerechosTitular::TIPOS[$d['tipo']]) . ' de ' . $c['denominacion'] . ' por ' . ($canal === 'Portal' ? 'el portal' : 'la sucursal') . '.',
                'Responder antes del ' . $vence->format('d/m/Y') . ' (Ley 25.326).',
                $d['tipo'] === 'RevisionAutomatizada' ? 'Gerente' : 'AnalistaCredito',
                null,
                'solicitudes_derechos',
                $id,
            );
            (new AuditoriaService($this->db))->registrar(
                'derechos.registrar',
                'solicitudes_derechos',
                $id,
                'Exitoso',
                null,
                ['cliente_id' => $clienteId, 'tipo' => $d['tipo'], 'canal' => $canal, 'vence' => $vence->format('Y-m-d')],
            );

            return $id;
        });
    }

    /**
     * Solicitudes para el panel, las que vencen antes primero.
     *
     * @return list<array<string, mixed>>
     */
    public function buscar(string $estado = 'Pendiente', string $tipo = ''): array
    {
        $b = $this->db->table('solicitudes_derechos s')
            ->select('s.*, c.denominacion AS cliente, c.id AS cliente_ref', false)
            ->join('clientes c', 'c.id = s.cliente_id');
        if (isset(DerechosTitular::ESTADOS[$estado])) {
            $b->where('s.estado', $estado);
        }
        if (isset(DerechosTitular::TIPOS[$tipo])) {
            $b->where('s.tipo', $tipo);
        }
        $filas = $b->orderBy('s.vence', 'ASC')->orderBy('s.id', 'ASC')->limit(200)->get()->getResultArray();
        $hoy = $this->hoyNegocio();
        foreach ($filas as &$f) {
            $f['situacion'] = $f['estado'] === 'Pendiente' ? DerechosTitular::situacionPlazo(substr((string) $f['vence'], 0, 10), $hoy) : null;
        }

        return $filas;
    }

    /** @return array<string, mixed> */
    public function obtener(int $id): array
    {
        $s = $this->db->query(
            "SELECT s.*, c.denominacion AS cliente, sc.numero AS solicitud_numero, u.apellido + ', ' + u.nombre AS responsable "
            . 'FROM dbo.solicitudes_derechos s JOIN dbo.clientes c ON c.id = s.cliente_id LEFT JOIN dbo.solicitudes_credito sc ON sc.id = s.solicitud_id '
            . 'LEFT JOIN dbo.usuarios_internos u ON u.id = s.usuario_id WHERE s.id = ?',
            [$id],
        )->getRowArray();
        if ($s === null) {
            throw new ReglaNegocioException('La solicitud de derechos no existe.');
        }
        $s['situacion'] = $s['estado'] === 'Pendiente' ? DerechosTitular::situacionPlazo(substr((string) $s['vence'], 0, 10), $this->hoyNegocio()) : null;

        return $s;
    }

    /**
     * Solicitudes de un cliente, las más recientes primero (portal: el cliente de la sesión).
     *
     * @return list<array<string, mixed>>
     */
    public function delCliente(int $clienteId): array
    {
        return $this->db->query(
            'SELECT s.id, s.tipo, s.canal, s.estado, s.vence, s.creado_en, s.fecha_resolucion, s.respuesta, sc.numero AS solicitud_numero '
            . 'FROM dbo.solicitudes_derechos s LEFT JOIN dbo.solicitudes_credito sc ON sc.id = s.solicitud_id WHERE s.cliente_id = ? ORDER BY s.id DESC',
            [$clienteId],
        )->getResultArray();
    }

    /**
     * Solicitudes de crédito del cliente cuya revisión puede pedir (rechazadas por motivos crediticios).
     *
     * @return list<array{id: int, numero: int, fecha_registro: string}>
     */
    public function solicitudesRevisables(int $clienteId): array
    {
        return $this->db->query(
            "SELECT id, numero, fecha_registro FROM dbo.solicitudes_credito WHERE cliente_id = ? AND estado = 'Rechazada' AND tipo_rechazo = 'Crediticio' ORDER BY id DESC",
            [$clienteId],
        )->getResultArray();
    }

    /**
     * Atiende o deniega la solicitud con su respuesta (RN-86). La revisión de una decisión automatizada la resuelve
     * quien puede resolver solicitudes de crédito (Gerente): una persona distinta del procesamiento automático.
     */
    public function resolver(int $id, int $versionLeida, string $estado, string $respuestaTexto, int $usuarioId, bool $puedeRevisarValoraciones): void
    {
        $respuesta = Campos::texto($respuestaTexto);
        $errores = DerechosTitular::validarRespuesta($estado, $respuesta);
        if ($errores !== []) {
            throw new ValidacionException($errores);
        }
        $this->transaccion(function () use ($id, $versionLeida, $estado, $respuesta, $usuarioId, $puedeRevisarValoraciones): void {
            $s = $this->bloquear('solicitudes_derechos', [$id])[0] ?? null;
            if ($s === null || $s['estado'] !== 'Pendiente') {
                throw new ReglaNegocioException('La solicitud ya fue respondida: vuelva a abrirla desde la lista.');
            }
            if ($s['tipo'] === 'RevisionAutomatizada' && !$puedeRevisarValoraciones) {
                throw new ReglaNegocioException('La revisión de una decisión automatizada la resuelve el Gerente.', 'RN-86');
            }
            $this->actualizarConVersion('solicitudes_derechos', $id, $versionLeida, [
                'estado' => $estado, 'respuesta' => $respuesta, 'fecha_resolucion' => $this->ahoraUtc(), 'usuario_id' => $usuarioId, 'actualizado_en' => $this->ahoraUtc(),
            ]);
            (new AlertaService($this->db))->resolver('solicitudes_derechos', $id, (string) (session('cuenta')['nombre'] ?? 'Usuario'));
            (new AuditoriaService($this->db))->registrar(
                'derechos.resolver',
                'solicitudes_derechos',
                $id,
                'Exitoso',
                $respuesta,
                ['tipo' => $s['tipo'], 'resultado' => $estado, 'vencia' => substr((string) $s['vence'], 0, 10), 'en_plazo' => $this->hoyNegocio() <= substr((string) $s['vence'], 0, 10)],
            );
        });
    }

    /** @return list<string> fechas Y-m-d no hábiles desde hoy hasta dentro de un año */
    private function diasNoHabiles(string $desde): array
    {
        return array_map(
            static fn (array $f): string => substr((string) $f['fecha'], 0, 10),
            $this->db->query('SELECT fecha FROM dbo.dias_no_habiles WHERE fecha BETWEEN CAST(? AS date) AND DATEADD(day, 400, CAST(? AS date))', [$desde, $desde])->getResultArray(),
        );
    }
}
