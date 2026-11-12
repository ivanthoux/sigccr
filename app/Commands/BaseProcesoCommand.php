<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Copias\EstadoRestauracion;
use App\Services\Comunes\AlertaService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Config\Database;
use Throwable;

/**
 * Base de los procesos programados (RNF-10, 4.4.4):
 *  a) exclusión mutua con sp_getapplock: si otra ejecución del mismo proceso está en curso, termina sin hacer nada;
 *  b) idempotencia: cada proceso define cómo evitar efectos duplicados (claves únicas, estados);
 *  c) registro de inicio, fin, elementos procesados y errores en ejecuciones_proceso;
 *  d) una transacción por elemento, de modo que un error no revierte los demás;
 *  e) recuperación de ejecuciones omitidas: fechasPendientes() devuelve las fechas de negocio no procesadas;
 *  f) zona horaria de negocio para las fechas.
 */
abstract class BaseProcesoCommand extends BaseCommand
{
    protected $group = 'SIG-CCR';

    /** @var BaseConnection<mixed, mixed> */
    protected BaseConnection $db;
    private int $procesados = 0;
    private int $errores = 0;
    /** @var list<string> */
    private array $detalleErrores = [];

    /** Cuerpo del proceso. Usar procesarElemento() para cada elemento. */
    abstract protected function ejecutar(?string $fechaReferencia): void;

    /** Si el proceso trabaja por fecha de negocio, devuelve las fechas pendientes (por defecto, ninguna en particular). */
    protected function porFecha(): bool
    {
        return false;
    }

    public function run(array $params): void
    {
        // CU-63 paso 8: durante una restauración los procesos programados no se ejecutan (D-45)
        if (EstadoRestauracion::predeterminado()->activa()) {
            CLI::write("{$this->name}: hay una restauración de copia en curso; se omite.", 'yellow');

            return;
        }
        $this->db = Database::connect();
        $bloqueo = $this->db->query(
            "DECLARE @r INT; EXEC @r = sp_getapplock @Resource = ?, @LockMode = 'Exclusive', @LockOwner = 'Session', @LockTimeout = 0; SELECT @r AS r;",
            ['sigccr:' . $this->name],
        )->getRowArray();
        if ((int) ($bloqueo['r'] ?? -1) < 0) {
            CLI::write("{$this->name}: otra ejecución está en curso; se omite.", 'yellow');

            return;
        }
        try {
            $fechas = $this->porFecha() ? $this->fechasPendientes() : [null];
            foreach ($fechas as $fecha) {
                $this->ejecutarRegistrado($fecha);
            }
        } finally {
            $this->db->query("EXEC sp_releaseapplock @Resource = ?, @LockOwner = 'Session';", ['sigccr:' . $this->name]);
        }
    }

    private function ejecutarRegistrado(?string $fecha): void
    {
        $this->procesados = $this->errores = 0;
        $this->detalleErrores = [];
        $this->db->table('ejecuciones_proceso')->insert([
            'proceso' => $this->name, 'fecha_referencia' => $fecha, 'inicio' => $this->ahoraUtc(), 'estado' => 'EnCurso',
        ]);
        $id = (int) $this->db->insertID();
        $estado = 'Finalizada';
        try {
            $this->ejecutar($fecha);
            if ($this->errores > 0) {
                $estado = 'ConErrores';
            }
        } catch (Throwable $e) {
            $estado = 'Fallida';
            $this->detalleErrores[] = $e->getMessage();
            log_message('critical', "{$this->name}: {$e->getMessage()}");
        }
        $this->db->table('ejecuciones_proceso')->where('id', $id)->update([
            'fin' => $this->ahoraUtc(),
            'elementos_procesados' => $this->procesados,
            'errores' => $this->errores,
            'estado' => $estado,
            'detalle' => $this->detalleErrores === [] ? null : mb_substr(implode(' | ', $this->detalleErrores), 0, 2000),
        ]);
        if ($estado !== 'Finalizada') {
            // RNF-16: los errores críticos de los procesos generan una alerta al Administrador
            (new AlertaService($this->db))->generar('Proceso', "{$this->name} finalizó en estado {$estado}.", 'Revisar ejecuciones_proceso y el registro técnico.', 'Administrador', null, 'ejecuciones_proceso', $id);
        }
        CLI::write(sprintf('%s %s: %s, %d procesados, %d errores', $this->name, $fecha ?? '', $estado, $this->procesados, $this->errores));
    }

    /**
     * Procesa un elemento en su propia transacción (RNF-10 d).
     *
     * @param callable(): void $operacion
     */
    protected function procesarElemento(string $descripcion, callable $operacion): void
    {
        $this->db->transException(true)->transBegin();
        try {
            $operacion();
            $this->db->transCommit();
            $this->procesados++;
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->errores++;
            $this->detalleErrores[] = "{$descripcion}: {$e->getMessage()}";
            log_message('error', "{$this->name} {$descripcion}: {$e->getMessage()}");
        }
    }

    /**
     * Procesa un elemento que maneja sus propias transacciones, por ejemplo porque espera a un servicio externo entre
     * ellas y no debe retener bloqueos (CU-86). Cuenta el elemento y registra el error sin interrumpir los demás.
     *
     * @param callable(): void $operacion
     */
    protected function procesarConTransaccionesPropias(string $descripcion, callable $operacion): void
    {
        try {
            $operacion();
            $this->procesados++;
        } catch (Throwable $e) {
            $this->errores++;
            $this->detalleErrores[] = "{$descripcion}: {$e->getMessage()}";
            log_message('error', "{$this->name} {$descripcion}: {$e->getMessage()}");
        }
    }

    /**
     * Fechas de negocio sin una ejecución finalizada, desde la última procesada hasta ayer u hoy (RNF-10 e).
     *
     * @return list<string>
     */
    protected function fechasPendientes(bool $incluirHoy = true): array
    {
        $zona = config('Sigccr')->zonaNegocio;
        $hoy = Time::now($zona)->setTime(0, 0);
        $ultima = $this->db->table('ejecuciones_proceso')->selectMax('fecha_referencia', 'f')
            ->where('proceso', $this->name)->whereIn('estado', ['Finalizada', 'ConErrores'])->get()->getRowArray()['f'] ?? null;
        $desde = $ultima === null ? $hoy : Time::parse((string) $ultima, $zona)->addDays(1);
        $fechas = [];
        for ($d = $desde; $d <= $hoy; $d = $d->addDays(1)) {
            if ($d->equals($hoy) && !$incluirHoy) {
                break;
            }
            $fechas[] = $d->format('Y-m-d');
        }

        return $fechas;
    }

    /** Errores registrados en la ejecución en curso. */
    protected function erroresRegistrados(): int
    {
        return $this->errores;
    }

    protected function ahoraUtc(): string
    {
        return Time::now('UTC')->format('Y-m-d H:i:s.vP');
    }
}
