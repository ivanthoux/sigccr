<?php

declare(strict_types=1);

namespace App\Services\Acceso;

use App\Services\BaseService;
use App\Services\Comunes\ComunicacionService;
use CodeIgniter\I18n\Time;

/**
 * Autorizaciones temporales de un solo uso (RN-03): código aleatorio de 256 bits que viaja en un enlace por correo;
 * la base guarda solo su hash SHA-256. Una nueva autorización del mismo propósito sustituye a la vigente.
 */
class AutorizacionService extends BaseService
{
    /** Vigencia por propósito, en minutos (3.5.1). */
    public const VIGENCIA_MINUTOS = [
        'Recuperacion' => 15,
        'Restablecimiento' => 15,
        'DefinicionInicial' => 1440,
        'VerificacionCorreo' => 30,
        'Activacion' => 1440,
        'Rehabilitacion' => 1440,
    ];

    /** Plantilla de correo por propósito (app/Views/correos). */
    public const PLANTILLAS = [
        'Recuperacion' => 'recuperacion_acceso',
        'Restablecimiento' => 'restablecimiento_credenciales',
        'DefinicionInicial' => 'definicion_inicial',
        'VerificacionCorreo' => 'verificacion_correo',
        'Activacion' => 'activacion_acceso',
        'Rehabilitacion' => 'activacion_acceso',
    ];

    /**
     * Emite la autorización y encola su correo. Debe llamarse dentro de la transacción de la operación; el envío
     * inmediato se hace después con ComunicacionService::enviar() sobre el identificador devuelto.
     *
     * @return int identificador de la comunicación encolada
     */
    public function emitir(int $cuentaId, string $proposito, string $correoDestino, string $nombre, string $rutaEnlace): int
    {
        $this->db->query("UPDATE dbo.autorizaciones_temporales SET estado = 'Sustituida' WHERE cuenta_acceso_id = ? AND proposito = ? AND estado = 'Vigente'", [$cuentaId, $proposito]);
        $codigo = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $minutos = self::VIGENCIA_MINUTOS[$proposito];
        $emitida = Time::now('UTC');
        $vence = $emitida->addMinutes($minutos);
        $this->db->table('autorizaciones_temporales')->insert([
            'cuenta_acceso_id' => $cuentaId,
            'proposito' => $proposito,
            'hash_codigo' => hash('sha256', $codigo),
            'correo_destino' => $correoDestino,
            'emitida' => $emitida->format('Y-m-d H:i:s.vP'),
            'vence' => $vence->format('Y-m-d H:i:s.vP'),
            'estado' => 'Vigente',
        ]);
        $autorizacionId = (int) $this->db->insertID();

        return (new ComunicacionService())->encolar(
            $proposito === 'VerificacionCorreo' ? 'VerificacionCorreo' : 'Autorizacion',
            $correoDestino,
            self::PLANTILLAS[$proposito],
            [
                'nombre' => $nombre,
                'enlace' => site_url($rutaEnlace . '/' . $codigo),
                'vence' => $vence->setTimezone(config('Sigccr')->zonaNegocio)->format('d/m/Y H:i'),
                'vigencia' => $minutos >= 60 ? intdiv($minutos, 60) . ' horas' : $minutos . ' minutos',
            ],
            'autorizaciones_temporales',
            $autorizacionId,
        );
    }

    /**
     * Autorización vigente para el código recibido, o null si no existe, ya se usó, fue sustituida o venció.
     * Con $bloquear la fila queda bloqueada hasta el fin de la transacción.
     *
     * @param list<string> $propositos
     * @return array<string, mixed>|null
     */
    public function buscarVigente(string $codigo, array $propositos, bool $bloquear): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $codigo)) {
            return null;
        }
        $bloqueo = $bloquear ? ' WITH (UPDLOCK, ROWLOCK)' : '';
        $marcas = implode(', ', array_fill(0, count($propositos), '?'));
        $a = $this->db->query(
            'SELECT id, cuenta_acceso_id, proposito, correo_destino, estado, CASE WHEN vence > SYSDATETIMEOFFSET() THEN 1 ELSE 0 END AS vigente '
            . "FROM dbo.autorizaciones_temporales{$bloqueo} WHERE hash_codigo = ? AND proposito IN ({$marcas})",
            [hash('sha256', $codigo), ...$propositos],
        )->getRowArray();

        return $a !== null && $a['estado'] === 'Vigente' && (int) $a['vigente'] === 1 ? $a : null;
    }

    /** Marca la autorización como utilizada (uso único). */
    public function marcarUtilizada(int $id): void
    {
        $this->db->query("UPDATE dbo.autorizaciones_temporales SET estado = 'Utilizada', utilizada = ? WHERE id = ?", [$this->ahoraUtc(), $id]);
    }

    /** Invalida todas las autorizaciones vigentes de la cuenta (por ejemplo, al desactivarla). */
    public function sustituirVigentes(int $cuentaId): void
    {
        $this->db->query("UPDATE dbo.autorizaciones_temporales SET estado = 'Sustituida' WHERE cuenta_acceso_id = ? AND estado = 'Vigente'", [$cuentaId]);
    }
}
