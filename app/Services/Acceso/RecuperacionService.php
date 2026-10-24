<?php

declare(strict_types=1);

namespace App\Services\Acceso;

use App\Exceptions\ReglaNegocioException;
use App\Libraries\Seguridad\PoliticaContrasena;
use App\Services\BaseService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ComunicacionService;
use CodeIgniter\I18n\Time;

/**
 * Recuperación de acceso (CU-02; RN-03, RN-62).
 *
 * La autorización es un código aleatorio de 256 bits que viaja en un enlace; la base guarda solo su hash SHA-256.
 * Vence a los 15 minutos, es de un solo uso y una nueva solicitud sustituye a la anterior. La respuesta al pedido es
 * siempre la misma, exista o no la cuenta (A1, A5).
 */
class RecuperacionService extends BaseService
{
    public const MINUTOS_VIGENCIA = 15;
    public const MENSAJE_INVALIDA = 'La autorización expiró o no es válida. Solicite nuevamente la recuperación de acceso.';

    /**
     * CU-02 pasos 3 a 5. $rutaEnlace es la ruta del formulario de restablecimiento (por ejemplo, 'admin/restablecer').
     */
    public function solicitar(string $tipoCuenta, string $correo, string $rutaEnlace): void
    {
        $correo = mb_strtolower(trim($correo));
        $comunicacionId = $this->transaccion(function () use ($tipoCuenta, $correo, $rutaEnlace): ?int {
            $auditoria = new AuditoriaService($this->db);
            $cuenta = $this->db->query(
                'SELECT ca.id, ca.correo, ca.estado, ui.nombre FROM dbo.cuentas_acceso ca WITH (UPDLOCK, ROWLOCK) '
                . 'LEFT JOIN dbo.usuarios_internos ui ON ui.id = ca.id WHERE ca.tipo_cuenta = ? AND ca.correo = ?',
                [$tipoCuenta, $correo],
            )->getRowArray();

            if ($cuenta === null || $cuenta['estado'] !== 'Habilitada') {
                $motivo = $cuenta === null ? 'Correo no registrado' : "Cuenta en estado {$cuenta['estado']}";
                $auditoria->registrar('acceso.recuperacion.solicitud', 'cuenta_acceso', $cuenta['id'] ?? null, 'Rechazado', $motivo, ['correo' => $correo], null, $cuenta === null ? null : (int) $cuenta['id'], $correo);

                return null;
            }

            $id = (int) $cuenta['id'];
            $this->db->query("UPDATE dbo.autorizaciones_temporales SET estado = 'Sustituida' WHERE cuenta_acceso_id = ? AND proposito = 'Recuperacion' AND estado = 'Vigente'", [$id]);
            $codigo = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $emitida = Time::now('UTC');
            $vence = $emitida->addMinutes(self::MINUTOS_VIGENCIA);
            $this->db->table('autorizaciones_temporales')->insert([
                'cuenta_acceso_id' => $id,
                'proposito' => 'Recuperacion',
                'hash_codigo' => hash('sha256', $codigo),
                'correo_destino' => $cuenta['correo'],
                'emitida' => $emitida->format('Y-m-d H:i:s.vP'),
                'vence' => $vence->format('Y-m-d H:i:s.vP'),
                'estado' => 'Vigente',
            ]);
            $autorizacionId = (int) $this->db->insertID();

            $comunicacionId = (new ComunicacionService())->encolar('Autorizacion', $cuenta['correo'], 'recuperacion_acceso', [
                'nombre' => $cuenta['nombre'] ?? '',
                'enlace' => site_url($rutaEnlace . '/' . $codigo),
                'vence' => $vence->setTimezone(config('Sigccr')->zonaNegocio)->format('d/m/Y H:i'),
                'minutos' => self::MINUTOS_VIGENCIA,
            ], 'autorizaciones_temporales', $autorizacionId);
            $auditoria->registrar('acceso.recuperacion.solicitud', 'cuenta_acceso', $id, 'Exitoso', null, null, null, $id, $cuenta['correo']);

            return $comunicacionId;
        });

        // Paso 4: se intenta el envío inmediato; si falla queda en la cola para su reintento (A4, CU-95)
        if ($comunicacionId !== null) {
            (new ComunicacionService())->enviar($comunicacionId);
        }
    }

    /** CU-02 paso 6: la autorización del enlace sigue vigente. */
    public function esValida(string $codigo): bool
    {
        return $this->buscarVigente($codigo, false) !== null;
    }

    /**
     * CU-02 pasos 7 y 8.
     *
     * @throws ReglaNegocioException por la política de contraseñas (A3) o una autorización inválida (A2)
     */
    public function restablecer(string $codigo, string $contrasena, string $confirmacion): void
    {
        $motivos = PoliticaContrasena::validar($contrasena, $confirmacion);
        if ($motivos !== []) {
            throw new ReglaNegocioException(implode(' ', $motivos), 'RN-62');
        }

        $this->transaccion(function () use ($codigo, $contrasena): void {
            $a = $this->buscarVigente($codigo, true);
            if ($a === null) {
                throw new ReglaNegocioException(self::MENSAJE_INVALIDA, 'RN-03');
            }
            $cuentaId = (int) $a['cuenta_acceso_id'];
            // Nueva contraseña; version_seguridad + 1 finaliza las sesiones abiertas de la cuenta (paso 8)
            $this->db->query(
                'UPDATE dbo.cuentas_acceso SET hash_contrasena = ?, version_seguridad = version_seguridad + 1, intentos_fallidos = 0, '
                . 'inicio_ventana_intentos = NULL, actualizado_en = ?, version_fila = version_fila + 1 WHERE id = ?',
                [password_hash($contrasena, PASSWORD_ARGON2ID), $this->ahoraUtc(), $cuentaId],
            );
            $this->db->query("UPDATE dbo.autorizaciones_temporales SET estado = 'Utilizada', utilizada = ? WHERE id = ?", [$this->ahoraUtc(), $a['id']]);
            (new AuditoriaService($this->db))->registrar('acceso.recuperacion.restablecimiento', 'cuenta_acceso', $cuentaId, 'Exitoso', null, null, null, $cuentaId, (string) $a['correo_destino']);
        });
    }

    /** @return array<string, mixed>|null */
    private function buscarVigente(string $codigo, bool $bloquear): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $codigo)) {
            return null;
        }
        $bloqueo = $bloquear ? ' WITH (UPDLOCK, ROWLOCK)' : '';
        $a = $this->db->query(
            "SELECT id, cuenta_acceso_id, correo_destino, estado, CASE WHEN vence > SYSDATETIMEOFFSET() THEN 1 ELSE 0 END AS vigente "
            . "FROM dbo.autorizaciones_temporales{$bloqueo} WHERE hash_codigo = ? AND proposito = 'Recuperacion'",
            [hash('sha256', $codigo)],
        )->getRowArray();
        if ($a === null || $a['estado'] !== 'Vigente') {
            return null;
        }
        if ((int) $a['vigente'] !== 1) {
            if ($bloquear) {
                $this->db->query("UPDATE dbo.autorizaciones_temporales SET estado = 'Vencida' WHERE id = ?", [$a['id']]);
            }

            return null;
        }

        return $a;
    }
}
