<?php

declare(strict_types=1);

namespace App\Services\Comunes;

use App\Adapters\Comunicaciones\ComunicacionesInterface;
use App\Adapters\Fabrica;
use App\Libraries\Comunicaciones\PoliticaEnvio;
use App\Services\BaseService;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Cola de comunicaciones (RNF-09, CU-95). Los datos sensibles del mensaje (por ejemplo, el enlace de una
 * autorización) se guardan cifrados con la clave de la aplicación y se eliminan al aceptarse el envío (4.4.6).
 *
 * Cada intento queda en intentos_envio. Un fallo transitorio se reintenta al minuto, a los tres y a los cinco minutos
 * del primer intento con el mismo identificador de operación (el proveedor descarta duplicados); agotados los
 * reintentos, o ante un fallo definitivo, queda Fallida; un resultado incierto queda En revisión y no se reenvía.
 * En los tres casos se genera una alerta para el Administrador (PoliticaEnvio, CU-95).
 */
class ComunicacionService extends BaseService
{
    /** Asunto de cada plantilla (app/Views/correos/<plantilla>.php). */
    public const ASUNTOS = [
        'recuperacion_acceso' => 'SIG-CCR: recuperación de acceso',
        'restablecimiento_credenciales' => 'SIG-CCR: definición de una contraseña nueva',
        'definicion_inicial' => 'SIG-CCR: activación de su usuario',
        'verificacion_correo' => 'SIG-CCR: confirmación de su correo electrónico',
        'verificacion_correo_cliente' => 'SIG-CCR: confirme su correo para usar el portal',
        'activacion_acceso' => 'SIG-CCR: activación de su acceso al portal',
        'cambio_correo' => 'SIG-CCR: confirme el cambio de su correo electrónico',
        'verificacion_correo_nuevo' => 'SIG-CCR: verifique su correo electrónico nuevo',
        'verificacion_pre_registro' => 'SIG-CCR: verifique su correo para completar el pre-registro',
        'oferta_crediticia' => 'SIG-CCR: tiene una oferta de crédito preaprobada',
    ];

    private ?ComunicacionesInterface $adaptador;

    public function __construct(?ComunicacionesInterface $adaptador = null)
    {
        parent::__construct();
        $this->adaptador = $adaptador;
    }

    /**
     * Agrega una comunicación a la cola. Debe llamarse dentro de la transacción de la operación que la origina.
     *
     * @param array<string, mixed> $datos datos de la plantilla; se guardan cifrados
     */
    public function encolar(string $tipo, string $destino, string $plantilla, array $datos, string $origenTipo, int $origenId): int
    {
        $this->db->table('comunicaciones')->insert([
            'tipo' => $tipo,
            'canal' => 'Correo',
            'destino' => $destino,
            'plantilla' => $plantilla,
            'origen_tipo' => $origenTipo,
            'origen_id' => $origenId,
            'programada_para' => $this->ahoraUtc(),
            'estado' => 'Pendiente',
        ]);
        $id = (int) $this->db->insertID();
        $cifrado = service('encrypter')->encrypt(json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        // VARBINARY desde texto hexadecimal: el controlador no convierte cadenas a binario de forma implícita
        $this->db->query('UPDATE dbo.comunicaciones SET datos_cifrados = CONVERT(VARBINARY(MAX), CAST(? AS VARCHAR(MAX)), 1) WHERE id = ?', ['0x' . bin2hex($cifrado), $id]);

        return $id;
    }

    /**
     * Intenta enviar una comunicación pendiente o con reintento programado (CU-95 pasos 3 a 6). Nunca lanza
     * excepciones por fallas del proveedor: el resultado queda registrado y la comunicación, en el estado que
     * corresponde según PoliticaEnvio.
     *
     * @return string estado resultante
     */
    public function enviar(int $id): string
    {
        return $this->transaccion(function () use ($id): string {
            $c = $this->db->query(
                'SELECT id, tipo, destino, plantilla, identificador_operacion, intentos, estado, origen_tipo, origen_id, '
                . 'CONVERT(VARCHAR(MAX), datos_cifrados, 1) AS datos_hex FROM dbo.comunicaciones WITH (UPDLOCK, ROWLOCK) WHERE id = ?',
                [$id],
            )->getRowArray();
            if ($c === null || !in_array($c['estado'], ['Pendiente', 'ReintentoProgramado'], true)) {
                return $c['estado'] ?? 'Inexistente';
            }
            $ahora = new DateTimeImmutable('now', new DateTimeZone('UTC'));

            // Paso 3 y A1: la comunicación debe seguir siendo válida
            $motivo = PoliticaEnvio::motivoCancelacion((string) $c['tipo'], $this->autorizacionDeOrigen($c), $this->ofertaDeOrigen($c), $this->consentimientoComercial($c), $ahora);
            if ($motivo !== null) {
                $this->db->table('intentos_envio')->insert(['comunicacion_id' => $id, 'fecha_hora' => $this->ahoraUtc(), 'resultado' => 'Cancelada', 'detalle' => $motivo]);
                $this->db->query('UPDATE dbo.comunicaciones SET estado = ?, datos_cifrados = NULL, version_fila = version_fila + 1 WHERE id = ?', ['Cancelada', $id]);

                return 'Cancelada';
            }

            try {
                $datos = $c['datos_hex'] === null ? [] : json_decode(
                    service('encrypter')->decrypt((string) hex2bin(substr((string) $c['datos_hex'], 2))),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
                $cuerpo = view('correos/' . $c['plantilla'], $datos);
                $asunto = self::ASUNTOS[$c['plantilla']] ?? 'SIG-CCR';
                $r = ($this->adaptador ?? Fabrica::comunicaciones())->enviar($c['destino'], $asunto, $cuerpo, (string) $c['identificador_operacion']);
            } catch (Throwable $e) {
                log_message('error', "Comunicación {$id}: {$e->getMessage()}");
                $r = ['resultado' => ComunicacionesInterface::FALLO_TRANSITORIO, 'id_proveedor' => null, 'detalle' => 'Error al enviar'];
            }

            $this->db->table('intentos_envio')->insert([
                'comunicacion_id' => $id,
                'fecha_hora' => $this->ahoraUtc(),
                'resultado' => $r['resultado'],
                'id_proveedor' => $r['id_proveedor'],
                'detalle' => $r['detalle'] === null ? null : mb_substr($r['detalle'], 0, 500),
            ]);
            $intentos = (int) $c['intentos'] + 1;
            $primero = $this->db->query('SELECT MIN(fecha_hora) AS f FROM dbo.intentos_envio WHERE comunicacion_id = ? AND resultado <> ?', [$id, 'Cancelada'])->getRow()->f ?? null;
            $sig = PoliticaEnvio::despuesDelIntento($r['resultado'], $intentos, $primero === null ? $ahora : new DateTimeImmutable((string) $primero));

            // Los datos cifrados (el enlace) se eliminan cuando ya no habrá más envíos (4.4.6); En revisión los conserva
            $limpiar = in_array($sig['estado'], ['AceptadaProveedor', 'Fallida'], true);
            $this->db->query(
                'UPDATE dbo.comunicaciones SET estado = ?, intentos = ?, programada_para = ISNULL(?, programada_para)'
                . ($limpiar ? ', datos_cifrados = NULL' : '') . ', version_fila = version_fila + 1 WHERE id = ?',
                [$sig['estado'], min($intentos, 4), $sig['programada_para']?->format('Y-m-d H:i:s.vP'), $id],
            );
            if ($sig['alerta']) {
                $causa = $sig['estado'] === 'EnRevision'
                    ? "El proveedor no confirmó si recibió la comunicación #{$id} a {$c['destino']}; no se reenvía."
                    : "No se pudo enviar la comunicación #{$id} a {$c['destino']}.";
                (new AlertaService($this->db))->generar('Integracion', $causa, 'Verificar el servicio de comunicaciones y el destinatario.', 'Administrador', null, 'comunicaciones', $id);
            }

            return $sig['estado'];
        });
    }

    /**
     * Comunicaciones listas para enviar (CU-95 paso 2), de la más antigua a la más reciente.
     *
     * @return list<int>
     */
    public function pendientes(int $limite = 100): array
    {
        $filas = $this->db->query(
            "SELECT TOP ({$limite}) id FROM dbo.comunicaciones WHERE estado IN ('Pendiente', 'ReintentoProgramado') AND programada_para <= ? ORDER BY programada_para, id",
            [$this->ahoraUtc()],
        )->getResultArray();

        return array_map(static fn (array $f): int => (int) $f['id'], $filas);
    }

    /**
     * @param array<string, mixed> $c
     * @return array{estado: string, vence: string}|null
     */
    private function autorizacionDeOrigen(array $c): ?array
    {
        // CU-104: el enlace de verificación del pre-registro vale mientras siga pendiente de verificación y no venza
        if ($c['origen_tipo'] === 'pre_registros') {
            $p = $this->db->query('SELECT estado, vence FROM dbo.pre_registros WHERE id = ?', [(int) $c['origen_id']])->getRowArray();

            if ($p === null) {
                return null;
            }

            return ['estado' => $p['estado'] === 'PendienteVerificacion' ? 'Vigente' : 'Utilizada', 'vence' => (string) $p['vence']];
        }
        if ($c['origen_tipo'] !== 'autorizaciones_temporales') {
            return null;
        }
        $a = $this->db->query('SELECT estado, vence FROM dbo.autorizaciones_temporales WHERE id = ?', [(int) $c['origen_id']])->getRowArray();

        return $a === null ? null : ['estado' => (string) $a['estado'], 'vence' => (string) $a['vence']];
    }

    /**
     * @param array<string, mixed> $c
     * @return array{estado: string, vence: string}|null
     */
    private function ofertaDeOrigen(array $c): ?array
    {
        if ($c['tipo'] !== 'Oferta' || $c['origen_tipo'] !== 'ofertas') {
            return null;
        }
        $o = $this->db->query('SELECT estado, vence FROM dbo.ofertas WHERE id = ?', [(int) $c['origen_id']])->getRowArray();

        return $o === null ? null : ['estado' => (string) $o['estado'], 'vence' => (string) $o['vence']];
    }

    /**
     * RN-64: rige el último registro de consentimiento comercial del cliente de la oferta.
     *
     * @param array<string, mixed> $c
     */
    private function consentimientoComercial(array $c): ?bool
    {
        if ($c['tipo'] !== 'Oferta' || $c['origen_tipo'] !== 'ofertas') {
            return null;
        }
        $f = $this->db->query(
            "SELECT TOP (1) k.otorgado FROM dbo.ofertas o JOIN dbo.consentimientos k ON k.cliente_id = o.cliente_id AND k.tipo = 'ComunicacionesComerciales' WHERE o.id = ? ORDER BY k.fecha DESC, k.id DESC",
            [(int) $c['origen_id']],
        )->getRowArray();

        return $f !== null && (int) $f['otorgado'] === 1;
    }
}
