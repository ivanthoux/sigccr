<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Libraries\Clientes\RequisitosDocumentales;
use CodeIgniter\Database\Seeder;

/** Documentación base por categoría de ingresos (RN-11). Cada línea puede exigir documentación adicional. */
class TiposDocumentacionSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['DOC_IDENTIDAD', 'Documento de identidad', 'Ambos', 1],
            ['RECIBO_SUELDO', 'Recibos de sueldo (últimos tres)', 'PersonaHumana', 1],
            ['RECIBO_HABERES', 'Último recibo de haberes previsionales', 'PersonaHumana', 1],
            ['CONST_MONOTRIBUTO', 'Constancia de inscripción en el monotributo', 'PersonaHumana', 1],
            ['PAGOS_MONOTRIBUTO', 'Comprobantes de pago del monotributo (seis meses)', 'PersonaHumana', 1],
            ['CONST_INSCRIPCION', 'Constancia de inscripción (responsable inscripto)', 'PersonaHumana', 1],
            ['DDJJ_GANANCIAS', 'Última declaración jurada del impuesto a las ganancias', 'PersonaHumana', 1],
            ['ESTATUTO', 'Estatuto o contrato social', 'PersonaJuridica', 1],
            ['CONST_CUIT', 'Constancia de CUIT', 'PersonaJuridica', 1],
            ['ESTADOS_CONTABLES', 'Últimos estados contables', 'PersonaJuridica', 1],
            ['PODER_REPRESENTACION', 'Instrumento que acredita la representación', 'PersonaJuridica', 1],
        ];
        // Constancia firmada del contrato (RN-78): documento interno, inactivo para no ofrecerse como exigible
        $tipos[] = ['CONTRATO_FIRMADO', 'Constancia firmada del contrato de préstamo', 'Ambos', 0, 0];
        foreach ($tipos as $t) {
            [$codigo, $nombre, $aplica, $clave] = $t;
            if ($this->db->table('tipos_documentacion')->where('codigo', $codigo)->countAllResults() === 0) {
                $this->db->table('tipos_documentacion')->insert(['codigo' => $codigo, 'nombre' => $nombre, 'aplica_a' => $aplica, 'es_clave' => $clave, 'activo' => $t[4] ?? 1]);
            }
        }
        // Documentación base por categoría (D-25): solo en una instalación nueva, sin pisar la configurada
        if ($this->db->tableExists('documentacion_sugerida') && $this->db->table('documentacion_sugerida')->countAllResults() === 0) {
            $ids = array_column($this->db->table('tipos_documentacion')->select('id, codigo')->get()->getResultArray(), 'id', 'codigo');
            foreach (RequisitosDocumentales::SUGERIDOS_INICIALES as $categoria => $codigos) {
                foreach ($codigos as $c) {
                    $this->db->table('documentacion_sugerida')->insert(['categoria_ingresos' => $categoria, 'tipo_documentacion_id' => (int) $ids[$c]]);
                }
            }
        }
    }
}
