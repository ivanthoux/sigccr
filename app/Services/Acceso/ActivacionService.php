<?php

declare(strict_types=1);

namespace App\Services\Acceso;

use App\Exceptions\ReglaNegocioException;
use App\Libraries\Seguridad\PoliticaContrasena;
use App\Libraries\Seguridad\ReglasActivacion;
use App\Services\BaseService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ComunicacionService;

/**
 * CU-04 Activar o Rehabilitar Acceso del Cliente (RN-03, RN-10, RN-62). La activación y la rehabilitación conservan
 * la cuenta existente: no se crea otra identidad (RN-08).
 */
class ActivacionService extends BaseService
{
    public const MENSAJE_ENVIADO = 'Si los datos corresponden a un cliente habilitado para activar su acceso, le enviamos un correo con las instrucciones. Revise también la carpeta de correo no deseado.';
    public const MENSAJE_NO_VERIFICADO = 'No pudimos verificar los datos ingresados. Revíselos o acérquese a la sucursal.';
    public const MENSAJE_INVALIDA = 'El enlace expiró o ya no es válido. Solicite uno nuevo desde «Activar o rehabilitar acceso».';
    private const RUTA_ENLACE = 'portal/activar';

    /**
     * CU-04 pasos 3 a 6. Devuelve true si corresponde el mensaje del paso 6 (también en A2) y false en A1.
     */
    public function solicitar(string $tipoDocumento, string $numero, string $correo): bool
    {
        $identificacion = ReglasActivacion::identificacion($tipoDocumento, $numero);
        [$ok, $comunicacionId] = $this->transaccion(function () use ($identificacion, $correo, $tipoDocumento): array {
            $this->liberarBloqueosVencidos();
            $c = $identificacion === null ? null : $this->buscarCliente($identificacion);
            $r = ReglasActivacion::evaluar($c === null ? null : [
                'estado_cliente' => (string) $c['estado'],
                'correo' => (string) $c['correo_cuenta'],
                'correo_verificado' => (int) $c['correo_verificado'] === 1,
                'estado_cuenta' => $c['estado_cuenta'],
                'bloqueada' => $c['bloqueada_hasta'] !== null && new \DateTimeImmutable((string) $c['bloqueada_hasta']) > new \DateTimeImmutable(),
            ], $correo);
            $auditoria = new AuditoriaService($this->db);
            $cuentaId = $c === null || $c['cuenta_cliente_id'] === null ? null : (int) $c['cuenta_cliente_id'];
            if ($r['resultado'] !== ReglasActivacion::RESULTADO_ENVIAR) {
                $auditoria->registrar('acceso.activacion.solicitud', 'cuenta_acceso', $cuentaId, 'Rechazado', $r['motivo'], ['tipo' => $tipoDocumento], null, $cuentaId, mb_strtolower(trim($correo)));

                return [$r['resultado'] === ReglasActivacion::RESULTADO_SIN_ENVIO, null];
            }
            // Paso 5: la nueva autorización sustituye a la anterior del mismo propósito (RN-03)
            $comunicacionId = (new AutorizacionService($this->db))->emitir((int) $cuentaId, (string) $r['proposito'], (string) $c['correo_cuenta'], (string) $c['nombre'], self::RUTA_ENLACE);
            $auditoria->registrar('acceso.activacion.solicitud', 'cuenta_acceso', $cuentaId, 'Exitoso', null, ['proposito' => $r['proposito']], null, $cuentaId, (string) $c['correo_cuenta']);

            return [true, $comunicacionId];
        });
        // Envío inmediato; si falla, CU-95 lo reintenta sin extender la vigencia (A5)
        if ($comunicacionId !== null) {
            (new ComunicacionService())->enviar($comunicacionId);
        }

        return $ok;
    }

    /**
     * D-16: al confirmar su correo desde el enlace de verificación (CU-11 paso 8), el cliente ya demostró que lo
     * controla, así que continúa directamente con la definición de su contraseña (CU-04 paso 7) sin volver a
     * identificarse ni esperar un segundo correo. Se aplican las mismas reglas que en el paso 4 (RN-10, RN-62).
     *
     * @return string|null código de la autorización de activación o rehabilitación, o null si no corresponde
     *                     (cuenta ya habilitada, bloqueada o cliente desactivado)
     */
    public function continuarTrasVerificacion(int $cuentaId): ?string
    {
        return $this->transaccion(function () use ($cuentaId): ?string {
            $this->liberarBloqueosVencidos();
            $c = $this->db->query(
                'SELECT c.id, c.estado, ca.correo AS correo_cuenta, ca.correo_verificado, ca.estado AS estado_cuenta, ca.bloqueada_hasta '
                . "FROM dbo.cuentas_acceso ca WITH (UPDLOCK, ROWLOCK) JOIN dbo.clientes c ON c.cuenta_cliente_id = ca.id WHERE ca.id = ? AND ca.tipo_cuenta = 'Cliente'",
                [$cuentaId],
            )->getRowArray();
            if ($c === null) {
                return null;
            }
            $r = ReglasActivacion::evaluar([
                'estado_cliente' => (string) $c['estado'],
                'correo' => (string) $c['correo_cuenta'],
                'correo_verificado' => (int) $c['correo_verificado'] === 1,
                'estado_cuenta' => $c['estado_cuenta'],
                'bloqueada' => $c['bloqueada_hasta'] !== null && new \DateTimeImmutable((string) $c['bloqueada_hasta']) > new \DateTimeImmutable(),
            ], (string) $c['correo_cuenta']);
            if ($r['resultado'] !== ReglasActivacion::RESULTADO_ENVIAR) {
                return null;
            }
            $codigo = (new AutorizacionService($this->db))->emitirSinComunicacion($cuentaId, (string) $r['proposito'], (string) $c['correo_cuenta']);
            (new AuditoriaService($this->db))->registrar('acceso.activacion.solicitud', 'cuenta_acceso', $cuentaId, 'Exitoso', 'Al verificar el correo (D-16)', ['proposito' => $r['proposito']], null, $cuentaId, (string) $c['correo_cuenta']);

            return $codigo;
        });
    }

    /** El enlace sigue vigente (paso 8, A3). */
    public function esValida(string $codigo): bool
    {
        return (new AutorizacionService($this->db))->buscarVigente($codigo, ['Activacion', 'Rehabilitacion'], false) !== null;
    }

    /**
     * CU-04 pasos 8 a 10. Devuelve el correo de la cuenta, para invitar al cliente a iniciar sesión con él. Usar el
     * enlace deja el correo verificado (D-40), como en la recuperación de la contraseña.
     *
     * @throws ReglaNegocioException por la política de contraseñas (A4) o por un enlace inválido (A3)
     */
    public function definirContrasena(string $codigo, string $contrasena, string $confirmacion): string
    {
        $motivos = PoliticaContrasena::validar($contrasena, $confirmacion);
        if ($motivos !== []) {
            throw new ReglaNegocioException(implode(' ', $motivos), 'RN-62');
        }
        return $this->transaccion(function () use ($codigo, $contrasena): string {
            $autorizaciones = new AutorizacionService($this->db);
            $a = $autorizaciones->buscarVigente($codigo, ['Activacion', 'Rehabilitacion'], true);
            if ($a === null) {
                throw new ReglaNegocioException(self::MENSAJE_INVALIDA, 'RN-03');
            }
            $cuentaId = (int) $a['cuenta_acceso_id'];
            // RN-10: el cliente debe seguir activo en el padrón al usar el enlace
            $cliente = $this->db->query('SELECT id, estado FROM dbo.clientes WITH (UPDLOCK, ROWLOCK) WHERE cuenta_cliente_id = ?', [$cuentaId])->getRowArray();
            if ($cliente === null || $cliente['estado'] !== 'Activo') {
                throw new ReglaNegocioException(self::MENSAJE_INVALIDA, 'RN-10');
            }
            $this->db->query(
                "UPDATE dbo.cuentas_acceso SET hash_contrasena = ?, estado = 'Habilitada', correo_verificado = 1, version_seguridad = version_seguridad + 1, "
                . 'intentos_fallidos = 0, inicio_ventana_intentos = NULL, bloqueada_hasta = NULL, actualizado_en = ?, version_fila = version_fila + 1 '
                . "WHERE id = ? AND tipo_cuenta = 'Cliente' AND estado IN ('PendienteActivacion', 'Desactivada')",
                [password_hash($contrasena, PASSWORD_ARGON2ID), $this->ahoraUtc(), $cuentaId],
            );
            if ($this->db->affectedRows() !== 1) {
                throw new ReglaNegocioException(self::MENSAJE_INVALIDA, 'RN-10');
            }
            $this->db->query('UPDATE dbo.cuentas_cliente SET fecha_activacion = ? WHERE id = ?', [$this->ahoraUtc(), $cuentaId]);
            $autorizaciones->marcarUtilizada((int) $a['id']);
            $accion = $a['proposito'] === 'Activacion' ? 'acceso.activacion' : 'acceso.rehabilitacion';
            (new AuditoriaService($this->db))->registrar($accion, 'cuenta_acceso', $cuentaId, 'Exitoso', null, ['cliente' => (int) $cliente['id']], null, $cuentaId, (string) $a['correo_destino']);

            return (string) $a['correo_destino'];
        });
    }

    /**
     * @param array{tipo_documento: string, nro_documento: string}|array{cuit: string} $id
     * @return array<string, mixed>|null
     */
    private function buscarCliente(array $id): ?array
    {
        $select = 'SELECT c.id, c.estado, c.cuenta_cliente_id, c.denominacion, ca.correo AS correo_cuenta, ca.correo_verificado, '
            . 'ca.estado AS estado_cuenta, ca.bloqueada_hasta, COALESCE(ph.nombre, pj.razon_social) AS nombre '
            . 'FROM dbo.clientes c LEFT JOIN dbo.cuentas_acceso ca WITH (UPDLOCK, ROWLOCK) ON ca.id = c.cuenta_cliente_id '
            . 'LEFT JOIN dbo.personas_humanas ph ON ph.cliente_id = c.id LEFT JOIN dbo.personas_juridicas pj ON pj.cliente_id = c.id ';
        if (isset($id['cuit'])) {
            return $this->db->query($select . "WHERE c.cuit_cuil = ? AND c.tipo_cliente = 'PersonaJuridica'", [$id['cuit']])->getRowArray();
        }

        return $this->db->query($select . 'WHERE ph.tipo_documento = ? AND ph.nro_documento = ?', [$id['tipo_documento'], $id['nro_documento']])->getRowArray();
    }
}
