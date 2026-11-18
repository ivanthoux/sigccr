<?php

declare(strict_types=1);

namespace App\Services\Clientes;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Clientes\ValidadorPreRegistro;
use App\Libraries\Validacion\Campos;
use App\Services\BaseService;
use App\Services\Comunes\AlertaService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ComunicacionService;
use CodeIgniter\I18n\Time;

/**
 * CU-104 Pre-registrarse en el Portal (D-42). Una persona que todavía no es cliente registra sus datos y verifica su
 * correo; el pre-registro queda pendiente de validación hasta que el Analista completa el alta con CU-11, tras
 * verificar su identidad en forma presencial. No crea un cliente ni habilita el portal.
 */
class PreRegistroService extends BaseService
{
    /** Vigencia del enlace de verificación del correo, en minutos (RN-03, igual que VerificacionCorreo). */
    public const VIGENCIA_MINUTOS = 30;
    private const RUTA_VERIFICACION = 'portal/pre-registro/verificar';
    private const ACTIVOS = ['PendienteVerificacion', 'PendienteValidacion'];

    /**
     * CU-104 pasos 3 a 5 (A1, A2, A4). Un pre-registro anterior del mismo documento que no llegó a verificarse se
     * reinicia con un enlace nuevo (A3).
     *
     * @param array<string, mixed> $datos
     */
    public function registrar(array $datos, ?string $ip): void
    {
        $d = ValidadorPreRegistro::normalizar($datos);
        $errores = ValidadorPreRegistro::validar($d);
        if ($errores !== []) {
            throw new ValidacionException($errores, 'RN-64');
        }
        $comunicacionId = $this->transaccion(function () use ($d, $ip): int {
            // A1: ya es cliente (por documento o CUIL)
            $cliente = $this->db->table('personas_humanas')->where('tipo_documento', $d['tipo_documento'])->where('nro_documento', $d['nro_documento'])->countAllResults() > 0
                || $this->db->table('clientes')->where('cuit_cuil', $d['cuit_cuil'])->countAllResults() > 0;
            if ($cliente) {
                throw new ReglaNegocioException('Usted ya es cliente de la entidad: active o rehabilite su acceso al portal con «Activar mi acceso», usando el correo que registró en la sucursal.', 'A1');
            }
            // A2: el correo pertenece a otro cliente
            $correoUsado = $this->db->table('clientes')->where('correo', $d['correo'])->countAllResults() > 0
                || $this->db->table('cuentas_acceso')->where('tipo_cuenta', 'Cliente')->where('correo', $d['correo'])->countAllResults() > 0;
            if ($correoUsado) {
                throw new ValidacionException(['correo' => 'Ese correo ya está registrado para otro cliente: ingrese otro.'], 'RN-08');
            }
            $previo = $this->db->query(
                'SELECT TOP 1 id, estado FROM dbo.pre_registros WITH (UPDLOCK, ROWLOCK) WHERE ((tipo_documento = ? AND nro_documento = ?) OR correo = ?) '
                . "AND estado IN ('PendienteVerificacion', 'PendienteValidacion') ORDER BY id DESC",
                [$d['tipo_documento'], $d['nro_documento'], $d['correo']],
            )->getRowArray();
            if ($previo !== null && $previo['estado'] === 'PendienteValidacion') {
                throw new ReglaNegocioException('Ya tenemos un pre-registro verificado con esos datos. Acérquese a la sucursal con su documento para completar el alta.');
            }

            $codigo = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $ahora = $this->ahoraUtc();
            $vence = Time::now('UTC')->addMinutes(self::VIGENCIA_MINUTOS);
            $fila = [
                'tipo_documento' => $d['tipo_documento'], 'nro_documento' => $d['nro_documento'], 'cuit_cuil' => $d['cuit_cuil'],
                'nombre' => $d['nombre'], 'apellido' => $d['apellido'], 'correo' => $d['correo'], 'telefono' => $d['telefono'] === '' ? null : $d['telefono'],
                'hash_codigo' => hash('sha256', $codigo), 'vence' => $vence->format('Y-m-d H:i:s.vP'), 'fecha_politica' => $ahora,
                'estado' => 'PendienteVerificacion', 'ip_origen' => $ip === null ? null : mb_substr($ip, 0, 45),
            ];
            if ($previo !== null) {
                // A3: se reinicia el pre-registro que no se verificó
                $id = (int) $previo['id'];
                $this->db->table('pre_registros')->where('id', $id)->set('version_fila', 'version_fila + 1', false)->update($fila + ['actualizado_en' => $ahora]);
            } else {
                $this->db->table('pre_registros')->insert($fila + ['creado_en' => $ahora]);
                $id = (int) $this->db->insertID();
            }
            $comunicacionId = (new ComunicacionService())->encolar('VerificacionCorreo', $d['correo'], 'verificacion_pre_registro', [
                'nombre' => $d['nombre'],
                'enlace' => site_url(self::RUTA_VERIFICACION . '/' . $codigo),
                'vence' => $vence->setTimezone(config('Sigccr')->zonaNegocio)->format('d/m/Y H:i'),
                'vigencia' => self::VIGENCIA_MINUTOS . ' minutos',
            ], 'pre_registros', $id);
            (new AuditoriaService($this->db))->registrar('pre-registros.registrar', 'pre_registros', $id, 'Exitoso', $previo !== null ? 'Reinicio de un pre-registro sin verificar (A3)' : null, [
                'documento' => $d['tipo_documento'] . ' ' . $d['nro_documento'], 'correo' => $d['correo'],
            ], null, null, $d['correo']);

            return $comunicacionId;
        });
        (new ComunicacionService())->enviar($comunicacionId);
    }

    /**
     * CU-104 pasos 6 y 7 (A3): la persona verifica su correo y el pre-registro queda pendiente de validación, con una
     * alerta para los Analistas de Crédito.
     */
    public function verificar(string $codigo): void
    {
        $this->transaccion(function () use ($codigo): void {
            $p = preg_match('/^[A-Za-z0-9_-]{43}$/', $codigo) !== 1 ? null : $this->db->query(
                "SELECT id, nombre, apellido, correo, CASE WHEN vence > SYSDATETIMEOFFSET() THEN 1 ELSE 0 END AS vigente FROM dbo.pre_registros WITH (UPDLOCK, ROWLOCK) "
                . "WHERE hash_codigo = ? AND estado = 'PendienteVerificacion'",
                [hash('sha256', $codigo)],
            )->getRowArray();
            if ($p === null || (int) $p['vigente'] !== 1) {
                throw new ReglaNegocioException('El enlace de verificación expiró o ya no es válido. Puede volver a pre-registrarse con los mismos datos para recibir uno nuevo.', 'RN-03');
            }
            $ahora = $this->ahoraUtc();
            $this->db->query(
                "UPDATE dbo.pre_registros SET estado = 'PendienteValidacion', hash_codigo = NULL, fecha_verificacion = ?, actualizado_en = ?, version_fila = version_fila + 1 WHERE id = ?",
                [$ahora, $ahora, $p['id']],
            );
            (new AlertaService($this->db))->generar(
                'Operativa',
                "Pre-registro verificado de {$p['apellido']}, {$p['nombre']} desde el portal.",
                'Verificar la identidad de la persona en la sucursal y completar su alta (CU-11), o descartar el pre-registro.',
                'AnalistaCredito',
                null,
                'pre_registros',
                (int) $p['id'],
            );
            (new AuditoriaService($this->db))->registrar('pre-registros.verificar', 'pre_registros', (int) $p['id'], 'Exitoso', null, null, null, null, (string) $p['correo']);
        });
    }

    /**
     * CU-11 paso 1: pre-registros por estado, los más antiguos primero.
     *
     * @return list<array<string, mixed>>
     */
    public function buscar(string $estado = 'PendienteValidacion', string $texto = ''): array
    {
        $b = $this->db->table('pre_registros p')->select('p.*, c.denominacion AS cliente, u.apellido + \', \' + u.nombre AS analista', false)
            ->join('clientes c', 'c.id = p.cliente_id', 'left')->join('usuarios_internos u', 'u.id = p.usuario_id', 'left');
        if (in_array($estado, ['PendienteVerificacion', 'PendienteValidacion', 'Completado', 'Descartado'], true)) {
            $b->where('p.estado', $estado);
        }
        $t = Campos::texto($texto);
        if ($t !== '') {
            $digitos = preg_replace('/\D/', '', $t) ?? '';
            $b->groupStart()->like('p.apellido', Campos::literalLike((string) $t))->orLike('p.nombre', Campos::literalLike((string) $t))->orLike('p.correo', Campos::literalLike((string) $t));
            if ($digitos !== '') {
                $b->orWhere('p.nro_documento', $digitos)->orWhere('p.cuit_cuil', $digitos);
            }
            $b->groupEnd();
        }

        return $b->orderBy($estado === 'PendienteValidacion' ? 'p.fecha_verificacion' : 'p.creado_en', $estado === 'PendienteValidacion' ? 'ASC' : 'DESC')->limit(200)->get()->getResultArray();
    }

    /** @return array<string, mixed> */
    public function obtener(int $id): array
    {
        $p = $this->db->query(
            "SELECT p.*, c.denominacion AS cliente, u.apellido + ', ' + u.nombre AS analista FROM dbo.pre_registros p LEFT JOIN dbo.clientes c ON c.id = p.cliente_id "
            . 'LEFT JOIN dbo.usuarios_internos u ON u.id = p.usuario_id WHERE p.id = ?',
            [$id],
        )->getRowArray();
        if ($p === null) {
            throw new ReglaNegocioException('El pre-registro no existe.');
        }

        return $p;
    }

    /**
     * Datos del pre-registro verificado para proponerlos en el formulario de alta (CU-11 paso 2).
     *
     * @return array<string, string>
     */
    public function datosParaAlta(int $id): array
    {
        $p = $this->obtener($id);
        if ($p['estado'] !== 'PendienteValidacion') {
            throw new ReglaNegocioException('Solo se completa el alta de un pre-registro con el correo verificado y pendiente de validación.');
        }

        return [
            'pre_registro_id' => (string) $p['id'], 'tipo_cliente' => 'PersonaHumana', 'nombre' => (string) $p['nombre'], 'apellido' => (string) $p['apellido'],
            'tipo_documento' => (string) $p['tipo_documento'], 'nro_documento' => (string) $p['nro_documento'], 'cuit_cuil' => (string) $p['cuit_cuil'],
            'correo' => (string) $p['correo'], 'telefono' => (string) ($p['telefono'] ?? ''),
        ];
    }

    /** CU-104 paso 7: lo llama ClienteService::registrar() dentro de la transacción del alta (CU-11). */
    public function completar(int $id, int $clienteId, int $usuarioId): void
    {
        $p = $this->db->query('SELECT id, estado FROM dbo.pre_registros WITH (UPDLOCK, ROWLOCK) WHERE id = ?', [$id])->getRowArray();
        if ($p === null || $p['estado'] !== 'PendienteValidacion') {
            throw new ReglaNegocioException('El pre-registro ya fue completado o descartado: vuelva a abrirlo desde la lista de pre-registros.');
        }
        $this->db->query(
            "UPDATE dbo.pre_registros SET estado = 'Completado', cliente_id = ?, usuario_id = ?, actualizado_en = ?, version_fila = version_fila + 1 WHERE id = ?",
            [$clienteId, $usuarioId, $this->ahoraUtc(), $id],
        );
        (new AlertaService($this->db))->resolver('pre_registros', $id, (string) (session('cuenta')['nombre'] ?? 'Analista de Crédito'));
    }

    /**
     * El Analista descarta un pre-registro que no corresponde (identidad no verificada, datos falsos, persona que no
     * se presentó). Queda como antecedente con su motivo.
     */
    public function descartar(int $id, int $versionLeida, string $motivoTexto, int $usuarioId): void
    {
        $motivo = Campos::texto($motivoTexto);
        $error = Campos::motivo($motivo) ?? (mb_strlen($motivo) > 500 ? 'El motivo admite hasta 500 caracteres.' : null);
        if ($error !== null) {
            throw new ValidacionException(['motivo' => $error]);
        }
        $this->transaccion(function () use ($id, $versionLeida, $motivo, $usuarioId): void {
            $p = $this->bloquear('pre_registros', [$id])[0] ?? null;
            if ($p === null || !in_array($p['estado'], self::ACTIVOS, true)) {
                throw new ReglaNegocioException('Solo se descarta un pre-registro pendiente.');
            }
            $this->actualizarConVersion('pre_registros', $id, $versionLeida, [
                'estado' => 'Descartado', 'motivo_descarte' => $motivo, 'usuario_id' => $usuarioId, 'hash_codigo' => null, 'actualizado_en' => $this->ahoraUtc(),
            ]);
            (new AlertaService($this->db))->resolver('pre_registros', $id, (string) (session('cuenta')['nombre'] ?? 'Analista de Crédito'));
            (new AuditoriaService($this->db))->registrar('pre-registros.descartar', 'pre_registros', $id, 'Exitoso', $motivo);
        });
    }
}
