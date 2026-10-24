<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Finanzas\AmortizacionFrancesa;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\Tasas;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use DateTimeImmutable;
use Throwable;

/**
 * Verificación de la arquitectura ejecutable (actividad 6, plan preventivo de R8):
 * extensiones de PHP, conexión con SQL Server, edición e intercalación, versiones de fila, transacciones,
 * ida y vuelta de DECIMAL y datetimeoffset, y el caso de referencia del motor financiero.
 *
 *   php spark sigccr:verificar-entorno            informe completo
 *   php spark sigccr:verificar-entorno --silencioso  solo fallas (para cron)
 */
class VerificarEntorno extends BaseCommand
{
    protected $group = 'SIG-CCR';
    protected $name = 'sigccr:verificar-entorno';
    protected $description = 'Verifica PHP, SQL Server y el motor financiero (R8).';
    protected $options = ['--silencioso' => 'Muestra solo las verificaciones fallidas.'];

    private int $fallas = 0;
    private bool $silencioso = false;

    public function run(array $params): void
    {
        $this->silencioso = array_key_exists('silencioso', $params) || CLI::getOption('silencioso') !== null;

        foreach (['bcmath', 'intl', 'mbstring', 'sqlsrv', 'sodium'] as $ext) {
            $this->verificar("Extensión {$ext}", static fn () => extension_loaded($ext) ? 'cargada' : throw new \RuntimeException('no cargada'));
        }
        $this->verificar('Zona horaria de PHP', static fn () => date_default_timezone_get() === 'UTC' ? 'UTC' : throw new \RuntimeException(date_default_timezone_get() . ' (se esperaba UTC)'));

        $this->verificar('Motor financiero: caso RN-68', static function () {
            $plan = AmortizacionFrancesa::plan(Decimal::of('100000'), Decimal::of('0.60'), 6, new DateTimeImmutable('2026-01-10'), Decimal::of('500'), Decimal::of('0.21'));
            $cftea = Tasas::cftea(Decimal::of('97580'), $plan->totales())->toPercent();
            $ok = $plan->cuotaOrdinaria->toFixed(2) === '19701.75' && $plan->cuotas[0]->total()->toFixed(2) === '21356.75' && $cftea === '146.33';

            return $ok ? 'cuota 19701.75, primera cuota 21356.75, CFTEA 146.33 %' : throw new \RuntimeException("resultado inesperado (CFTEA {$cftea})");
        });

        try {
            $db = Database::connect();
            $db->initialize();
        } catch (Throwable $e) {
            $this->fallo('Conexión con SQL Server', $e->getMessage());
            $this->resumen();

            return;
        }
        $this->verificar('Conexión con SQL Server', static function () use ($db) {
            $r = $db->query("SELECT CAST(SERVERPROPERTY('ProductVersion') AS VARCHAR(30)) AS v, CAST(SERVERPROPERTY('Edition') AS VARCHAR(60)) AS e")->getRowArray();

            return "{$r['v']} ({$r['e']})";
        });
        $this->verificar('Intercalación y versiones de fila', static function () use ($db) {
            $r = $db->query('SELECT collation_name AS c, is_read_committed_snapshot_on AS rcsi FROM sys.databases WHERE name = DB_NAME()')->getRowArray();
            if ((int) $r['rcsi'] !== 1) {
                throw new \RuntimeException('READ_COMMITTED_SNAPSHOT desactivado (ver docker/db/crear_base.sql)');
            }

            return "{$r['c']}, READ_COMMITTED_SNAPSHOT activo";
        });
        $this->verificar('DECIMAL y datetimeoffset (ida y vuelta)', static function () use ($db) {
            $r = $db->query("SELECT CAST(? AS DECIMAL(19,2)) AS imp, CAST(? AS DECIMAL(18,12)) AS tasa, CAST(? AS DATETIMEOFFSET(3)) AS ts",
                ['19701.75', '0.000821917808', '2026-10-25 15:30:00.123+00:00'])->getRowArray();
            // El controlador sqlsrv devuelve los decimales como texto y puede omitir el cero inicial (".0008...").
            $impOk = Decimal::of((string) $r['imp'])->equals('19701.75');
            $tasaOk = Decimal::of((string) $r['tasa'])->equals('0.000821917808');
            $tsOk = str_contains((string) $r['ts'], '2026-10-25 15:30:00.123');
            if (!$impOk || !$tasaOk || !$tsOk) {
                throw new \RuntimeException('valores devueltos: ' . json_encode($r));
            }

            return "imp={$r['imp']} tasa={$r['tasa']} ts={$r['ts']}";
        });
        $this->verificar('Transacción con reversión', static function () use ($db) {
            $db->query('CREATE TABLE #prueba_tx (v INT)');
            $db->transBegin();
            $db->query('INSERT INTO #prueba_tx (v) VALUES (1)');
            $db->transRollback();
            $n = (int) $db->query('SELECT COUNT(*) AS n FROM #prueba_tx')->getRowArray()['n'];
            $db->query('DROP TABLE #prueba_tx');

            return $n === 0 ? 'la reversión no deja filas' : throw new \RuntimeException('la reversión dejó filas');
        });
        $this->verificar('Esquema migrado', static function () use ($db) {
            $n = (int) $db->query("SELECT COUNT(*) AS n FROM sys.tables WHERE schema_id = SCHEMA_ID('dbo') AND name <> 'migrations'")->getRowArray()['n'];

            return $n >= 70 ? "{$n} tablas" : throw new \RuntimeException("{$n} tablas: ejecutar php spark migrate --all -g migraciones");
        });

        $this->resumen();
    }

    /** @param callable(): string $prueba */
    private function verificar(string $nombre, callable $prueba): void
    {
        try {
            $detalle = $prueba();
            if (!$this->silencioso) {
                CLI::write(CLI::color('[OK]   ', 'green') . "{$nombre}: {$detalle}");
            }
        } catch (Throwable $e) {
            $this->fallo($nombre, $e->getMessage());
        }
    }

    private function fallo(string $nombre, string $mensaje): void
    {
        $this->fallas++;
        CLI::write(CLI::color('[FALLA] ', 'red') . "{$nombre}: {$mensaje}");
    }

    private function resumen(): void
    {
        if (!$this->silencioso || $this->fallas > 0) {
            CLI::write($this->fallas === 0 ? 'Arquitectura ejecutable verificada.' : "{$this->fallas} verificaciones fallidas.", $this->fallas === 0 ? 'green' : 'red');
        }
        if ($this->fallas > 0) {
            exit(1);
        }
    }
}
