<?php

declare(strict_types=1);

namespace Tests\Feature\Documentacion;

use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-74 · Nombre del tipo de documento (CU-11, RN-11; G10). El nombre se guarda en una columna NVARCHAR(120): el
 * largo se mide en unidades UTF-16, igual que SQL Server, así que un emoji cuenta dos. Con mb_strlen() un nombre de
 * 119 letras y un emoji pasaba la validación y fallaba al guardarse con un error 500.
 *
 * @internal
 */
final class TiposDocumentoTest extends IntegracionTestCase
{
    private const MENSAJE = 'Ingrese el nombre del documento (de 5 a 120 caracteres).';

    /** @return array<string, string> */
    private static function datos(string $nombre): array
    {
        return ['nombre' => $nombre, 'aplica_a' => 'Ambos'];
    }

    public function testNombreConLosLimitesDeLargo(): void
    {
        $this->comoRol('Gerente');

        $this->assertRechazos('admin/documentacion/tipos', self::datos('Constancia de ingresos'), [
            'nombre de 4' => [['nombre' => 'Cuat'], 'nombre', self::MENSAJE],
            'nombre de 121' => [['nombre' => str_repeat('n', 121)], 'nombre', self::MENSAJE],
            'nombre de 119 y un emoji (121 unidades)' => [['nombre' => str_repeat('n', 119) . '😀'], 'nombre', self::MENSAJE],
            'nombre de 4 y un emoji (6 unidades) que mb_strlen contaría como 5' => [['nombre' => 'Ab😀'], 'nombre', null],
        ], ['tipos_documentacion']);
    }

    public function testNombreDeCiento20UnidadesConEmojiSeGuarda(): void
    {
        $this->comoRol('Gerente');
        $nombre = str_repeat('n', 118) . '😀';

        $this->assertAceptado($this->enviar('admin/documentacion/tipos', self::datos($nombre)));

        $guardado = $this->db->table('tipos_documentacion')->where('id', $this->ultimoId('tipos_documentacion'))->get()->getRow();
        $this->assertSame($nombre, $guardado->nombre);
    }
}
