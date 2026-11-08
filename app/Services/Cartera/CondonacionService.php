<?php

declare(strict_types=1);

namespace App\Services\Cartera;

use App\Exceptions\ReglaNegocioException;
use App\Exceptions\ValidacionException;
use App\Libraries\Caja\CobroCuotas;
use App\Libraries\Cartera\Condonacion;
use App\Libraries\Finanzas\Decimal;
use App\Libraries\Validacion\Campos;
use App\Services\BaseService;
use App\Services\Comunes\AuditoriaService;
use App\Services\Comunes\ParametroService;

/** CU-44 Condonar Intereses Punitorios (Gerente). RN-36, RN-38, D-36. */
class CondonacionService extends BaseService
{
    private const IMPAGAS = ['Pendiente', 'Vencida'];

    /**
     * CU-44 paso 3 (A3): cuotas del cliente con punitorios exigibles pendientes, calculados hasta hoy.
     *
     * @return array{cuotas: list<array<string, mixed>>, limite: string}
     */
    public function obligaciones(int $clienteId): array
    {
        $ids = array_map('intval', array_column($this->db->query(
            "SELECT q.id FROM dbo.cuotas q JOIN dbo.creditos c ON c.id = q.credito_id WHERE c.cliente_id = ? AND c.estado IN ('Activo', 'EnMora') AND q.estado IN ('Pendiente', 'Vencida')",
            [$clienteId],
        )->getResultArray(), 'id'));
        $this->transaccion(fn () => (new PunitorioService($this->db))->devengarCuotas($ids, $this->hoyNegocio()));
        $cuotas = $ids === [] ? [] : $this->db->query(
            'SELECT q.*, c.numero AS credito_numero FROM dbo.cuotas q JOIN dbo.creditos c ON c.id = q.credito_id WHERE q.id IN (' . implode(', ', $ids) . ') '
            . 'AND ROUND(q.punitorio_pendiente, 2) > 0 ORDER BY q.vencimiento, c.numero, q.numero',
        )->getResultArray();
        foreach ($cuotas as &$q) {
            $q['conceptos'] = CobroCuotas::conceptos($q);
            $q['dias_atraso'] = CobroCuotas::diasAtraso((string) $q['vencimiento'], $this->hoyNegocio());
        }
        unset($q);

        return ['cuotas' => $cuotas, 'limite' => $this->limite()->toFixed(4)];
    }

    /**
     * CU-44 pasos 4 a 10 (A1, A2).
     *
     * @param list<int|string> $cuotaIds
     * @return array{id: int, importe: string, cuotas: int, propuestas: int}
     */
    public function condonar(int $gerenteId, int $clienteId, array $cuotaIds, string $importeTexto, string $fundamentoTexto): array
    {
        $fundamento = Campos::texto($fundamentoTexto);
        $importe = Campos::importe($importeTexto);
        $errores = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $cuotaIds))));
        if ($ids === []) {
            $errores['cuotas'] = 'Seleccione al menos una cuota con punitorios pendientes.';
        }
        if ($importe === null || !Decimal::of($importe)->isPositive()) {
            $errores['importe'] = 'Ingrese el importe a condonar, mayor que cero.';
        }
        if (($e = Campos::motivo($fundamento)) !== null) {
            $errores['fundamento'] = 'Indique el fundamento de la condonación (al menos 5 caracteres, A2).';
        }
        if ($errores !== []) {
            throw new ValidacionException($errores, 'RN-38');
        }

        return $this->transaccion(function () use ($gerenteId, $clienteId, $ids, $importe, $fundamento): array {
            (new PunitorioService($this->db))->devengarCuotas($ids, $this->hoyNegocio());
            $cuotas = $this->db->query(
                'SELECT q.*, c.cliente_id FROM dbo.cuotas q WITH (UPDLOCK, ROWLOCK) JOIN dbo.creditos c ON c.id = q.credito_id WHERE q.id IN (' . implode(', ', $ids) . ') ORDER BY q.vencimiento, q.id',
            )->getResultArray();
            foreach ($cuotas as $q) {
                if ((int) $q['cliente_id'] !== $clienteId || !in_array($q['estado'], self::IMPAGAS, true)) {
                    throw new ReglaNegocioException('Alguna cuota seleccionada no es del cliente o ya no está pendiente.');
                }
            }
            if (count($cuotas) !== count($ids)) {
                throw new ReglaNegocioException('Alguna cuota seleccionada no existe.');
            }
            $limite = $this->limite();
            $pendiente = Condonacion::maximo($cuotas, Decimal::one());
            $maximo = Condonacion::maximo($cuotas, $limite);
            $monto = Decimal::of((string) $importe);
            if ($monto->compare($maximo) > 0) {
                throw new ValidacionException(['importe' => 'El importe supera el máximo condonable: ' . formato_importe($maximo->toFixed(2)) . ' (' . rtrim(rtrim(number_format((float) $limite->toFixed(4) * 100, 2, ',', ''), '0'), ',') . ' % de ' . formato_importe($pendiente->toFixed(2)) . ' de punitorios pendientes, A1).'], 'RN-38');
            }
            $partes = Condonacion::distribuir($monto, $cuotas);
            $this->db->table('condonaciones')->insert(['fecha' => $this->ahoraUtc(), 'importe' => $monto->toFixed(2), 'fundamento' => mb_substr($fundamento, 0, 1000), 'usuario_id' => $gerenteId, 'cliente_id' => $clienteId]);
            $id = (int) $this->db->insertID();
            $porId = array_column($cuotas, null, 'id');
            foreach ($partes as $x) {
                $this->db->table('condonaciones_cuotas')->insert(['condonacion_id' => $id, 'cuota_id' => $x['cuota'], 'importe' => $x['importe']->toFixed(4), 'iva' => $x['iva']->toFixed(4)]);
                $this->actualizarConVersion('cuotas', $x['cuota'], (int) $porId[$x['cuota']]['version_fila'], [
                    'punitorio_pendiente' => $x['punitorio_pendiente']->toFixed(4), 'iva_punitorio_pendiente' => $x['iva_punitorio_pendiente']->toFixed(4),
                ]);
            }
            // RN-36: la condonación deja Observadas las propuestas vigentes que comprenden las cuotas
            $antes = $this->propuestasVigentes(array_column($partes, 'cuota'));
            (new RefinanciacionService($this->db))->observarPorCuotas(array_column($partes, 'cuota'), 'Condonación de punitorios de ' . formato_importe($monto->toFixed(2)) . ' sobre cuotas comprendidas (CU-44).');
            (new AuditoriaService($this->db))->registrar('punitorios.condonar', 'condonaciones', $id, 'Exitoso', $fundamento, [
                'cliente' => $clienteId, 'importe' => $monto->toFixed(2), 'maximo' => $maximo->toFixed(2), 'limite' => $limite->toFixed(4),
                'cuotas' => array_map(static fn ($x) => ['cuota' => $x['cuota'], 'importe' => $x['importe']->toFixed(4), 'iva' => $x['iva']->toFixed(4)], $partes),
                'propuestas_observadas' => $antes,
            ]);

            return ['id' => $id, 'importe' => $monto->toFixed(2), 'cuotas' => count($partes), 'propuestas' => count($antes)];
        });
    }

    private function limite(): Decimal
    {
        return Decimal::of((string) ((new ParametroService($this->db))->vigente('Morosidad')['valores']['limite_condonacion'] ?? '1'));
    }

    /**
     * @param list<int> $cuotaIds
     * @return list<int> números de las propuestas vigentes que comprenden las cuotas
     */
    private function propuestasVigentes(array $cuotaIds): array
    {
        return $cuotaIds === [] ? [] : array_map('intval', array_column($this->db->query(
            'SELECT DISTINCT p.numero FROM dbo.propuestas_refinanciacion p JOIN dbo.propuestas_cuotas pc ON pc.propuesta_id = p.id '
            . "WHERE p.estado IN ('PendienteResolucion', 'AprobadaPendienteAceptacion') AND pc.cuota_id IN (" . implode(', ', array_map('intval', $cuotaIds)) . ')',
        )->getResultArray(), 'numero'));
    }
}
