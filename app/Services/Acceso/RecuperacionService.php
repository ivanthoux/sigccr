<?php

declare(strict_types=1);

namespace App\Services\Acceso;

use App\Exceptions\ReglaNegocioException;
use App\Libraries\Seguridad\PoliticaContrasena;
use App\Services\BaseService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ComunicacionService;

/**
 * Definición de contraseña por enlace: recuperación de acceso (CU-02), restablecimiento iniciado por el Administrador
 * (CU-07) y definición inicial de un usuario nuevo (CU-05). La respuesta al pedido de recuperación es siempre la misma,
 * exista o no la cuenta (CU-02 A1, A5).
 */
class RecuperacionService extends BaseService
{
    public const MINUTOS_VIGENCIA = AutorizacionService::VIGENCIA_MINUTOS['Recuperacion'];
    public const MENSAJE_INVALIDA = 'El enlace expiró o ya no es válido. Solicite uno nuevo desde "¿Olvidó su contraseña?".';

    /** Propósitos cuyo enlace permite definir la contraseña. */
    private const PROPOSITOS = ['Recuperacion', 'Restablecimiento', 'DefinicionInicial'];

    /** CU-02 pasos 3 a 5. $rutaEnlace es la ruta del formulario de contraseña (por ejemplo, 'admin/restablecer'). */
    public function solicitar(string $tipoCuenta, string $correo, string $rutaEnlace): void
    {
        $correo = mb_strtolower(trim($correo));
        $comunicacionId = $this->transaccion(function () use ($tipoCuenta, $correo, $rutaEnlace): ?int {
            $this->liberarBloqueosVencidos();
            $auditoria = new AuditoriaService($this->db);
            $cuenta = $this->db->query(
                'SELECT ca.id, ca.correo, ca.estado, c.estado AS estado_cliente, COALESCE(ui.nombre, ph.nombre, pj.razon_social) AS nombre '
                . 'FROM dbo.cuentas_acceso ca WITH (UPDLOCK, ROWLOCK) LEFT JOIN dbo.usuarios_internos ui ON ui.id = ca.id '
                . 'LEFT JOIN dbo.clientes c ON c.cuenta_cliente_id = ca.id LEFT JOIN dbo.personas_humanas ph ON ph.cliente_id = c.id '
                . 'LEFT JOIN dbo.personas_juridicas pj ON pj.cliente_id = c.id WHERE ca.tipo_cuenta = ? AND ca.correo = ?',
                [$tipoCuenta, $correo],
            )->getRowArray();

            // RN-10: el cliente desactivado en el padrón tampoco recupera el acceso al portal
            $clienteInactivo = $tipoCuenta === 'Cliente' && $cuenta !== null && $cuenta['estado_cliente'] !== 'Activo';
            if ($cuenta === null || $cuenta['estado'] !== 'Habilitada' || $clienteInactivo) {
                $motivo = $cuenta === null ? 'Correo no registrado' : ($clienteInactivo ? 'Cliente desactivado en el padrón (RN-10)' : "Cuenta en estado {$cuenta['estado']}");
                $auditoria->registrar('acceso.recuperacion.solicitud', 'cuenta_acceso', $cuenta['id'] ?? null, 'Rechazado', $motivo, ['correo' => $correo], null, $cuenta === null ? null : (int) $cuenta['id'], $correo);

                return null;
            }
            $id = (int) $cuenta['id'];
            $comunicacionId = (new AutorizacionService($this->db))->emitir($id, 'Recuperacion', $cuenta['correo'], (string) ($cuenta['nombre'] ?? ''), $rutaEnlace);
            $auditoria->registrar('acceso.recuperacion.solicitud', 'cuenta_acceso', $id, 'Exitoso', null, null, null, $id, $cuenta['correo']);

            return $comunicacionId;
        });

        // Paso 4: se intenta el envío inmediato; si falla queda en la cola para su reintento (A4, CU-95)
        if ($comunicacionId !== null) {
            (new ComunicacionService())->enviar($comunicacionId);
        }
    }

    /** El enlace sigue vigente (CU-02 paso 6, CU-07 paso 8). */
    public function esValida(string $codigo): bool
    {
        return (new AutorizacionService($this->db))->buscarVigente($codigo, self::PROPOSITOS, false) !== null;
    }

    /**
     * CU-02 pasos 7 y 8; CU-07 paso 9. Definir la contraseña desde el enlace verifica el correo y, si la cuenta estaba
     * pendiente de activación, la habilita. version_seguridad + 1 finaliza las sesiones abiertas.
     *
     * @throws ReglaNegocioException por la política de contraseñas o por un enlace inválido
     */
    public function restablecer(string $codigo, string $contrasena, string $confirmacion): void
    {
        $motivos = PoliticaContrasena::validar($contrasena, $confirmacion);
        if ($motivos !== []) {
            throw new ReglaNegocioException(implode(' ', $motivos), 'RN-62');
        }

        $this->transaccion(function () use ($codigo, $contrasena): void {
            $autorizaciones = new AutorizacionService($this->db);
            $a = $autorizaciones->buscarVigente($codigo, self::PROPOSITOS, true);
            if ($a === null) {
                throw new ReglaNegocioException(self::MENSAJE_INVALIDA, 'RN-03');
            }
            $cuentaId = (int) $a['cuenta_acceso_id'];
            $this->db->query(
                "UPDATE dbo.cuentas_acceso SET hash_contrasena = ?, correo_verificado = 1, "
                . "estado = CASE WHEN estado = 'PendienteActivacion' THEN 'Habilitada' ELSE estado END, "
                . 'version_seguridad = version_seguridad + 1, intentos_fallidos = 0, inicio_ventana_intentos = NULL, '
                . 'actualizado_en = ?, version_fila = version_fila + 1 WHERE id = ?',
                [password_hash($contrasena, PASSWORD_ARGON2ID), $this->ahoraUtc(), $cuentaId],
            );
            $autorizaciones->marcarUtilizada((int) $a['id']);
            $accion = $a['proposito'] === 'DefinicionInicial' ? 'acceso.definicion_inicial' : 'acceso.restablecimiento';
            (new AuditoriaService($this->db))->registrar($accion, 'cuenta_acceso', $cuentaId, 'Exitoso', null, ['proposito' => $a['proposito']], null, $cuentaId, (string) $a['correo_destino']);
        });
    }
}
