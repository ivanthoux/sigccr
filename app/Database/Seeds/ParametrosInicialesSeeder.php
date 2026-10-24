<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Libraries\Finanzas\ModeloEvaluacion;
use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

/**
 * Primera versión de cada grupo de parámetros, con los valores iniciales de 3.5.1. Rige desde la puesta en marcha.
 * Los valores que 3.5.1 dejaba «definidos por la entidad» son los de la decisión D-02, validada el 24/10/2026; se
 * modifican con CU-51 y CU-60.
 */
class ParametrosInicialesSeeder extends Seeder
{
    public function run(): void
    {
        $grupos = [
            'Evaluacion' => ModeloEvaluacion::PARAMETROS_INICIALES + ['vigencia_dias' => 30],
            'Estructuracion' => [
                'porcentaje_afectacion' => '0.30',
                'alicuota_iva' => '0.21',
                'vigencia_alternativas_dias' => 7,
                'paso_redondeo' => '1000',
                'escalones' => ['1', '0.75', '0.5'],
            ],
            'Morosidad' => [
                'tasa_punitoria_diaria' => '0.0012',
                'tope_sobre_tna' => '0.50',
                'tolerancia_dias' => 3,
                'umbral_morosidad_avanzada_dias' => 60,
                'limite_condonacion' => '1.00',
                'refinanciacion' => ['tasa_minima' => '0.30', 'tasa_maxima' => '0.80', 'cuotas_maximas' => 36],
            ],
            'Campanas' => [
                'umbral_disponibilidad' => '5000000.00',
                'periodo_proyeccion_dias' => 30,
                'factor_cobrabilidad' => '0.90',
                'reserva_minima' => '1000000.00',
                'capital_maximo' => '3000000.00',
                'duracion_dias' => 15,
            ],
        ];
        $ahora = Time::now('UTC')->format('Y-m-d H:i:s.vP');
        foreach ($grupos as $tipo => $valores) {
            if ($this->db->table('versiones_parametros')->where('tipo', $tipo)->countAllResults() > 0) {
                continue;
            }
            $this->db->table('versiones_parametros')->insert([
                'tipo' => $tipo,
                'numero' => 1,
                'vigente_desde' => $ahora,
                'valores' => json_encode($valores, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'motivo' => 'Carga inicial (3.5.1)',
                'creado_en' => $ahora,
            ]);
        }
    }
}
