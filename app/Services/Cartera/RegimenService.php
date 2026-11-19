<?php

declare(strict_types=1);

namespace App\Services\Cartera;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Cartera\ReglasRegimen;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Validacion\Campos;
use App\Services\BaseService;
use App\Services\Comunes\AlertaService;
use App\Services\Comunes\AuditoriaService;

/**
 * CU-105 Generar Régimen Informativo de Deudores (RN-84, D-44). El Temporizador genera el mes cerrado a partir del
 * primer día hábil del mes siguiente (pasos 1 a 5); el Gerente consulta, descarga y rectifica (paso 6, A1).
 *
 * Los días de atraso y los saldos se toman del estado de las cuotas al momento de la generación, con el cierre del mes
 * como fecha de referencia del atraso: las cuotas que vencieron hasta el cierre y siguen impagas determinan la
 * situación. Un pago registrado entre el cierre y la generación reduce el atraso informado (aproximación de D-44).
 */
class RegimenService extends BaseService
{
    public const PROCESO = 'regimen:generar';

    /** Período que el Temporizador debe generar hoy, o null si no corresponde o ya existe (A1). */
    public function periodoPendiente(): ?string
    {
        $periodo = ReglasRegimen::periodoACerrar($this->hoyNegocio(), $this->diasNoHabiles());
        if ($periodo === null || $this->db->table('regimenes_informativos')->where('periodo', $periodo)->countAllResults() > 0) {
            return null;
        }

        return $periodo;
    }

    /**
     * Pasos 2 a 5. Sin usuario lo genera el Temporizador; con usuario lo pide el Gerente para un período cerrado. Si
     * el período ya tiene archivo, el Gerente lo rectifica con un motivo: se agrega una versión y la anterior queda
     * Rectificado (A1).
     *
     * @return array{id: int, version: int, deudores: int}
     */
    public function generar(string $periodo, ?int $usuarioId = null, ?string $motivo = null, ?string $proceso = null): array
    {
        $hoy = $this->hoyNegocio();
        if (!ReglasRegimen::periodoCerrado($periodo, $hoy)) {
            throw new ValidacionException(['periodo' => 'Seleccione un mes ya cerrado.']);
        }
        $motivo = Campos::texto((string) $motivo);

        $r = $this->transaccion(function () use ($periodo, $usuarioId, $motivo, $proceso): array {
            // Las generaciones simultáneas del mismo período las resuelve UQ_regimenes_periodo_version
            $vigentes = array_values(array_filter($this->bloquear('regimenes_informativos', [$periodo], 'periodo'), static fn ($x) => $x['estado'] === 'Vigente'));
            $anterior = $vigentes[0] ?? null;
            if ($anterior !== null) {
                if ($usuarioId === null) {
                    return ['omitido' => true];
                }
                if ($motivo === '') {
                    throw new ValidacionException(['motivo' => 'Indique el motivo de la rectificación.']);
                }
                // H-30: el motivo se guarda en NVARCHAR(500); uno más largo respondía error 500
                $error = Campos::motivo($motivo, 500);
                if ($error !== null) {
                    throw new ValidacionException(['motivo' => $error]);
                }
                $this->db->table('regimenes_informativos')->where('id', $anterior['id'])->update(['estado' => 'Rectificado']);
            }
            $version = $anterior === null ? 1 : (int) $this->db->query('SELECT MAX(version) AS v FROM dbo.regimenes_informativos WHERE periodo = ?', [$periodo])->getRow()->v + 1;
            $cierre = ReglasRegimen::cierre($periodo);
            $deudores = ReglasRegimen::deudores($this->cuotasVigentes($cierre), $cierre);
            $archivo = ReglasRegimen::archivo($periodo, $version, $cierre, $deudores);
            $saldo = Decimal::sum(array_map(static fn ($d) => Decimal::of($d['saldo']), $deudores))->toFixed(2);
            $this->db->query(
                'INSERT INTO dbo.regimenes_informativos (periodo, version, fecha_cierre, fecha_generacion, cantidad_deudores, saldo_total, archivo, hash_archivo, estado, usuario_id, proceso, motivo) '
                . "VALUES (?, ?, ?, ?, ?, ?, CONVERT(VARBINARY(MAX), CAST(? AS VARCHAR(MAX)), 1), ?, 'Vigente', ?, ?, ?)",
                [$periodo, $version, $cierre, $this->ahoraUtc(), count($deudores), $saldo, '0x' . bin2hex($archivo), hash('sha256', $archivo), $usuarioId, $usuarioId === null ? ($proceso ?? self::PROCESO) : null, $anterior === null ? null : $motivo],
            );
            $id = (int) $this->db->insertID();
            foreach ($deudores as $d) {
                $this->db->table('situaciones_deudor')->insert(['regimen_informativo_id' => $id] + $d);
            }
            $detalle = ['periodo' => $periodo, 'version' => $version, 'deudores' => count($deudores), 'hash' => hash('sha256', $archivo)];
            (new AuditoriaService($this->db))->registrar($anterior === null ? 'regimen.generar' : 'regimen.rectificar', 'regimenes_informativos', $id, 'Exitoso', $anterior === null ? null : $motivo, $detalle, $usuarioId === null ? ($proceso ?? self::PROCESO) : null);
            $texto = $deudores === []
                ? "Régimen informativo de deudores {$periodo}: no hay financiaciones vigentes; se registró el período sin deudores (A2)."
                : "Régimen informativo de deudores {$periodo}" . ($version > 1 ? " (rectificación, versión {$version})" : '') . ': el archivo está disponible con ' . count($deudores) . ' deudores.';
            (new AlertaService($this->db))->generar('Proceso', $texto, 'Descargar el archivo y presentarlo por el canal del BCRA.', 'Gerente', null, 'regimenes_informativos', $id);

            return ['id' => $id, 'version' => $version, 'deudores' => count($deudores)];
        });
        if (isset($r['omitido'])) {
            throw new ReglaNegocioException("El período {$periodo} ya fue generado; el Gerente puede rectificarlo (A1).");
        }

        return $r;
    }

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        return $this->db->query(
            "SELECT r.id, r.periodo, r.version, r.fecha_cierre, r.fecha_generacion, r.cantidad_deudores, r.saldo_total, r.hash_archivo, r.estado, r.proceso, r.motivo, u.apellido + ', ' + u.nombre AS usuario "
            . 'FROM dbo.regimenes_informativos r LEFT JOIN dbo.usuarios_internos u ON u.id = r.usuario_id ORDER BY r.periodo DESC, r.version DESC',
        )->getResultArray();
    }

    /** @return array<string, mixed> */
    public function obtener(int $id): array
    {
        $r = $this->db->query(
            "SELECT r.id, r.periodo, r.version, r.fecha_cierre, r.fecha_generacion, r.cantidad_deudores, r.saldo_total, r.hash_archivo, r.estado, r.proceso, r.motivo, u.apellido + ', ' + u.nombre AS usuario "
            . 'FROM dbo.regimenes_informativos r LEFT JOIN dbo.usuarios_internos u ON u.id = r.usuario_id WHERE r.id = ?',
            [$id],
        )->getRowArray();
        if ($r === null) {
            throw new ReglaNegocioException('El régimen informativo no existe.');
        }
        $r['deudores'] = $this->db->query('SELECT cliente_id, cuit_cuil, denominacion, dias_atraso, situacion, saldo, creditos FROM dbo.situaciones_deudor WHERE regimen_informativo_id = ? ORDER BY situacion DESC, denominacion', [$id])->getResultArray();
        $r['por_situacion'] = ReglasRegimen::porSituacion($r['deudores']);
        $r['versiones'] = $this->db->query('SELECT id, version, estado, fecha_generacion FROM dbo.regimenes_informativos WHERE periodo = ? ORDER BY version', [$r['periodo']])->getResultArray();

        return $r;
    }

    /** Paso 6: contenido del archivo para descargar; la descarga queda auditada. @return array{nombre: string, contenido: string} */
    public function archivo(int $id, int $usuarioId): array
    {
        $r = $this->db->query('SELECT periodo, version, CONVERT(VARCHAR(MAX), archivo, 1) AS archivo_hex FROM dbo.regimenes_informativos WHERE id = ?', [$id])->getRowArray();
        if ($r === null) {
            throw new ReglaNegocioException('El régimen informativo no existe.');
        }
        (new AuditoriaService($this->db))->registrar('regimen.descargar', 'regimenes_informativos', $id, 'Exitoso', null, ['periodo' => $r['periodo'], 'version' => (int) $r['version']], null, $usuarioId);

        return ['nombre' => 'regimen_deudores_' . str_replace('-', '', (string) $r['periodo']) . '_v' . (int) $r['version'] . '.txt', 'contenido' => (string) hex2bin(substr((string) $r['archivo_hex'], 2))];
    }

    /**
     * Cuotas impagas de los créditos vigentes liquidados hasta el cierre.
     *
     * @return list<array<string, mixed>>
     */
    private function cuotasVigentes(string $cierre): array
    {
        return $this->db->query(
            'SELECT cl.id AS cliente_id, cl.cuit_cuil, cl.denominacion, c.id AS credito_id, q.vencimiento, '
            . 'q.capital_pendiente + q.interes_pendiente + q.gastos_pendiente + q.iva_pendiente + q.no_capitalizable_pendiente + q.punitorio_pendiente + q.iva_punitorio_pendiente AS pendiente '
            . 'FROM dbo.cuotas q JOIN dbo.creditos c ON c.id = q.credito_id JOIN dbo.clientes cl ON cl.id = c.cliente_id '
            . "WHERE c.estado IN ('Activo', 'EnMora') AND q.estado IN ('Pendiente', 'Vencida') AND CAST(c.fecha_liquidacion AT TIME ZONE 'Argentina Standard Time' AS date) <= CAST(? AS date) "
            . 'ORDER BY cl.id, q.vencimiento',
            [$cierre],
        )->getResultArray();
    }

    /** @return list<string> */
    private function diasNoHabiles(): array
    {
        $hoy = $this->hoyNegocio();

        return array_map(static fn ($f) => substr((string) $f['fecha'], 0, 10), $this->db->query(
            'SELECT fecha FROM dbo.dias_no_habiles WHERE fecha BETWEEN CAST(? AS date) AND EOMONTH(CAST(? AS date))',
            [substr($hoy, 0, 7) . '-01', $hoy],
        )->getResultArray());
    }
}
