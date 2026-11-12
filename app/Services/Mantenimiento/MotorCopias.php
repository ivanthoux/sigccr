<?php

declare(strict_types=1);

namespace App\Services\Mantenimiento;

use App\Exceptions\CopiaCorruptaException;
use App\Exceptions\ReglaNegocioException;
use App\Libraries\Copias\CifradoCopia;
use App\Libraries\Copias\EstadoRestauracion;
use App\Libraries\Copias\PaqueteCopia;
use App\Libraries\Copias\PoliticaCopias;
use App\Services\Comunes\AlertaService;
use App\Services\Comunes\AuditoriaService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Config\Database;
use RuntimeException;
use Throwable;

/**
 * Generación y restauración de las copias (CU-61 paso 4 a 7 y A5, CU-63 pasos 7 a 12; RNF-13, D-45). Se ejecuta solo
 * en el contenedor de procesos, el único con acceso de escritura al volumen de copias que comparte con SQL Server
 * (carpeta de mssql y del grupo www-data, con setgid, preparada por docker/php/entrypoint.sh). Usa tres conexiones: la de la aplicación para el catálogo, la de mantenimiento (sigccr_copias, en
 * master) para BACKUP y RESTORE, y la del propietario del esquema para verificar y migrar la base restaurada.
 */
class MotorCopias
{
    public const PROCESO_AUTOMATICA = 'copias:generar';
    public const PROCESO_SOLICITUDES = 'copias:procesar';
    private const BLOQUEO = 'sigccr:copias';

    private string $dir;
    private string $base;
    /** @var BaseConnection<mixed, mixed>|null */
    private ?BaseConnection $mant = null;

    /** @param BaseConnection<mixed, mixed>|null $db conexión de la aplicación */
    public function __construct(private ?BaseConnection $db = null, private ?EstadoRestauracion $estado = null)
    {
        $this->dir = rtrim(config('Sigccr')->directorioCopias, '/');
        $this->base = (string) config('Database')->default['database'];
        $this->estado ??= EstadoRestauracion::predeterminado();
    }

    // ------------------------------------------------------------------ CU-61

    /** A5: copia automática diaria, con el proceso como responsable; si falla, alerta al Administrador. */
    public function copiaAutomatica(): array
    {
        $id = $this->registrar('Automatica', null, 'Proceso ' . self::PROCESO_AUTOMATICA, 'Copia automática diaria (RNF-13)', 'EnCurso');

        return $this->generar($id);
    }

    /**
     * Copias manuales solicitadas desde el panel, en orden.
     *
     * @return list<array<string, mixed>>
     */
    public function procesarSolicitadas(): array
    {
        $r = [];
        foreach ($this->app()->query("SELECT id FROM dbo.copias_seguridad WHERE estado = 'Solicitada' ORDER BY id")->getResultArray() as $c) {
            $r[] = $this->generar((int) $c['id']);
        }

        return $r;
    }

    /**
     * Pasos 4 a 7 (A2, A3). Devuelve el resultado; una falla deja la copia Fallida con su causa.
     *
     * @return array{id: int, estado: string, detalle: ?string}
     */
    public function generar(int $id): array
    {
        $c = $this->app()->table('copias_seguridad')->where('id', $id)->get()->getRowArray();
        if ($c === null) {
            throw new ReglaNegocioException('La copia no existe.');
        }
        $this->bloquear();
        $temporal = $this->dir . '/' . basename((string) $c['archivo'], PoliticaCopias::EXTENSION);
        try {
            $this->app()->table('copias_seguridad')->where('id', $id)->update(['estado' => 'EnCurso']);
            $this->prepararDirectorio();
            $adjuntos = config('Sigccr')->copiasConAdjuntos ? rtrim(WRITEPATH, '/') : null;
            $requerido = PoliticaCopias::espacioRequerido($this->tamanoBase(), $adjuntos === null ? 0 : self::tamanoDirectorio($adjuntos . '/legajos'));
            $libre = @disk_free_space($this->dir);
            if ($libre === false || $libre < $requerido) {
                throw new ReglaNegocioException('El volumen de copias no tiene espacio suficiente: se necesitan ' . PoliticaCopias::tamano($requerido) . ' y hay ' . PoliticaCopias::tamano((int) $libre) . ' libres. Libere espacio o verifique el destino (A2).');
            }
            // Paso 5: copia de la base con sumas de verificación, empaquetada con los adjuntos y cifrada
            $bak = "{$temporal}.bak";
            $this->ejecutar($this->mantenimiento(), "BACKUP DATABASE [{$this->base}] TO DISK = N'{$bak}' WITH INIT, FORMAT, CHECKSUM, COPY_ONLY, NAME = N'SIG-CCR {$c['archivo']}'");
            // Paso 6: integridad de la copia de la base
            $this->ejecutar($this->mantenimiento(), "RESTORE VERIFYONLY FROM DISK = N'{$bak}' WITH CHECKSUM");
            if (!is_readable($bak)) {
                throw new RuntimeException("SQL Server generó la copia sin permiso de lectura para la aplicación: revisar los permisos del volumen {$this->dir} (docker/php/entrypoint.sh).");
            }
            $cantidad = PaqueteCopia::armar($bak, $adjuntos, $adjuntos === null ? [] : $this->rutasLegajo(), "{$temporal}.zip");
            $destino = $this->dir . '/' . $c['archivo'];
            CifradoCopia::cifrar("{$temporal}.zip", $destino, $this->clave());
            $hash = (string) hash_file('sha256', $destino);
            @chmod($destino, 0644);
            $fila = [
                'estado' => 'Verificada', 'hash_verificacion' => $hash, 'tamano_bytes' => (int) filesize($destino), 'fecha_fin' => $this->ahora(),
                'incluye_adjuntos' => $adjuntos !== null ? 1 : 0, 'detalle' => $adjuntos !== null ? "Incluye {$cantidad} archivo(s) del legajo." : null,
            ];
            $this->app()->table('copias_seguridad')->where('id', $id)->update($fila);
            file_put_contents($destino . '.json', PoliticaCopias::manifiesto($fila + $c));
            @chmod($destino . '.json', 0644);
            (new AuditoriaService($this->app()))->registrar('copias.generar', 'copias_seguridad', $id, 'Exitoso', null, ['archivo' => $c['archivo'], 'tamano' => $fila['tamano_bytes'], 'hash' => $hash, 'origen' => $c['origen']], $c['usuario_id'] === null ? self::PROCESO_AUTOMATICA : null, $c['usuario_id'] === null ? null : (int) $c['usuario_id'], (string) $c['responsable']);

            return ['id' => $id, 'estado' => 'Verificada', 'detalle' => null];
        } catch (Throwable $e) {
            // A3: la copia queda Fallida con su causa; el Administrador puede reintentar
            $detalle = mb_substr($e->getMessage(), 0, 1000);
            $this->app()->table('copias_seguridad')->where('id', $id)->update(['estado' => 'Fallida', 'fecha_fin' => $this->ahora(), 'detalle' => $detalle]);
            @unlink($this->dir . '/' . $c['archivo']);
            (new AuditoriaService($this->app()))->registrar('copias.generar', 'copias_seguridad', $id, 'Error', $detalle, ['archivo' => $c['archivo']], $c['usuario_id'] === null ? self::PROCESO_AUTOMATICA : null, $c['usuario_id'] === null ? null : (int) $c['usuario_id'], (string) $c['responsable']);
            if ($c['origen'] !== 'Manual') {
                (new AlertaService($this->app()))->generar('Proceso', 'Falló la copia de seguridad ' . ($c['origen'] === 'Automatica' ? 'automática' : 'previa a la restauración') . ": {$detalle}", 'Revisar el volumen de copias y generar una copia manual (CU-61).', 'Administrador', null, 'copias_seguridad', $id);
            }

            return ['id' => $id, 'estado' => 'Fallida', 'detalle' => $detalle];
        } finally {
            @unlink("{$temporal}.bak");
            @unlink("{$temporal}.zip");
            $this->liberar();
        }
    }

    /**
     * RNF-13: depura las copias con más de los días configurados (se conserva siempre la más reciente).
     *
     * @return int cantidad depurada
     */
    public function depurar(): int
    {
        $copias = $this->app()->query("SELECT id, estado, fecha_generacion, archivo FROM dbo.copias_seguridad WHERE estado = 'Verificada'")->getResultArray();
        $ids = PoliticaCopias::aDepurar($copias, new \DateTimeImmutable('now'), config('Sigccr')->diasConservacionCopias);
        foreach ($copias as $c) {
            if (in_array((int) $c['id'], $ids, true)) {
                @unlink($this->dir . '/' . $c['archivo']);
                @unlink($this->dir . '/' . $c['archivo'] . '.json');
                $this->app()->table('copias_seguridad')->where('id', $c['id'])->update(['estado' => 'Depurada', 'detalle' => 'Depurada al superar los ' . config('Sigccr')->diasConservacionCopias . ' días de conservación (RNF-13).']);
                (new AuditoriaService($this->app()))->registrar('copias.depurar', 'copias_seguridad', (int) $c['id'], 'Exitoso', null, ['archivo' => $c['archivo']], self::PROCESO_AUTOMATICA);
            }
        }

        return count($ids);
    }

    // ------------------------------------------------------------------ CU-63

    /**
     * Pasos 7 a 12 de la restauración pedida desde el panel (A1, A3, A4). Devuelve el resultado, que también queda
     * en ultima_restauracion.json.
     *
     * @return array<string, mixed>|null null si no hay una restauración pendiente
     */
    public function restaurarPendiente(): ?array
    {
        $pedido = $this->estado->actual();
        if ($pedido === null || ($pedido['estado'] ?? '') !== 'Pendiente') {
            return null;
        }
        $pedido['estado'] = 'EnCurso';
        $pedido['inicio'] = $this->ahora();
        $pedido['etapa'] = 'Verificando la copia seleccionada';
        $this->estado->guardar($pedido);
        $this->bloquear();
        try {
            return $this->restaurar($pedido);
        } finally {
            $this->liberar();
        }
    }

    /** @param array<string, mixed> $pedido */
    private function restaurar(array $pedido): array
    {
        $archivo = (string) $pedido['archivo'];
        $ruta = $this->dir . '/' . $archivo;
        $copia = $this->app()->table('copias_seguridad')->where('archivo', $archivo)->get()->getRowArray();
        // A1: la copia tiene que estar verificada y su archivo, intacto
        if ($copia === null || $copia['estado'] !== 'Verificada' || !is_file($ruta) || hash_file('sha256', $ruta) !== $copia['hash_verificacion']) {
            if ($copia !== null && $copia['estado'] === 'Verificada') {
                $this->app()->table('copias_seguridad')->where('id', $copia['id'])->update(['estado' => 'Corrupta', 'detalle' => 'El archivo no coincide con su código de verificación o no está en el volumen.']);
            }

            return $this->terminar($pedido, false, 'La copia seleccionada está corrupta o incompleta: no se restauró. Seleccione otra copia (A1).', null, []);
        }
        // Operaciones posteriores a la copia, para informar qué debe reconstruirse (paso 12)
        $posteriores = $this->posteriores((string) $copia['fecha_generacion']);
        // Paso 7 (A3): copia del estado actual
        $this->etapa($pedido, 'Generando la copia del estado actual');
        $previaId = $this->registrar('PreviaRestauracion', $pedido['usuario_id'] ?? null, (string) $pedido['responsable'], "Estado previo a restaurar {$archivo}", 'EnCurso');
        $previa = $this->generar($previaId);
        $previaArchivo = $previa['estado'] === 'Verificada' ? (string) $this->app()->table('copias_seguridad')->select('archivo')->where('id', $previaId)->get()->getRow()->archivo : null;
        if ($previaArchivo === null && !($pedido['sin_respaldo'] ?? false)) {
            (new AuditoriaService($this->app()))->registrar('copias.restaurar', 'copias_seguridad', (int) $copia['id'], 'Rechazado', 'No se pudo generar la copia previa: ' . $previa['detalle'], ['archivo' => $archivo], null, self::usuario($pedido), (string) $pedido['responsable']);

            return $this->terminar($pedido, false, 'No se pudo generar la copia del estado actual (' . $previa['detalle'] . '). No se restauró: para continuar sin respaldo previo, vuelva a pedir la restauración marcando esa opción (A3).', null, [], 'RequiereConfirmacion');
        }
        // Paso 8: modo mantenimiento (ya activo desde el pedido), sesiones cerradas y procesos detenidos
        $this->etapa($pedido, 'Cerrando sesiones y deteniendo los procesos');
        $sesiones = self::cerrarSesiones();
        $this->app()->close();
        $this->db = null;
        // Paso 9
        $this->etapa($pedido, 'Restaurando la base de datos');
        $error = null;
        $adjuntos = 0;
        try {
            $adjuntos = $this->restaurarArchivo($ruta);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
        $recuperada = null;
        if ($error !== null) {
            // A4: se intenta volver al estado anterior con la copia previa
            $recuperada = false;
            if ($previaArchivo !== null) {
                try {
                    $this->restaurarArchivo($this->dir . '/' . $previaArchivo, false);
                    $recuperada = true;
                } catch (Throwable $e2) {
                    $error .= ' La recuperación con la copia previa también falló: ' . $e2->getMessage();
                }
            }
            $this->ejecutarSilencioso("IF DATABASEPROPERTYEX(N'{$this->base}', 'Status') = 'OFFLINE' ALTER DATABASE [{$this->base}] SET ONLINE");
        }
        // Pasos 10 y 11: integridad, esquema al día y servicios
        $this->etapa($pedido, 'Verificando la base restaurada');
        try {
            $this->verificarBase();
        } catch (Throwable $e) {
            $error = trim(($error ?? '') . ' Verificación: ' . $e->getMessage());
        }
        $this->reconciliarCatalogo();
        $restaurada = $error === null ? $copia : null;
        $resultado = $error === null
            ? "Base restaurada al {$this->fechaLocal((string) $copia['fecha_generacion'])} desde {$archivo}" . ($adjuntos > 0 ? " con {$adjuntos} adjunto(s)" : '') . '.'
            : 'La restauración falló: ' . $error . ($recuperada === true ? ' Se recuperó el estado anterior con la copia previa.' : '');
        $this->registrarRestauracion($pedido, $copia, $previaArchivo, $error === null, $resultado, $posteriores, $sesiones);

        return $this->terminar($pedido, $error === null, $resultado, $restaurada === null ? null : $archivo, $posteriores, $error === null ? 'Finalizada' : 'Fallida', $previaArchivo);
    }

    /**
     * Descifra, verifica y restaura una copia (pasos 9 y 10) y, con la base ya restaurada, los adjuntos que ella
     * referencia.
     *
     * @return int adjuntos restaurados
     */
    private function restaurarArchivo(string $ruta, bool $conAdjuntos = true): int
    {
        $temporal = $this->dir . '/restauracion_' . bin2hex(random_bytes(4));
        try {
            CifradoCopia::descifrar($ruta, "{$temporal}.zip", $this->clave());
            PaqueteCopia::extraerBase("{$temporal}.zip", "{$temporal}.bak");
            @chmod("{$temporal}.bak", 0644);
            $m = $this->mantenimiento();
            $this->ejecutar($m, "RESTORE VERIFYONLY FROM DISK = N'{$temporal}.bak' WITH CHECKSUM");
            $this->ejecutar($m, "ALTER DATABASE [{$this->base}] SET OFFLINE WITH ROLLBACK IMMEDIATE");
            $this->ejecutar($m, "RESTORE DATABASE [{$this->base}] FROM DISK = N'{$temporal}.bak' WITH REPLACE, CHECKSUM, RECOVERY");
            $this->ejecutarSilencioso("IF DATABASEPROPERTYEX(N'{$this->base}', 'Status') <> 'ONLINE' ALTER DATABASE [{$this->base}] SET ONLINE");
            @unlink("{$temporal}.bak");

            return $conAdjuntos && config('Sigccr')->copiasConAdjuntos ? PaqueteCopia::extraerAdjuntos("{$temporal}.zip", rtrim(WRITEPATH, '/'), $this->rutasLegajo()) : 0;
        } finally {
            @unlink("{$temporal}.zip");
            @unlink("{$temporal}.bak");
        }
    }

    /** @return list<string> rutas de los archivos del legajo registradas en la base (relativas a writable/) */
    private function rutasLegajo(): array
    {
        return array_values(array_filter(array_map('strval', array_column($this->app()->query('SELECT ruta_archivo FROM dbo.documentos_legajo')->getResultArray(), 'ruta_archivo')), [PaqueteCopia::class, 'rutaValida']));
    }

    /** Paso 10: la base está en línea y es íntegra; luego se lleva su esquema a la versión de la aplicación. */
    private function verificarBase(): void
    {
        $estado = $this->mantenimiento()->query("SELECT CAST(DATABASEPROPERTYEX(N'{$this->base}', 'Status') AS VARCHAR(20)) AS e")->getRow()->e ?? '';
        if ($estado !== 'ONLINE') {
            throw new RuntimeException("La base quedó en estado {$estado}.");
        }
        $duenio = Database::connect('migraciones', false);
        $this->ejecutar($duenio, "DBCC CHECKDB (N'{$this->base}') WITH NO_INFOMSGS");
        // Una copia anterior a una migración se actualiza al esquema vigente, y se asegura el usuario de las copias
        $salida = [];
        $rc = 0;
        exec('cd ' . escapeshellarg(ROOTPATH) . ' && SIGCCR_DB_GRUPO=migraciones php spark migrate --all 2>&1', $salida, $rc);
        if ($rc !== 0) {
            throw new RuntimeException('No se pudieron aplicar las migraciones: ' . implode(' ', array_slice($salida, -3)));
        }
        $login = (string) config('Database')->copias['username'];
        if ($login !== '' && preg_match('/^\w+$/', $login) === 1) {
            $duenio->query("IF USER_ID('{$login}') IS NULL CREATE USER [{$login}] FOR LOGIN [{$login}]; IF IS_ROLEMEMBER('db_backupoperator', '{$login}') = 0 ALTER ROLE db_backupoperator ADD MEMBER [{$login}];");
        }
        $duenio->close();
    }

    /**
     * Rehace el catálogo con los manifiestos del volumen: la base restaurada no tiene las copias posteriores a ella y
     * tiene la propia en curso. Las copias cuyo archivo ya no está quedan Depuradas.
     */
    public function reconciliarCatalogo(): void
    {
        $db = $this->app();
        foreach (glob($this->dir . '/*' . PoliticaCopias::EXTENSION . '.json') ?: [] as $f) {
            $m = PoliticaCopias::leerManifiesto((string) file_get_contents($f));
            if ($m === null || !is_file($this->dir . '/' . $m['archivo'])) {
                continue;
            }
            $datos = ['estado' => 'Verificada', 'hash_verificacion' => $m['hash_verificacion'], 'tamano_bytes' => $m['tamano_bytes'] ?? null, 'fecha_fin' => $m['fecha_fin'] ?? null, 'incluye_adjuntos' => !empty($m['incluye_adjuntos']) ? 1 : 0];
            $fila = $db->table('copias_seguridad')->where('archivo', $m['archivo'])->get()->getRowArray();
            if ($fila === null) {
                $db->table('copias_seguridad')->insert($datos + ['archivo' => $m['archivo'], 'fecha_generacion' => $m['fecha_generacion'], 'origen' => $m['origen'], 'descripcion' => $m['descripcion'] ?? null, 'responsable' => $m['responsable'] ?? null]);
            } elseif (in_array($fila['estado'], ['Solicitada', 'EnCurso'], true)) {
                $db->table('copias_seguridad')->where('id', $fila['id'])->update($datos);
            }
        }
        foreach ($db->query("SELECT id, archivo FROM dbo.copias_seguridad WHERE estado IN ('Verificada', 'Solicitada', 'EnCurso')")->getResultArray() as $c) {
            if (!is_file($this->dir . '/' . $c['archivo'])) {
                $db->table('copias_seguridad')->where('id', $c['id'])->update(['estado' => 'Depurada', 'detalle' => 'El archivo no está en el volumen de copias (catálogo rehecho tras una restauración).']);
            }
        }
    }

    /**
     * Paso 12: restauración registrada en la base restaurada (o en la recuperada) y en el registro técnico, fuera de
     * la base (RN-45, RNF-16), y alerta al Administrador con lo que debe reconstruirse.
     *
     * @param array<string, mixed> $pedido
     * @param array<string, mixed> $copia
     * @param list<string> $posteriores
     */
    private function registrarRestauracion(array $pedido, array $copia, ?string $previa, bool $exitosa, string $resultado, array $posteriores, int $sesiones): void
    {
        $tecnico = ['fecha' => $this->ahora(), 'copia' => $copia['archivo'], 'copia_previa' => $previa, 'responsable' => $pedido['responsable'], 'usuario_id' => $pedido['usuario_id'] ?? null, 'exitosa' => $exitosa, 'resultado' => $resultado, 'posteriores' => $posteriores, 'sesiones_cerradas' => $sesiones];
        file_put_contents(rtrim(WRITEPATH, '/') . '/logs/restauraciones.log', json_encode($tecnico, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
        log_message($exitosa ? 'notice' : 'critical', 'Restauración de copia: ' . $resultado);
        try {
            $db = $this->app();
            $id = static fn (?string $a): ?int => $a === null ? null : (($r = $db->table('copias_seguridad')->select('id')->where('archivo', $a)->get()->getRow()) === null ? null : (int) $r->id);
            $usuario = self::usuario($pedido);
            if ($usuario !== null && $db->table('usuarios_internos')->where('id', $usuario)->countAllResults() === 0) {
                $usuario = null;
            }
            $copiaId = $id((string) $copia['archivo']);
            if ($copiaId !== null) {
                $db->table('restauraciones')->insert([
                    'copia_seguridad_id' => $copiaId, 'fecha' => $this->ahora(), 'resultado' => mb_substr($resultado, 0, 300), 'usuario_id' => $usuario, 'responsable' => $pedido['responsable'],
                    'copia_previa_id' => $id($previa), 'inicio' => $pedido['inicio'] ?? null, 'exitosa' => $exitosa ? 1 : 0,
                ]);
            }
            (new AuditoriaService($db))->registrar('copias.restaurar', 'copias_seguridad', $copiaId, $exitosa ? 'Exitoso' : 'Error', $exitosa ? null : mb_substr($resultado, 0, 500), ['copia' => $copia['archivo'], 'copia_previa' => $previa, 'posteriores' => $posteriores], null, $usuario, (string) $pedido['responsable']);
            $texto = $exitosa
                ? 'Se restauró la copia ' . $copia['archivo'] . ($posteriores === [] ? '.' : '. Operaciones posteriores a la copia que deben reconstruirse: ' . implode(', ', $posteriores) . ($previa !== null ? " (quedan en la copia previa {$previa})." : '.'))
                : $resultado;
            (new AlertaService($db))->generar('Seguridad', mb_substr($texto, 0, 1000), 'Revisar el resultado de la restauración en Copias de seguridad.', 'Administrador', null, 'copias_seguridad', $copiaId);
        } catch (Throwable $e) {
            log_message('critical', 'No se pudo registrar la restauración en la base: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ Utilidades

    /** Registra una copia nueva y devuelve su identificador. */
    public function registrar(string $origen, ?int $usuarioId, string $responsable, ?string $descripcion, string $estado = 'Solicitada'): int
    {
        $archivo = PoliticaCopias::nombreArchivo(new \DateTimeImmutable('now'), $origen, bin2hex(random_bytes(3)));
        $this->app()->table('copias_seguridad')->insert([
            'archivo' => $archivo, 'fecha_generacion' => $this->ahora(), 'origen' => $origen, 'estado' => $estado, 'usuario_id' => $usuarioId,
            'responsable' => mb_substr($responsable, 0, 150), 'descripcion' => $descripcion === null ? null : mb_substr($descripcion, 0, 300),
        ]);

        return (int) $this->app()->insertID();
    }

    /** @return list<string> operaciones registradas después de la fecha de la copia */
    private function posteriores(string $desde): array
    {
        $c = static fn (BaseConnection $db, string $sql) => (int) ($db->query($sql, [$desde])->getRow()->t ?? 0);
        $db = $this->app();

        return PoliticaCopias::posteriores([
            'solicitudes' => $c($db, 'SELECT COUNT(*) AS t FROM dbo.solicitudes_credito WHERE fecha_registro > ?'),
            'creditos' => $c($db, 'SELECT COUNT(*) AS t FROM dbo.creditos WHERE fecha_liquidacion > ?'),
            'cobros' => $c($db, 'SELECT COUNT(*) AS t FROM dbo.cobros WHERE fecha_hora > ?'),
            'propuestas' => $c($db, 'SELECT COUNT(*) AS t FROM dbo.propuestas_refinanciacion WHERE fecha_registro > ?'),
            'movimientos' => $c($db, 'SELECT COUNT(*) AS t FROM dbo.movimientos_fondos WHERE fecha > CAST(? AS date)'),
            'clientes' => $c($db, 'SELECT COUNT(*) AS t FROM dbo.clientes WHERE creado_en > ?'),
            'auditoria' => $c($db, 'SELECT COUNT(*) AS t FROM dbo.registros_auditoria WHERE fecha_hora > ?'),
        ]);
    }

    /**
     * @param array<string, mixed> $pedido
     * @param list<string> $posteriores
     * @return array<string, mixed>
     */
    private function terminar(array $pedido, bool $exitosa, string $mensaje, ?string $restaurada, array $posteriores, ?string $estado = null, ?string $previa = null): array
    {
        $r = ['estado' => $estado ?? ($exitosa ? 'Finalizada' : 'Fallida'), 'exitosa' => $exitosa, 'mensaje' => $mensaje, 'archivo' => $pedido['archivo'], 'restaurada' => $restaurada,
            'copia_previa' => $previa, 'posteriores' => $posteriores, 'responsable' => $pedido['responsable'], 'solicitada' => $pedido['solicitada'] ?? null, 'inicio' => $pedido['inicio'] ?? null, 'fin' => $this->ahora()];
        $this->estado->finalizar($r);

        return $r;
    }

    /** @param array<string, mixed> $pedido */
    private function etapa(array &$pedido, string $etapa): void
    {
        $pedido['etapa'] = $etapa;
        $this->estado->guardar($pedido);
    }

    /** Paso 8: borra los archivos de sesión del panel y del portal. @return int sesiones cerradas */
    private static function cerrarSesiones(): int
    {
        $n = 0;
        foreach (glob(rtrim(WRITEPATH, '/') . '/session/' . config('Session')->cookieName . '*') ?: [] as $f) {
            if (is_file($f) && @unlink($f)) {
                $n++;
            }
        }

        return $n;
    }

    private function prepararDirectorio(): void
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0775, true)) {
            throw new ReglaNegocioException("El destino de las copias ({$this->dir}) no está disponible (A2).");
        }
        // SQL Server escribe con su propio usuario (mssql, uid 10001); PHP-FPM lee con www-data para la descarga
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            @chown($this->dir, 10001);
            @chgrp($this->dir, 'www-data');
            @chmod($this->dir, 02775);
        }
        if (!is_writable($this->dir)) {
            throw new ReglaNegocioException("El destino de las copias ({$this->dir}) no admite escritura (A2).");
        }
    }

    /** Tamaño de los archivos de datos de la base, en bytes. */
    private function tamanoBase(): int
    {
        return (int) ($this->mantenimiento()->query('SELECT CAST(SUM(CAST(size AS BIGINT)) * 8192 AS BIGINT) AS t FROM sys.master_files WHERE database_id = DB_ID(?) AND type = 0', [$this->base])->getRow()->t ?? 0);
    }

    public static function tamanoDirectorio(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $t = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
            $t += $f->isFile() ? (int) $f->getSize() : 0;
        }

        return $t;
    }

    private function clave(): string
    {
        return CifradoCopia::clave((string) config('Encryption')->key);
    }

    /**
     * Ejecuta una sentencia larga (BACKUP, RESTORE, DBCC) y espera a que termine: el controlador de SQL Server
     * devuelve el control antes, salvo que se recorran todos sus resultados.
     *
     * @param BaseConnection<mixed, mixed> $db
     */
    private function ejecutar(BaseConnection $db, string $sql): void
    {
        $db->initialize();
        sqlsrv_configure('WarningsReturnAsErrors', 0);
        $stmt = sqlsrv_query($db->connID, $sql, [], ['QueryTimeout' => 0]);
        if ($stmt === false) {
            throw new RuntimeException(self::erroresSqlsrv());
        }
        do {
            $r = sqlsrv_next_result($stmt);
        } while ($r === true);
        sqlsrv_free_stmt($stmt);
        if ($r === false) {
            throw new RuntimeException(self::erroresSqlsrv());
        }
    }

    private function ejecutarSilencioso(string $sql): void
    {
        try {
            $this->ejecutar($this->mantenimiento(), $sql);
        } catch (Throwable $e) {
            log_message('error', 'Copias: ' . $e->getMessage());
        }
    }

    private static function erroresSqlsrv(): string
    {
        $e = sqlsrv_errors(SQLSRV_ERR_ERRORS) ?: [];

        return implode(' ', array_map(static fn ($x) => preg_replace('/^\[[^\]]*\]\[[^\]]*\]\[[^\]]*\]/', '', (string) $x['message']), $e)) ?: 'Error de SQL Server.';
    }

    /** sp_getapplock en la conexión de mantenimiento: una sola copia o restauración a la vez (A1). */
    private function bloquear(): void
    {
        $r = $this->mantenimiento()->query("DECLARE @r INT; EXEC @r = sp_getapplock @Resource = ?, @LockMode = 'Exclusive', @LockOwner = 'Session', @LockTimeout = 0; SELECT @r AS r;", [self::BLOQUEO])->getRow();
        if ((int) ($r->r ?? -1) < 0) {
            throw new ReglaNegocioException('Ya hay una copia de seguridad o una restauración en curso (A1).');
        }
    }

    private function liberar(): void
    {
        try {
            $this->mantenimiento()->query("EXEC sp_releaseapplock @Resource = ?, @LockOwner = 'Session';", [self::BLOQUEO]);
        } catch (Throwable) {
            // la sesión ya no tiene el bloqueo
        }
    }

    /** @return BaseConnection<mixed, mixed> */
    private function mantenimiento(): BaseConnection
    {
        return $this->mant ??= Database::connect('copias', false);
    }

    /** @return BaseConnection<mixed, mixed> */
    private function app(): BaseConnection
    {
        return $this->db ??= Database::connect('default', false);
    }

    /** @param array<string, mixed> $pedido */
    private static function usuario(array $pedido): ?int
    {
        return isset($pedido['usuario_id']) ? (int) $pedido['usuario_id'] : null;
    }

    private function ahora(): string
    {
        return Time::now('UTC')->format('Y-m-d H:i:s.vP');
    }

    private function fechaLocal(string $instante): string
    {
        return Time::parse($instante)->setTimezone(config('Sigccr')->zonaNegocio)->format('d/m/Y H:i');
    }
}
