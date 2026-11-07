<?php

declare(strict_types=1);

namespace App\Services\Cartera;

use App\Libraries\Finanzas\Decimal;
use App\Libraries\Finanzas\Punitorios;
use App\Services\BaseService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ParametroService;
use CodeIgniter\I18n\Time;

/**
 * CU-88 Calcular Intereses Punitorios (SS-06). RN-34, RN-35, RN-53, RN-65, D-33.
 *
 * Registra un tramo por cuota y día de atraso con la tasa vigente ese día, limitada por el tope sobre la TNA del
 * crédito, sobre el capital vencido pendiente. Los tramos son condicionales mientras el atraso no supera la
 * tolerancia asignada al crédito; al superarla, todos pasan a exigibles y se suman a los punitorios pendientes de la
 * cuota. La clave única (cuota, fecha) hace idempotente el cálculo, que se ejecuta de noche y también al cobrar, para
 * incluir el día del pago (RN-53).
 */
class PunitorioService extends BaseService
{
    public const PROCESO = 'cartera:punitorios';

    /** @var array<string, array{id: int, numero: int, valores: array<string, mixed>}> versión de morosidad por día */
    private array $versiones = [];
    /** @var array<string, string> alícuota de IVA por día */
    private array $alicuotas = [];

    /**
     * CU-88 paso 3: cuotas cuyo vencimiento pasó y conservan importe pendiente pasan a Vencida; sus créditos, a En
     * mora (4.2.6, 4.2.7). Devuelve la cantidad de cuotas marcadas.
     */
    public function marcarVencidas(string $fecha): int
    {
        $this->db->query("UPDATE dbo.cuotas SET estado = 'Vencida', version_fila = version_fila + 1 WHERE estado = 'Pendiente' AND vencimiento <= CAST(? AS date)", [$fecha]);
        $n = $this->db->affectedRows();
        $this->db->query(
            "UPDATE c SET estado = 'EnMora', version_fila = c.version_fila + 1 FROM dbo.creditos c WHERE c.estado = 'Activo' "
            . "AND EXISTS (SELECT 1 FROM dbo.cuotas q WHERE q.credito_id = c.id AND q.estado = 'Vencida')",
        );

        return $n;
    }

    /**
     * Cuotas vencidas con capital pendiente al cierre de una fecha (CU-88 paso 3).
     *
     * @return list<int>
     */
    public function cuotasEnAtraso(string $fecha): array
    {
        return array_map('intval', array_column($this->db->query(
            "SELECT q.id FROM dbo.cuotas q JOIN dbo.creditos c ON c.id = q.credito_id WHERE q.estado IN ('Pendiente', 'Vencida') "
            . "AND q.vencimiento < CAST(? AS date) AND q.capital_pendiente > 0 AND c.estado IN ('Activo', 'EnMora') ORDER BY q.id",
            [$fecha],
        )->getResultArray(), 'id'));
    }

    /**
     * CU-88 pasos 4 a 6 (A1, A4) para una cuota: registra los tramos de los días sin calcular hasta la fecha y, si el
     * atraso supera la tolerancia, los vuelve exigibles. Debe ejecutarse dentro de una transacción; bloquea la cuota.
     *
     * @return array{tramos: int, importe: string, exigible: bool}
     */
    public function devengar(int $cuotaId, string $hasta): array
    {
        $q = $this->db->query(
            'SELECT q.*, c.tna, c.tolerancia_dias, c.estado AS estado_credito FROM dbo.cuotas q WITH (UPDLOCK, ROWLOCK) JOIN dbo.creditos c ON c.id = q.credito_id WHERE q.id = ?',
            [$cuotaId],
        )->getRowArray();
        $vacio = ['tramos' => 0, 'importe' => '0.0000', 'exigible' => false];
        if ($q === null || !in_array($q['estado'], ['Pendiente', 'Vencida'], true) || !in_array($q['estado_credito'], ['Activo', 'EnMora'], true)) {
            return $vacio;
        }
        $vencimiento = substr((string) $q['vencimiento'], 0, 10);
        $dias = Punitorios::diasAtraso(new \DateTimeImmutable($vencimiento), new \DateTimeImmutable($hasta));
        if ($dias === 0) {
            return $vacio;
        }
        $ultimo = $this->db->table('tramos_punitorios')->selectMax('fecha', 'f')->where('cuota_id', $cuotaId)->get()->getRow()->f ?? null;
        $base = Decimal::of((string) $q['capital_pendiente']);
        $tna = Decimal::of((string) $q['tna']);
        $exigible = Punitorios::esExigible($dias, (int) $q['tolerancia_dias']);
        $nuevos = 0;
        $suma = Decimal::zero();
        if ($base->isPositive()) {
            foreach (Punitorios::diasSinTramo($vencimiento, $ultimo === null ? null : (string) $ultimo, $hasta) as $dia) {
                $v = $this->version($dia);
                $tasa = Punitorios::tasaDiaria(Decimal::of((string) $v['valores']['tasa_punitoria_diaria']), Decimal::of((string) $v['valores']['tope_sobre_tna']), $tna);
                $t = Punitorios::tramo($base, $tasa, Decimal::of($this->alicuota($dia)));
                $this->db->table('tramos_punitorios')->insert([
                    'cuota_id' => $cuotaId, 'fecha' => $dia, 'tasa_diaria' => $tasa->toFixed(12), 'capital_base' => $base->toFixed(4),
                    'importe' => $t['importe']->toFixed(4), 'iva' => $t['iva']->toFixed(4), 'exigible' => 0, 'version_parametros_id' => $v['id'],
                ]);
                $nuevos++;
                $suma = $suma->add($t['importe']);
            }
        }
        if ($exigible) {
            // RN-53: superada la tolerancia, son exigibles todos los tramos desde el primer día de atraso
            $c = $this->db->query('SELECT ISNULL(SUM(importe), 0) AS importe, ISNULL(SUM(iva), 0) AS iva FROM dbo.tramos_punitorios WHERE cuota_id = ? AND exigible = 0', [$cuotaId])->getRowArray();
            if (Decimal::of((string) $c['importe'])->isPositive() || Decimal::of((string) $c['iva'])->isPositive()) {
                $this->db->query('UPDATE dbo.tramos_punitorios SET exigible = 1 WHERE cuota_id = ? AND exigible = 0', [$cuotaId]);
                $this->db->table('cuotas')->where('id', $cuotaId)->update([
                    'punitorio_pendiente' => Decimal::of((string) $q['punitorio_pendiente'])->add((string) $c['importe'])->toFixed(4),
                    'iva_punitorio_pendiente' => Decimal::of((string) $q['iva_punitorio_pendiente'])->add((string) $c['iva'])->toFixed(4),
                    'version_fila' => (int) $q['version_fila'] + 1,
                ]);
            }
        }

        return ['tramos' => $nuevos, 'importe' => $suma->toFixed(4), 'exigible' => $exigible];
    }

    /**
     * Punitorios al día de las cuotas impagas de un cliente o de cuotas dadas, para cobrar (CU-33) o consultar la
     * deuda. Bloquea las cuotas: debe ejecutarse dentro de una transacción.
     *
     * @param list<int> $cuotaIds
     */
    public function devengarCuotas(array $cuotaIds, string $hasta): void
    {
        foreach ($cuotaIds as $id) {
            $this->devengar($id, $hasta);
        }
    }

    /** CU-88 paso 9: resumen de la ejecución en auditoría (RN-45). */
    public function auditar(string $fecha, int $cuotas, int $vencidas, int $errores): void
    {
        (new AuditoriaService($this->db))->registrar('cartera.punitorios', 'ejecuciones_proceso', null, 'Exitoso', $errores === 0 ? null : "{$errores} cuota(s) con error, identificadas para su reproceso (A4).", [
            'fecha' => $fecha, 'cuotas' => $cuotas, 'cuotas_vencidas' => $vencidas, 'errores' => $errores,
        ]);
    }

    /**
     * Versión de morosidad vigente al cierre del día (RN-35: cada día con la tasa que le corresponde).
     *
     * @return array{id: int, numero: int, valores: array<string, mixed>}
     */
    private function version(string $dia): array
    {
        return $this->versiones[$dia] ??= (new ParametroService($this->db))->vigente('Morosidad', $this->finDelDia($dia));
    }

    private function alicuota(string $dia): string
    {
        return $this->alicuotas[$dia] ??= (string) (new ParametroService($this->db))->vigente('Estructuracion', $this->finDelDia($dia))['valores']['alicuota_iva'];
    }

    private function finDelDia(string $dia): string
    {
        return Time::parse($dia . ' 23:59:59', config('Sigccr')->zonaNegocio)->setTimezone('UTC')->format('Y-m-d H:i:s.vP');
    }
}
