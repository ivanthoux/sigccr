<?php

declare(strict_types=1);

namespace App\Services\Clientes;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Clientes\ReglasClientes;
use App\Libraries\Clientes\RequisitosDocumentales;
use App\Libraries\Validacion\Campos;
use App\Libraries\Validacion\ValidadorCliente;
use App\Services\Acceso\AutorizacionService;
use App\Services\BaseService;
use App\Services\Comunes\AlertaService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ComunicacionService;
use App\Services\Comunes\GeografiaService;
use Throwable;

/**
 * Padrón de clientes (SS-02): CU-11 a CU-15 y CU-54. RN-07 a RN-12, RN-44, RN-58, RN-64, RN-75.
 *
 * Herencia por tablas de clase: clientes (datos comunes) + personas_humanas o personas_juridicas. Cada cliente tiene
 * una cuenta de acceso al portal (cuentas_acceso + cuentas_cliente) creada Pendiente de activación; su correo se
 * verifica con un enlace antes de poder usarse (RN-08).
 */
class ClienteService extends BaseService
{
    private const RUTA_VERIFICACION = 'portal/verificar-correo';
    /** Clientes que muestra la pantalla inicial de CU-54 antes de buscar. */
    public const CANTIDAD_RECIENTES = 50;

    /**
     * CU-54 Localizar Cliente.
     *
     * @param array{texto?: string, documento?: string, cuit?: string, tipo?: string, estado?: string} $c
     * @return list<array<string, mixed>>
     */
    public function buscar(array $c): array
    {
        $b = $this->db->table('clientes c')
            ->select("c.id, c.tipo_cliente, c.denominacion, c.cuit_cuil, c.correo, c.estado, ph.tipo_documento, ph.nro_documento")
            ->join('personas_humanas ph', 'ph.cliente_id = c.id', 'left');
        foreach (preg_split('/\s+/u', Campos::texto($c['texto'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $palabra) {
            $b->like('c.denominacion', trim($palabra, ','));
        }
        if (($c['documento'] ?? '') !== '') {
            $b->where('ph.nro_documento', Campos::documentoFlexible($c['documento']));
        }
        if (($c['cuit'] ?? '') !== '') {
            $b->where('c.cuit_cuil', str_replace(['-', ' ', '.'], '', $c['cuit']));
        }
        if (in_array($c['tipo'] ?? '', ['PersonaHumana', 'PersonaJuridica'], true)) {
            $b->where('c.tipo_cliente', $c['tipo']);
        }
        if (in_array($c['estado'] ?? '', ['Activo', 'Desactivado'], true)) {
            $b->where('c.estado', $c['estado']);
        }

        return $b->orderBy('c.denominacion')->get(500)->getResultArray();
    }

    /**
     * CU-54 paso 1: últimos clientes registrados, para la pantalla inicial antes de buscar.
     *
     * @return list<array<string, mixed>>
     */
    public function recientes(int $cantidad = self::CANTIDAD_RECIENTES): array
    {
        return $this->db->table('clientes c')
            ->select('c.id, c.tipo_cliente, c.denominacion, c.cuit_cuil, c.correo, c.estado, ph.tipo_documento, ph.nro_documento')
            ->join('personas_humanas ph', 'ph.cliente_id = c.id', 'left')
            ->orderBy('c.id', 'DESC')->get($cantidad)->getResultArray();
    }

    /**
     * Perfil del cliente (CU-15 paso 3) con cuenta del portal, consentimientos vigentes y legajo.
     *
     * @return array<string, mixed>
     */
    public function obtener(int $id): array
    {
        $this->liberarBloqueosVencidos();
        $c = $this->db->query(
            'SELECT c.*, ph.tipo_documento, ph.nro_documento, ph.nombre, ph.apellido, ph.fecha_nacimiento, ph.estado_civil, ph.ocupacion, ph.empleador, '
            . 'pj.razon_social, pj.fecha_constitucion, pj.actividad_principal, pj.representante_nombre, pj.representante_documento, pj.representante_cargo, '
            . 'ca.estado AS estado_cuenta, ca.correo_verificado, ca.correo AS correo_cuenta, '
            . 'lo.nombre AS localidad, lo.departamento, lo.provincia_id, pr.nombre AS provincia '
            . 'FROM dbo.clientes c LEFT JOIN dbo.personas_humanas ph ON ph.cliente_id = c.id LEFT JOIN dbo.personas_juridicas pj ON pj.cliente_id = c.id '
            . 'LEFT JOIN dbo.localidades lo ON lo.id = c.localidad_id LEFT JOIN dbo.provincias pr ON pr.id = lo.provincia_id '
            . 'LEFT JOIN dbo.cuentas_acceso ca ON ca.id = c.cuenta_cliente_id WHERE c.id = ?',
            [$id],
        )->getRowArray();
        if ($c === null) {
            throw new ReglaNegocioException('El cliente no existe.');
        }
        $c['correo_pendiente'] = $c['cuenta_cliente_id'] === null ? null : ($this->db->query(
            "SELECT TOP 1 correo_destino FROM dbo.autorizaciones_temporales WHERE cuenta_acceso_id = ? AND proposito = 'VerificacionCorreo' "
            . "AND estado = 'Vigente' AND vence > SYSDATETIMEOFFSET() ORDER BY id DESC",
            [$c['cuenta_cliente_id']],
        )->getRowArray()['correo_destino'] ?? null);
        $c['consentimientos'] = [];
        foreach (['PoliticaDatos', 'ComunicacionesComerciales'] as $tipo) {
            $c['consentimientos'][$tipo] = $this->db->query(
                'SELECT TOP 1 otorgado, fecha, medio FROM dbo.consentimientos WHERE cliente_id = ? AND tipo = ? ORDER BY fecha DESC, id DESC',
                [$id, $tipo],
            )->getRowArray();
        }
        $c['documentos'] = (new LegajoService($this->db))->vigentes($id);
        $c['desactivacion'] = $c['estado'] !== 'Desactivado' ? null : $this->db->query(
            "SELECT TOP 1 fecha_hora, motivo, responsable FROM dbo.registros_auditoria WHERE accion = 'clientes.desactivar' AND objeto_tipo = 'clientes' "
            . "AND objeto_id = ? AND resultado = 'Exitoso' ORDER BY id DESC",
            [(string) $id],
        )->getRowArray();

        return $c;
    }

    /** CU-15: la consulta del perfil y del historial queda en la auditoría (RNF-14). */
    public function registrarConsulta(int $id, string $que): void
    {
        (new AuditoriaService($this->db))->registrar('clientes.consultar_' . $que, 'clientes', $id);
    }

    /**
     * CU-15 pasos 4 y 5: historial crediticio consolidado.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function historial(int $id): array
    {
        $q = fn (string $sql) => $this->db->query($sql, [$id])->getResultArray();

        return [
            'solicitudes' => $q('SELECT id, numero, fecha_registro, importe, cantidad_cuotas, estado FROM dbo.solicitudes_credito WHERE cliente_id = ? ORDER BY fecha_registro DESC'),
            'creditos' => $q(
                'SELECT cr.id, cr.numero, cr.origen, cr.fecha_liquidacion, cr.capital, cr.cantidad_cuotas, cr.estado, '
                . "(SELECT COUNT(*) FROM dbo.cuotas cu WHERE cu.credito_id = cr.id AND cu.estado = 'Pagada') AS cuotas_pagadas, "
                . "(SELECT COUNT(*) FROM dbo.cuotas cu WHERE cu.credito_id = cr.id AND cu.estado = 'Vencida') AS cuotas_vencidas "
                . 'FROM dbo.creditos cr WHERE cr.cliente_id = ? ORDER BY cr.fecha_liquidacion DESC'
            ),
            'refinanciaciones' => $q("SELECT id, numero, fecha_registro, capital_refinanciado, cantidad_cuotas, tasa, estado FROM dbo.propuestas_refinanciacion WHERE cliente_id = ? AND estado = 'Aplicada' ORDER BY fecha_registro DESC"),
            'evaluaciones' => $q(
                'SELECT e.id, s.numero AS solicitud, e.modalidad, e.resultado, e.puntaje, e.categoria_riesgo, e.fecha_finalizacion, e.vence, e.vigencia '
                . "FROM dbo.evaluaciones_crediticias e JOIN dbo.solicitudes_credito s ON s.id = e.solicitud_id WHERE s.cliente_id = ? AND e.procesamiento = 'Finalizada' "
                . 'ORDER BY e.fecha_finalizacion DESC'
            ),
            'mora' => $q(
                'SELECT cr.numero AS credito, cu.numero AS cuota, cu.vencimiento, cu.estado '
                . "FROM dbo.cuotas cu JOIN dbo.creditos cr ON cr.id = cu.credito_id WHERE cr.cliente_id = ? AND cu.estado = 'Vencida' ORDER BY cu.vencimiento"
            ),
        ];
    }

    /**
     * CU-11 Registrar Cliente.
     *
     * @param array<string, mixed> $datos
     * @param list<array{codigo: string, nombre: string, temporal: string, tamano: int}> $archivos documentación adjunta
     */
    public function registrar(array $datos, array $archivos, int $usuarioId): int
    {
        $d = ValidadorCliente::normalizar($datos);
        $errores = ValidadorCliente::validar($d, true, $this->hoyNegocio());
        $this->validarLocalidad($d, null, $errores);
        $documentos = (new LegajoService($this->db))->validarArchivos($archivos, $errores);
        // D-14: el alta solo exige identificar al cliente; la documentación de ingresos la exige cada línea en CU-22
        foreach (RequisitosDocumentales::faltantes(RequisitosDocumentales::identificacion($d['tipo_cliente']), array_column($documentos, 'codigo')) as $falta) {
            $errores['doc_' . $falta] = 'Adjunte este documento: es obligatorio para identificar al cliente (RN-11).';
        }
        if ($errores !== []) {
            throw new ValidacionException($errores, 'RN-11');
        }

        $movidos = [];
        try {
            [$id, $comunicacionId] = $this->transaccion(function () use ($d, $documentos, $usuarioId, &$movidos): array {
                $this->verificarUnicidad($d, null, null);
                $ahora = $this->ahoraUtc();
                $this->db->table('clientes')->insert([
                    'tipo_cliente' => $d['tipo_cliente'], 'cuit_cuil' => $d['cuit_cuil'] === '' ? null : $d['cuit_cuil'],
                    'denominacion' => ValidadorCliente::denominacion($d), 'correo' => $d['correo'],
                    'telefono' => $d['telefono'] === '' ? null : $d['telefono'], 'domicilio' => $d['domicilio'],
                    'localidad_id' => $d['localidad_id'], 'codigo_postal' => $d['codigo_postal'],
                    'ingresos_declarados' => $d['ingresos_declarados'], 'categoria_ingresos' => $d['categoria_ingresos'],
                    'estado' => 'Activo', 'fecha_alta' => $this->hoyNegocio(), 'creado_en' => $ahora,
                ]);
                $id = (int) $this->db->insertID();
                $this->db->table($d['tipo_cliente'] === 'PersonaJuridica' ? 'personas_juridicas' : 'personas_humanas')
                    ->insert(['cliente_id' => $id] + $this->datosSubclase($d));

                // Cuenta del portal, Pendiente de activación (postcondición de CU-11); el correo se verifica primero
                $this->db->table('cuentas_acceso')->insert(['tipo_cuenta' => 'Cliente', 'correo' => $d['correo'], 'correo_verificado' => 0, 'estado' => 'PendienteActivacion', 'creado_en' => $ahora]);
                $cuentaId = (int) $this->db->insertID();
                $this->db->table('cuentas_cliente')->insert(['id' => $cuentaId]);
                $this->db->table('clientes')->where('id', $id)->update(['cuenta_cliente_id' => $cuentaId]);

                $this->registrarConsentimiento($id, 'PoliticaDatos', true);
                if ($d['acepta_comunicaciones']) {
                    $this->registrarConsentimiento($id, 'ComunicacionesComerciales', true);
                }
                foreach ($documentos as $doc) {
                    $movidos[] = (new LegajoService($this->db))->guardar($id, $doc, $usuarioId)['ruta'];
                }
                $comunicacionId = (new AutorizacionService($this->db))->emitir($cuentaId, 'VerificacionCorreo', $d['correo'], $d['tipo_cliente'] === 'PersonaJuridica' ? $d['representante_nombre'] : $d['nombre'], self::RUTA_VERIFICACION, 'verificacion_correo_cliente');
                (new AuditoriaService($this->db))->registrar('clientes.registrar', 'clientes', $id, 'Exitoso', null, [
                    'tipo' => $d['tipo_cliente'], 'denominacion' => ValidadorCliente::denominacion($d), 'categoria' => $d['categoria_ingresos'],
                    'documentos' => count($documentos), 'comunicaciones_comerciales' => $d['acepta_comunicaciones'],
                ]);

                return [$id, $comunicacionId];
            });
        } catch (Throwable $e) {
            LegajoService::descartar($movidos);
            throw $e;
        }
        (new ComunicacionService())->enviar($comunicacionId);

        return $id;
    }

    /**
     * CU-12 Modificar Datos del Cliente.
     *
     * @param array<string, mixed> $datos
     * @return array{verificacion: bool, solicitudes_afectadas: int}
     */
    public function modificar(int $id, int $versionLeida, array $datos, string $evidenciaIdentidad): array
    {
        $actual = $this->obtener($id);
        $d = ValidadorCliente::normalizar(['tipo_cliente' => $actual['tipo_cliente']] + $datos);
        $errores = ValidadorCliente::validar($d, false, $this->hoyNegocio());
        $this->validarLocalidad($d, $actual['localidad_id'] === null ? null : (int) $actual['localidad_id'], $errores);
        $correoNuevo = $d['correo'] !== $actual['correo'] ? $d['correo'] : null;
        $evidencia = Campos::texto($evidenciaIdentidad);
        if ($correoNuevo !== null && mb_strlen($evidencia) < 10) {
            // A3 / RN-58: el cambio de correo en sucursal exige comprobar presencialmente documento e identidad
            $errores['evidencia_identidad'] = 'Para cambiar el correo, describa cómo comprobó presencialmente el documento y la identidad del cliente.';
        }
        if ($errores !== []) {
            throw new ValidacionException($errores);
        }

        $resultado = $this->transaccion(function () use ($id, $versionLeida, $d, $actual, $correoNuevo, $evidencia): array {
            $bloqueado = $this->bloquear('clientes', [$id])[0] ?? null;
            if ($bloqueado === null || $bloqueado['estado'] !== 'Activo') {
                throw new ReglaNegocioException('Solo se pueden modificar los datos de un cliente activo.');
            }
            $this->verificarUnicidad($d, $id, $correoNuevo);

            $nuevos = [
                'cuit_cuil' => $d['cuit_cuil'] === '' ? null : $d['cuit_cuil'], 'denominacion' => ValidadorCliente::denominacion($d),
                'telefono' => $d['telefono'] === '' ? null : $d['telefono'], 'domicilio' => $d['domicilio'],
                'localidad_id' => $d['localidad_id'], 'codigo_postal' => $d['codigo_postal'],
                'ingresos_declarados' => $d['ingresos_declarados'], 'categoria_ingresos' => $d['categoria_ingresos'],
            ] + $this->datosSubclase($d);
            $cambios = [];
            foreach ($nuevos as $campo => $valor) {
                $anterior = $actual[$campo] ?? null;
                $igual = $campo === 'ingresos_declarados' ? Campos::importe((string) $anterior) === $valor : (string) ($anterior ?? '') === (string) ($valor ?? '');
                if (!$igual) {
                    $cambios[$campo] = ['anterior' => $anterior, 'nuevo' => $valor];
                }
            }
            $consentimientoActual = (bool) ($actual['consentimientos']['ComunicacionesComerciales']['otorgado'] ?? false);
            $cambiaConsentimiento = $consentimientoActual !== $d['acepta_comunicaciones'];
            if ($cambios === [] && $correoNuevo === null && !$cambiaConsentimiento) {
                throw new ReglaNegocioException('No hay cambios para guardar.');
            }

            $this->actualizarConVersion('clientes', $id, $versionLeida, array_intersect_key($nuevos, array_flip(['cuit_cuil', 'denominacion', 'telefono', 'domicilio', 'localidad_id', 'codigo_postal', 'ingresos_declarados', 'categoria_ingresos'])) + ['actualizado_en' => $this->ahoraUtc()]);
            $this->db->table($d['tipo_cliente'] === 'PersonaJuridica' ? 'personas_juridicas' : 'personas_humanas')->where('cliente_id', $id)->update($this->datosSubclase($d));

            if ($cambiaConsentimiento) {
                $this->registrarConsentimiento($id, 'ComunicacionesComerciales', $d['acepta_comunicaciones']);
                $cambios['comunicaciones_comerciales'] = ['anterior' => $consentimientoActual, 'nuevo' => $d['acepta_comunicaciones']];
                if (!$d['acepta_comunicaciones']) {
                    $this->cancelarComunicacionesComerciales($id);
                }
            }

            $comunicacionId = null;
            if ($correoNuevo !== null) {
                $cuentaId = (int) $actual['cuenta_cliente_id'];
                (new AutorizacionService($this->db))->sustituirVigentes($cuentaId);
                $comunicacionId = (new AutorizacionService($this->db))->emitir($cuentaId, 'VerificacionCorreo', $correoNuevo, (string) ($d['nombre'] ?? $d['representante_nombre']), self::RUTA_VERIFICACION, 'verificacion_correo_cliente');
                $cambios['correo'] = ['anterior' => $actual['correo'], 'nuevo' => "{$correoNuevo} (pendiente de verificación)", 'evidencia_identidad' => $evidencia];
            }

            // RN-12: si cambiaron datos usados en la evaluación, se invalidan las evaluaciones vigentes de las
            // solicitudes en curso y se alerta a los responsables
            $afectadas = 0;
            $relevantes = ReglasClientes::cambiosRelevantes($actual, $nuevos);
            if ($relevantes !== []) {
                $afectadas = $this->tratarSolicitudesAfectadas($id, $actual['denominacion'], $relevantes);
                $cambios['solicitudes_afectadas'] = $afectadas;
            }
            (new AuditoriaService($this->db))->registrar('clientes.modificar', 'clientes', $id, 'Exitoso', null, $cambios);

            return ['comunicacion' => $comunicacionId, 'solicitudes_afectadas' => $afectadas];
        });
        if ($resultado['comunicacion'] !== null) {
            (new ComunicacionService())->enviar($resultado['comunicacion']);
        }

        return ['verificacion' => $resultado['comunicacion'] !== null, 'solicitudes_afectadas' => $resultado['solicitudes_afectadas']];
    }

    /**
     * CU-11 paso 8 y CU-12 paso 8: el cliente confirma su correo desde el enlace.
     *
     * @return int identificador de la cuenta de acceso del cliente, para continuar con su activación (D-16)
     */
    public function verificarCorreo(string $codigo): int
    {
        return $this->transaccion(function () use ($codigo): int {
            $autorizaciones = new AutorizacionService($this->db);
            $a = $autorizaciones->buscarVigente($codigo, ['VerificacionCorreo'], true);
            $cliente = $a === null ? null : $this->db->table('clientes')->select('id, correo')->where('cuenta_cliente_id', $a['cuenta_acceso_id'])->get()->getRowArray();
            if ($a === null || $cliente === null) {
                throw new ReglaNegocioException('El enlace de verificación expiró o ya no es válido. Pida en la sucursal que le envíen uno nuevo.', 'RN-03');
            }
            $correo = (string) $a['correo_destino'];
            $cuentaId = (int) $a['cuenta_acceso_id'];
            $repetido = $this->db->table('cuentas_acceso')->where('tipo_cuenta', 'Cliente')->where('correo', $correo)->where('id !=', $cuentaId)->countAllResults() > 0;
            if ($repetido) {
                throw new ReglaNegocioException('Ese correo ya está registrado para otro cliente.', 'RN-08');
            }
            $this->db->query('UPDATE dbo.cuentas_acceso SET correo = ?, correo_verificado = 1, actualizado_en = ?, version_fila = version_fila + 1 WHERE id = ?', [$correo, $this->ahoraUtc(), $cuentaId]);
            $this->db->query('UPDATE dbo.clientes SET correo = ?, actualizado_en = ?, version_fila = version_fila + 1 WHERE id = ?', [$correo, $this->ahoraUtc(), $cliente['id']]);
            $autorizaciones->marcarUtilizada((int) $a['id']);
            (new AuditoriaService($this->db))->registrar('clientes.verificar_correo', 'clientes', (int) $cliente['id'], 'Exitoso', null, ['correo' => ['anterior' => $cliente['correo'], 'nuevo' => $correo]], null, $cuentaId, $correo);

            return $cuentaId;
        });
    }

    /** CU-13 Desactivar Cliente (RN-09). */
    public function desactivar(int $id, int $versionLeida, string $motivo): void
    {
        $motivo = $this->exigirMotivo($motivo);
        $this->transaccion(function () use ($id, $versionLeida, $motivo): void {
            $c = $this->bloquear('clientes', [$id])[0] ?? null;
            if ($c === null) {
                throw new ReglaNegocioException('El cliente no existe.');
            }
            $rechazos = ReglasClientes::motivosContraDesactivar($c['estado'], $this->pendientes($id));
            if ($rechazos !== []) {
                throw new ReglaNegocioException('No se puede desactivar: tiene ' . implode('; ', $rechazos) . '. Deben resolverse antes (RN-09).', 'RN-09');
            }
            $this->actualizarConVersion('clientes', $id, $versionLeida, ['estado' => 'Desactivado', 'actualizado_en' => $this->ahoraUtc()]);
            if ($c['cuenta_cliente_id'] !== null) {
                // Cuenta del portal desactivada, sesiones finalizadas y autorizaciones invalidadas
                $this->db->query("UPDATE dbo.cuentas_acceso SET estado = 'Desactivada', version_seguridad = version_seguridad + 1, version_fila = version_fila + 1 WHERE id = ?", [$c['cuenta_cliente_id']]);
                (new AutorizacionService($this->db))->sustituirVigentes((int) $c['cuenta_cliente_id']);
            }
            (new AuditoriaService($this->db))->registrar('clientes.desactivar', 'clientes', $id, 'Exitoso', $motivo);
        });
    }

    /**
     * CU-14 Reactivar Cliente. La cuenta del portal no se reactiva acá: el cliente la rehabilita con CU-04 (RN-10).
     *
     * @return bool true si el cliente tiene el correo verificado (A2)
     */
    public function reactivar(int $id, int $versionLeida, string $motivo): bool
    {
        $motivo = $this->exigirMotivo($motivo);

        return $this->transaccion(function () use ($id, $versionLeida, $motivo): bool {
            $c = $this->bloquear('clientes', [$id])[0] ?? null;
            if ($c === null || $c['estado'] !== 'Desactivado') {
                throw new ReglaNegocioException('El cliente no está desactivado.');
            }
            $this->actualizarConVersion('clientes', $id, $versionLeida, ['estado' => 'Activo', 'actualizado_en' => $this->ahoraUtc()]);
            (new AuditoriaService($this->db))->registrar('clientes.reactivar', 'clientes', $id, 'Exitoso', $motivo);
            $verificado = $c['cuenta_cliente_id'] === null ? null : $this->db->table('cuentas_acceso')->select('correo_verificado')->where('id', $c['cuenta_cliente_id'])->get()->getRowArray();

            return (int) ($verificado['correo_verificado'] ?? 0) === 1;
        });
    }

    /**
     * Incorpora documentos al legajo de un cliente activo (CU-12; RN-11). Incorporar no equivale a verificar.
     *
     * @param list<array{codigo: string, nombre: string, temporal: string, tamano: int}> $archivos
     */
    public function agregarDocumentos(int $id, array $archivos, int $usuarioId): int
    {
        $errores = [];
        $documentos = (new LegajoService($this->db))->validarArchivos($archivos, $errores);
        if ($documentos === [] && $errores === []) {
            $errores['documentos'] = 'Seleccione al menos un archivo.';
        }
        if ($errores !== []) {
            throw new ValidacionException($errores);
        }
        $movidos = [];
        try {
            $this->transaccion(function () use ($id, $documentos, $usuarioId, &$movidos): void {
                $c = $this->bloquear('clientes', [$id])[0] ?? null;
                if ($c === null || $c['estado'] !== 'Activo') {
                    throw new ReglaNegocioException('Solo se puede agregar documentación al legajo de un cliente activo.');
                }
                foreach ($documentos as $doc) {
                    $movidos[] = (new LegajoService($this->db))->guardar($id, $doc, $usuarioId)['ruta'];
                }
                // RN-61: un documento clave nuevo devuelve a verificación las solicitudes que ya pasaron por ella
                if (array_filter($documentos, static fn (array $d) => (int) $d['es_clave'] === 1) !== []) {
                    (new \App\Services\Solicitudes\SolicitudService($this->db))->documentoClaveIncorporado($id, $usuarioId);
                }
                (new AuditoriaService($this->db))->registrar('clientes.agregar_documentos', 'clientes', $id, 'Exitoso', null, ['documentos' => array_column($documentos, 'nombre')]);
            });
        } catch (Throwable $e) {
            LegajoService::descartar($movidos);
            throw $e;
        }

        return count($documentos);
    }

    /**
     * Archivo de un documento del legajo para descargarlo; la descarga queda auditada (RNF-12, RNF-14).
     *
     * @return array{ruta: string, nombre: string, mime: string}
     */
    public function documento(int $clienteId, int $documentoId): array
    {
        $d = $this->db->table('documentos_legajo')->select('ruta_archivo, nombre_original, tipo_mime')
            ->where('id', $documentoId)->where('cliente_id', $clienteId)->get()->getRowArray();
        if ($d === null || !is_file(WRITEPATH . $d['ruta_archivo'])) {
            throw new ReglaNegocioException('El documento no existe.');
        }
        (new AuditoriaService($this->db))->registrar('clientes.descargar_documento', 'documentos_legajo', $documentoId, 'Exitoso', null, ['cliente' => $clienteId]);

        return ['ruta' => WRITEPATH . $d['ruta_archivo'], 'nombre' => $d['nombre_original'], 'mime' => $d['tipo_mime']];
    }

    /** @return list<array{id: int, codigo: string, nombre: string, aplica_a: string}> */
    public function tiposDocumentacion(): array
    {
        return (new LegajoService($this->db))->tiposDocumentacion();
    }

    // ── Auxiliares ─────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $d
     * @return array<string, mixed>
     */
    /**
     * D-18: la localidad existe, pertenece a la provincia elegida y está activa (una localidad dada de baja del
     * catálogo se conserva en el cliente que ya la tenía).
     *
     * @param array<string, mixed>  $d
     * @param array<string, string> $errores
     */
    private function validarLocalidad(array $d, ?int $actual, array &$errores): void
    {
        if ($d['localidad_id'] === 0 || isset($errores['provincia_id'])) {
            return;
        }
        $l = (new GeografiaService($this->db))->localidad($d['localidad_id']);
        if ($l === null || $l['provincia_id'] !== $d['provincia_id']) {
            $errores['localidad_id'] = 'Seleccione una localidad de la provincia elegida.';
        } elseif ($l['activa'] !== 1 && $l['id'] !== $actual) {
            $errores['localidad_id'] = 'La localidad ya no está disponible: seleccione otra.';
        }
    }

    private function datosSubclase(array $d): array
    {
        $nulo = static fn (string $v): ?string => $v === '' ? null : $v;
        if ($d['tipo_cliente'] === 'PersonaJuridica') {
            return [
                'razon_social' => $d['razon_social'], 'fecha_constitucion' => $d['fecha_constitucion'], 'actividad_principal' => $d['actividad_principal'],
                'representante_nombre' => $d['representante_nombre'], 'representante_documento' => $d['representante_documento'], 'representante_cargo' => $d['representante_cargo'],
            ];
        }

        return [
            'tipo_documento' => $d['tipo_documento'], 'nro_documento' => $d['nro_documento'], 'nombre' => $d['nombre'], 'apellido' => $d['apellido'],
            'fecha_nacimiento' => $d['fecha_nacimiento'], 'estado_civil' => $nulo($d['estado_civil']), 'ocupacion' => $nulo($d['ocupacion']), 'empleador' => $nulo($d['empleador']),
        ];
    }

    /**
     * RN-07 y RN-08: documento, CUIT/CUIL y correo únicos en el padrón. $correo null: no se cambia el correo.
     *
     * @param array<string, mixed> $d
     */
    private function verificarUnicidad(array $d, ?int $excluirId, ?string $correo): void
    {
        $errores = [];
        $excluir = static function ($b, string $col) use ($excluirId) {
            return $excluirId === null ? $b : $b->where("{$col} !=", $excluirId);
        };
        if (
            $d['tipo_cliente'] === 'PersonaHumana'
            && $excluir($this->db->table('personas_humanas')->where('tipo_documento', $d['tipo_documento'])->where('nro_documento', $d['nro_documento']), 'cliente_id')->countAllResults() > 0
        ) {
            $errores['nro_documento'] = 'El documento ya está registrado para otro cliente.';
        }
        if ($d['cuit_cuil'] !== '' && $excluir($this->db->table('clientes')->where('cuit_cuil', $d['cuit_cuil']), 'id')->countAllResults() > 0) {
            $errores['cuit_cuil'] = 'El CUIT/CUIL ya está registrado para otro cliente.';
        }
        $correoAControlar = $excluirId === null ? $d['correo'] : $correo;
        if ($correoAControlar !== null) {
            $enClientes = $excluir($this->db->table('clientes')->where('correo', $correoAControlar), 'id')->countAllResults() > 0;
            $cuentas = $this->db->table('cuentas_acceso ca')->join('clientes c', 'c.cuenta_cliente_id = ca.id', 'left')->where('ca.tipo_cuenta', 'Cliente')->where('ca.correo', $correoAControlar);
            if ($excluirId !== null) {
                $cuentas->groupStart()->where('c.id IS NULL', null, false)->orWhere('c.id !=', $excluirId)->groupEnd();
            }
            if ($enClientes || $cuentas->countAllResults() > 0) {
                $errores['correo'] = 'El correo electrónico ya está registrado para otro cliente.';
            }
        }
        if ($errores !== []) {
            throw new ValidacionException($errores, 'RN-07');
        }
    }

    private function registrarConsentimiento(int $clienteId, string $tipo, bool $otorgado): void
    {
        $this->db->table('consentimientos')->insert(['cliente_id' => $clienteId, 'tipo' => $tipo, 'otorgado' => $otorgado ? 1 : 0, 'fecha' => $this->ahoraUtc(), 'medio' => 'Sucursal']);
    }

    /** RN-64: la revocación cancela de inmediato las comunicaciones comerciales pendientes del cliente. */
    private function cancelarComunicacionesComerciales(int $clienteId): void
    {
        $this->db->query(
            "UPDATE dbo.comunicaciones SET estado = 'Cancelada', datos_cifrados = NULL, version_fila = version_fila + 1 "
            . "WHERE tipo = 'Oferta' AND estado IN ('Pendiente', 'ReintentoProgramado') AND origen_tipo = 'ofertas' "
            . 'AND origen_id IN (SELECT id FROM dbo.ofertas WHERE cliente_id = ?)',
            [$clienteId],
        );
    }

    /**
     * RN-12, CU-12 pasos 6 y 9, A4: invalida las evaluaciones vigentes de las solicitudes en curso y alerta a sus
     * responsables. Devuelve la cantidad de solicitudes afectadas. La devolución del trámite a la etapa que
     * corresponda la aplica el ciclo de la solicitud (SS-04) al detectar la evaluación invalidada.
     *
     * @param list<string> $campos
     */
    private function tratarSolicitudesAfectadas(int $clienteId, string $denominacion, array $campos): int
    {
        $marcas = implode(', ', array_fill(0, count(ReglasClientes::SOLICITUD_FINAL), '?'));
        $solicitudes = $this->db->query(
            "SELECT id, numero, usuario_id FROM dbo.solicitudes_credito WHERE cliente_id = ? AND estado NOT IN ({$marcas})",
            [$clienteId, ...ReglasClientes::SOLICITUD_FINAL],
        )->getResultArray();
        $alertas = new AlertaService($this->db);
        foreach ($solicitudes as $s) {
            $this->db->query("UPDATE dbo.evaluaciones_crediticias SET vigencia = 'Invalidada', version_fila = version_fila + 1 WHERE solicitud_id = ? AND vigencia = 'Vigente'", [$s['id']]);
            $causa = "Cambiaron datos de {$denominacion} usados en la evaluación de la solicitud {$s['numero']} (" . implode(', ', $campos) . ').';
            $alertas->generar('Operativa', $causa, 'Revisar la solicitud: la evaluación anterior quedó invalidada (RN-12).', $s['usuario_id'] === null ? 'AnalistaCredito' : null, $s['usuario_id'] === null ? null : (int) $s['usuario_id'], 'solicitudes_credito', $s['id']);
        }

        return count($solicitudes);
    }

    /** @return array{cuotas: int, solicitudes: int, propuestas: int, saldos: int, ordenes: int, ofertas: int} */
    private function pendientes(int $id): array
    {
        $n = fn (string $sql, array $p = []): int => (int) $this->db->query($sql, [$id, ...$p])->getRowArray()['n'];
        $marcas = implode(', ', array_fill(0, count(ReglasClientes::SOLICITUD_FINAL), '?'));

        return [
            'cuotas' => $n("SELECT COUNT(*) AS n FROM dbo.cuotas cu JOIN dbo.creditos cr ON cr.id = cu.credito_id WHERE cr.cliente_id = ? AND cu.estado IN ('Pendiente', 'Vencida')"),
            'solicitudes' => $n("SELECT COUNT(*) AS n FROM dbo.solicitudes_credito WHERE cliente_id = ? AND estado NOT IN ({$marcas})", ReglasClientes::SOLICITUD_FINAL),
            'propuestas' => $n("SELECT COUNT(*) AS n FROM dbo.propuestas_refinanciacion WHERE cliente_id = ? AND estado IN ('PendienteResolucion', 'AprobadaPendienteAceptacion', 'Observada')"),
            'saldos' => $n('SELECT COUNT(*) AS n FROM dbo.saldos_a_favor WHERE cliente_id = ? AND (importe_disponible > 0 OR importe_reservado > 0)'),
            'ordenes' => $n("SELECT COUNT(*) AS n FROM dbo.ordenes_pago_electronico WHERE cliente_id = ? AND estado IN ('PendienteConfirmacion', 'EnConciliacion')"),
            'ofertas' => $n("SELECT COUNT(*) AS n FROM dbo.ofertas WHERE cliente_id = ? AND estado = 'Vigente'"),
        ];
    }

    private function exigirMotivo(string $motivo): string
    {
        $motivo = Campos::texto($motivo);
        $error = Campos::motivo($motivo);
        if ($error !== null) {
            throw new ValidacionException(['motivo' => $error]);
        }

        return $motivo;
    }
}
