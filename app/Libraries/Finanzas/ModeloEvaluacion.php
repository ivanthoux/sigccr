<?php

declare(strict_types=1);

namespace App\Libraries\Finanzas;

/**
 * Modelo de evaluación crediticia (RN-69).
 *
 * Puntaje de 0 a 100 = suma ponderada de cuatro indicadores valorados de 0 a 100. Los pesos, valores y cortes son
 * parámetros (CU-58); PARAMETROS_INICIALES contiene los valores de RN-69 y es la forma del JSON guardado en
 * versiones_parametros (tipo Evaluacion).
 */
final class ModeloEvaluacion
{
    public const PARAMETROS_INICIALES = [
        'pesos' => ['bcra' => '0.40', 'comportamiento' => '0.25', 'endeudamiento' => '0.25', 'antiguedad' => '0.10'],
        // peor situación de los últimos 12 meses => valor; 'sin_deudas' cuando no hay deudas informadas
        'bcra' => ['1' => 100, '2' => 60, '3' => 20, '4' => 0, '5' => 0, '6' => 0, 'sin_deudas' => 70],
        // máximo atraso interno de 12 meses: [días hasta (inclusive), valor]; 'sin_antecedentes' sin historial interno
        'comportamiento' => ['tramos' => [[0, 100], [30, 70], [60, 30]], 'resto' => 0, 'sin_antecedentes' => 50],
        // relación cuotas / ingresos: [hasta (inclusive), valor]
        'endeudamiento' => ['tramos' => [['0.10', 100], ['0.20', 70], ['0.30', 40]], 'resto' => 0],
        // antigüedad en meses: [desde (inclusive), valor], de mayor a menor
        'antiguedad' => ['tramos' => [[24, 100], [12, 70], [6, 40]], 'resto' => 20],
        // cortes de categoría: puntaje mínimo
        'categorias' => ['A' => 80, 'B' => 65, 'C' => 50, 'D' => 35, 'E' => 0],
        'resultados' => ['A' => 'Favorable', 'B' => 'Favorable', 'C' => 'RequiereAnalisisGerencial', 'D' => 'Desfavorable', 'E' => 'Desfavorable'],
        // situaciones vigentes que producen resultado desfavorable cualquiera sea el puntaje
        'situaciones_excluyentes' => [4, 5, 6],
    ];

    /**
     * @param ?int  $peorSituacion12m       peor situación BCRA de 12 meses; null si no hay deudas informadas
     * @param ?int  $peorSituacionVigente   peor situación del último período informado; null si no hay deudas
     * @param ?int  $maxAtrasoInterno       máximo atraso interno de 12 meses en días; null sin antecedentes
     * @param array<string, mixed>|null $p  parámetros de la versión vigente (por defecto, los iniciales)
     *
     * @return array{puntaje: Decimal, categoria: string, resultado: string, indicadores: array<string, array{valor: int, peso: string, aporte: string}>}
     */
    public static function evaluar(
        ?int $peorSituacion12m,
        ?int $peorSituacionVigente,
        ?int $maxAtrasoInterno,
        Decimal $cuotasVigentesYComprometidas,
        Decimal $ingresos,
        int $antiguedadMeses,
        ?array $p = null,
    ): array {
        $p ??= self::PARAMETROS_INICIALES;

        $vBcra = $peorSituacion12m === null ? $p['bcra']['sin_deudas'] : $p['bcra'][(string) $peorSituacion12m];

        if ($maxAtrasoInterno === null) {
            $vComp = $p['comportamiento']['sin_antecedentes'];
        } else {
            $vComp = $p['comportamiento']['resto'];
            foreach ($p['comportamiento']['tramos'] as [$hasta, $valor]) {
                if ($maxAtrasoInterno <= $hasta) {
                    $vComp = $valor;
                    break;
                }
            }
        }

        $ratio = $ingresos->isPositive() ? $cuotasVigentesYComprometidas->div($ingresos) : Decimal::one();
        $vEnd = $p['endeudamiento']['resto'];
        foreach ($p['endeudamiento']['tramos'] as [$hasta, $valor]) {
            if ($ratio->compare($hasta) <= 0) {
                $vEnd = $valor;
                break;
            }
        }

        $vAnt = $p['antiguedad']['resto'];
        foreach ($p['antiguedad']['tramos'] as [$desde, $valor]) {
            if ($antiguedadMeses >= $desde) {
                $vAnt = $valor;
                break;
            }
        }

        $valores = ['bcra' => $vBcra, 'comportamiento' => $vComp, 'endeudamiento' => $vEnd, 'antiguedad' => $vAnt];
        $puntaje = Decimal::zero();
        $indicadores = [];
        foreach ($valores as $k => $v) {
            $aporte = Decimal::of((int) $v)->mul($p['pesos'][$k]);
            $puntaje = $puntaje->add($aporte);
            $indicadores[$k] = ['valor' => (int) $v, 'peso' => (string) $p['pesos'][$k], 'aporte' => $aporte->toFixed(2)];
        }
        $puntaje = $puntaje->round(2);

        $categoria = 'E';
        foreach ($p['categorias'] as $cat => $minimo) {
            if ($puntaje->compare((int) $minimo) >= 0) {
                $categoria = $cat;
                break;
            }
        }
        $resultado = $p['resultados'][$categoria];
        if ($peorSituacionVigente !== null && in_array($peorSituacionVigente, $p['situaciones_excluyentes'], true)) {
            $resultado = 'Desfavorable';
        }

        return ['puntaje' => $puntaje, 'categoria' => $categoria, 'resultado' => $resultado, 'indicadores' => $indicadores];
    }
}
