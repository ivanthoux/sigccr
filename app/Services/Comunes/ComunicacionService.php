<?php

declare(strict_types=1);

namespace App\Services\Comunes;

use App\Adapters\Comunicaciones\ComunicacionesInterface;
use App\Adapters\Fabrica;
use App\Services\BaseService;
use CodeIgniter\I18n\Time;
use Throwable;

/**
 * Cola de comunicaciones (RNF-09, CU-95). Los datos sensibles del mensaje (por ejemplo, el enlace de una
 * autorización) se guardan cifrados con la clave de la aplicación y se eliminan al aceptarse el envío (4.4.6).
 *
 * Cada intento queda en intentos_envio. Un fallo transitorio o incierto se reintenta a los 1, 3 y 5 minutos con el
 * mismo identificador de operación (el proveedor descarta duplicados); agotados los reintentos, o ante un fallo
 * definitivo, la comunicación queda Fallida y se genera una alerta para el Administrador.
 */
class ComunicacionService extends BaseService
{
    /** Minutos de espera antes de cada reintento (RNF-09). */
    public const REINTENTOS_MINUTOS = [1, 3, 5];

    /** Asunto de cada plantilla (app/Views/correos/<plantilla>.php). */
    public const ASUNTOS = [
        'recuperacion_acceso' => 'SIG-CCR: recuperación de acceso',
        'restablecimiento_credenciales' => 'SIG-CCR: definición de una contraseña nueva',
        'definicion_inicial' => 'SIG-CCR: activación de su usuario',
        'verificacion_correo' => 'SIG-CCR: confirmación de su correo electrónico',
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
     * Intenta enviar una comunicación pendiente o con reintento programado. Nunca lanza excepciones por fallas del
     * proveedor: el resultado queda registrado y la comunicación, programada para su reintento.
     *
     * @return string estado resultante
     */
    public function enviar(int $id): string
    {
        return $this->transaccion(function () use ($id): string {
            $c = $this->db->query(
                'SELECT id, tipo, destino, plantilla, identificador_operacion, intentos, estado, CONVERT(VARCHAR(MAX), datos_cifrados, 1) AS datos_hex '
                . 'FROM dbo.comunicaciones WITH (UPDLOCK, ROWLOCK) WHERE id = ?',
                [$id],
            )->getRowArray();
            if ($c === null || !in_array($c['estado'], ['Pendiente', 'ReintentoProgramado'], true)) {
                return $c['estado'] ?? 'Inexistente';
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

            if ($r['resultado'] === ComunicacionesInterface::ACEPTADO) {
                $this->db->query('UPDATE dbo.comunicaciones SET estado = ?, intentos = ?, datos_cifrados = NULL, version_fila = version_fila + 1 WHERE id = ?', ['AceptadaProveedor', $intentos, $id]);

                return 'AceptadaProveedor';
            }
            $reintento = $r['resultado'] !== ComunicacionesInterface::FALLO_DEFINITIVO && $intentos <= count(self::REINTENTOS_MINUTOS);
            if ($reintento) {
                $cuando = Time::now('UTC')->addMinutes(self::REINTENTOS_MINUTOS[$intentos - 1])->format('Y-m-d H:i:s.vP');
                $this->db->query('UPDATE dbo.comunicaciones SET estado = ?, intentos = ?, programada_para = ?, version_fila = version_fila + 1 WHERE id = ?', ['ReintentoProgramado', $intentos, $cuando, $id]);

                return 'ReintentoProgramado';
            }
            $this->db->query('UPDATE dbo.comunicaciones SET estado = ?, intentos = ?, datos_cifrados = NULL, version_fila = version_fila + 1 WHERE id = ?', ['Fallida', $intentos, $id]);
            (new AlertaService($this->db))->generar('Integracion', "No se pudo enviar la comunicación #{$id} a {$c['destino']}.", 'Verificar el servicio de comunicaciones y el destinatario.', 'Administrador', null, 'comunicaciones', $id);

            return 'Fallida';
        });
    }
}
