<?php

declare(strict_types=1);

namespace App\Services\Mantenimiento;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Copias\EstadoRestauracion;
use App\Libraries\Copias\PoliticaCopias;
use App\Services\BaseService;
use App\Services\Comunes\AuditoriaService;
use CodeIgniter\Database\BaseConnection;

/**
 * Operaciones del Administrador sobre las copias de seguridad (CU-61 a CU-63, D-45). El panel solicita la copia o
 * la restauración y valida sus condiciones; el contenedor de procesos (MotorCopias) las ejecuta, porque es el único
 * con acceso de escritura al volumen de copias. La descarga se hace desde el panel, que lo monta de solo lectura.
 */
class CopiaService extends BaseService
{
    private string $dir;
    private EstadoRestauracion $estado;

    /** @param BaseConnection<mixed, mixed>|null $db */
    public function __construct(?BaseConnection $db = null, ?EstadoRestauracion $estado = null)
    {
        parent::__construct($db);
        $this->dir = rtrim(config('Sigccr')->directorioCopias, '/');
        $this->estado = $estado ?? EstadoRestauracion::predeterminado();
    }

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        return $this->db->query('SELECT c.*, (SELECT COUNT(*) FROM dbo.restauraciones r WHERE r.copia_seguridad_id = c.id) AS restauraciones FROM dbo.copias_seguridad c ORDER BY c.fecha_generacion DESC, c.id DESC')->getResultArray();
    }

    /** @return array<string, mixed> */
    public function obtener(int $id): array
    {
        $c = $this->db->table('copias_seguridad')->where('id', $id)->get()->getRowArray();
        if ($c === null) {
            throw new ReglaNegocioException('La copia de seguridad no existe.');
        }
        $c['disponible'] = $c['estado'] === 'Verificada' && is_file($this->dir . '/' . $c['archivo']);
        $c['historial'] = $this->db->query('SELECT r.*, c.archivo AS previa FROM dbo.restauraciones r LEFT JOIN dbo.copias_seguridad c ON c.id = r.copia_previa_id WHERE r.copia_seguridad_id = ? ORDER BY r.fecha DESC', [$id])->getResultArray();

        return $c;
    }

    /**
     * CU-61 paso 2: destino configurado y su espacio libre.
     *
     * @return array{ruta: string, disponible: bool, libre: ?int, en_curso: bool, restauracion: bool, adjuntos: bool}
     */
    public function destino(): array
    {
        $libre = is_dir($this->dir) ? @disk_free_space($this->dir) : false;

        return [
            'ruta' => $this->dir, 'disponible' => $libre !== false, 'libre' => $libre === false ? null : (int) $libre, 'en_curso' => $this->hayEnCurso(),
            'restauracion' => $this->estado->activa(), 'adjuntos' => config('Sigccr')->copiasConAdjuntos,
        ];
    }

    /** CU-61 pasos 3 y 4 (A1, A2): la copia queda Solicitada y la genera el contenedor de procesos. */
    public function solicitar(int $usuarioId, string $responsable, string $descripcion): int
    {
        $descripcion = trim($descripcion);
        if (mb_strlen($descripcion) > 300) {
            throw new ValidacionException(['descripcion' => 'La descripción admite hasta 300 caracteres.']);
        }
        $d = $this->destino();
        if ($d['restauracion']) {
            throw new ReglaNegocioException('Hay una restauración en curso: no se pueden generar copias hasta que finalice.');
        }
        if (!$d['disponible']) {
            throw new ReglaNegocioException("El destino de las copias ({$d['ruta']}) no está disponible: verifique el volumen de copias antes de reintentar (A2).");
        }
        $requerido = PoliticaCopias::espacioRequerido($this->tamanoBase(), $d['adjuntos'] ? MotorCopias::tamanoDirectorio(rtrim(WRITEPATH, '/') . '/uploads') : 0);
        if ($d['libre'] !== null && $d['libre'] < $requerido) {
            throw new ReglaNegocioException('El destino no tiene espacio suficiente: se necesitan ' . PoliticaCopias::tamano($requerido) . ' y hay ' . PoliticaCopias::tamano($d['libre']) . ' libres. Libere espacio o verifique el destino antes de reintentar (A2).');
        }

        return $this->transaccion(function () use ($usuarioId, $responsable, $descripcion): int {
            $this->db->query("SELECT id FROM dbo.copias_seguridad WITH (UPDLOCK, HOLDLOCK) WHERE estado IN ('Solicitada', 'EnCurso')")->getResultArray();
            if ($this->hayEnCurso()) {
                throw new ReglaNegocioException('Ya hay una copia de seguridad en curso: espere a que finalice para iniciar otra (A1).');
            }
            $id = (new MotorCopias($this->db, $this->estado))->registrar('Manual', $usuarioId, $responsable, $descripcion === '' ? null : $descripcion);
            (new AuditoriaService($this->db))->registrar('copias.solicitar', 'copias_seguridad', $id, 'Exitoso', null, ['descripcion' => $descripcion]);

            return $id;
        });
    }

    /**
     * CU-62 pasos 4 a 9 (A1): verifica el archivo contra su código y registra la descarga.
     *
     * @return array{ruta: string, nombre: string, hash: string}
     */
    public function paraDescargar(int $id): array
    {
        $c = $this->obtener($id);
        $ruta = $this->dir . '/' . $c['archivo'];
        if ($c['estado'] !== 'Verificada' || !is_file($ruta)) {
            throw new ReglaNegocioException('La copia no está disponible para descargar' . ($c['estado'] === 'Verificada' ? ': su archivo ya no está en el volumen' : ' (' . (PoliticaCopias::ESTADOS[$c['estado']] ?? $c['estado']) . ')') . ' (A1).');
        }
        if (hash_file('sha256', $ruta) !== $c['hash_verificacion']) {
            $this->db->table('copias_seguridad')->where('id', $id)->update(['estado' => 'Corrupta', 'detalle' => 'El archivo no coincide con su código de verificación (CU-62).']);
            (new AuditoriaService($this->db))->registrar('copias.descargar', 'copias_seguridad', $id, 'Rechazado', 'Copia corrupta');
            throw new ReglaNegocioException('La copia está corrupta: su archivo no coincide con el código de verificación. Se marcó como corrupta y no se descarga (A1).');
        }
        (new AuditoriaService($this->db))->registrar('copias.descargar', 'copias_seguridad', $id, 'Exitoso', null, ['archivo' => $c['archivo'], 'hash' => $c['hash_verificacion']]);

        return ['ruta' => $ruta, 'nombre' => (string) $c['archivo'], 'hash' => (string) $c['hash_verificacion']];
    }

    /**
     * CU-63 pasos 3 a 6 (A1, A2): valida la copia y la confirmación y deja pedida la restauración, que ejecuta el
     * contenedor de procesos. Desde ese momento el sistema está en modo mantenimiento.
     */
    public function solicitarRestauracion(int $id, string $confirmacion, bool $sinRespaldo, int $usuarioId, string $responsable): void
    {
        $c = $this->obtener($id);
        if (!$c['disponible']) {
            throw new ReglaNegocioException('La copia está corrupta, incompleta o no disponible: seleccione otra copia (A1).');
        }
        if (!PoliticaCopias::confirmacionValida($confirmacion)) {
            throw new ValidacionException(['confirmacion' => 'Para confirmar, escriba ' . PoliticaCopias::CONFIRMACION . ' (A2).']);
        }
        if ($this->estado->activa()) {
            throw new ReglaNegocioException('Ya hay una restauración en curso.');
        }
        if ($this->hayEnCurso()) {
            throw new ReglaNegocioException('Hay una copia de seguridad en curso: espere a que finalice para restaurar.');
        }
        // Precondición: no hay operaciones críticas en curso (procesos programados ejecutándose)
        $procesos = $this->db->query("SELECT proceso FROM dbo.ejecuciones_proceso WHERE estado = 'EnCurso' AND inicio > DATEADD(hour, -2, SYSDATETIMEOFFSET())")->getResultArray();
        if ($procesos !== []) {
            throw new ReglaNegocioException('Hay procesos programados en ejecución (' . implode(', ', array_unique(array_column($procesos, 'proceso'))) . '): espere a que terminen para restaurar.');
        }
        (new AuditoriaService($this->db))->registrar('copias.solicitar-restauracion', 'copias_seguridad', $id, 'Exitoso', null, ['archivo' => $c['archivo'], 'sin_respaldo' => $sinRespaldo]);
        $this->estado->guardar([
            'estado' => 'Pendiente', 'copia_id' => $id, 'archivo' => $c['archivo'], 'fecha_copia' => $c['fecha_generacion'], 'sin_respaldo' => $sinRespaldo,
            'usuario_id' => $usuarioId, 'responsable' => $responsable, 'solicitada' => $this->ahoraUtc(), 'etapa' => 'Esperando al contenedor de procesos',
        ]);
    }

    /** @return array<string, mixed>|null */
    public function restauracionActual(): ?array
    {
        return $this->estado->actual();
    }

    /** @return array<string, mixed>|null */
    public function ultimaRestauracion(): ?array
    {
        return $this->estado->ultima();
    }

    private function hayEnCurso(): bool
    {
        return $this->db->table('copias_seguridad')->whereIn('estado', ['Solicitada', 'EnCurso'])->countAllResults() > 0;
    }

    private function tamanoBase(): int
    {
        return (int) ($this->db->query('SELECT CAST(SUM(CAST(size AS BIGINT)) * 8192 AS BIGINT) AS t FROM sys.database_files WHERE type = 0')->getRow()->t ?? 0);
    }
}
