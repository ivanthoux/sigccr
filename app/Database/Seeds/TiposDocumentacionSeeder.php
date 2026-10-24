<?php

declare(strict_types=1);

namespace App\Database\Seeds;

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
        foreach ($tipos as [$codigo, $nombre, $aplica, $clave]) {
            if ($this->db->table('tipos_documentacion')->where('codigo', $codigo)->countAllResults() === 0) {
                $this->db->table('tipos_documentacion')->insert(['codigo' => $codigo, 'nombre' => $nombre, 'aplica_a' => $aplica, 'es_clave' => $clave, 'activo' => 1]);
            }
        }
    }
}
