<?php

declare(strict_types=1);

namespace Tests\Unit\Solicitudes;

use App\Libraries\Solicitudes\ModeloContrato as M;
use PHPUnit\Framework\TestCase;

/**
 * D-56: modelo de contrato configurable (CU-106). Las cláusulas admiten variables y bloques; lo que exige la ley
 * (costo financiero destacado, plan de pago y revocación) no se puede quitar.
 */
final class ModeloContratoTest extends TestCase
{
    /** @return list<array{estilo: string, titulo: string, texto: string}> */
    private static function minimo(array $extra = []): array
    {
        return [
            ['estilo' => 'numerada', 'titulo' => 'Costo', 'texto' => "{cuadro_cft}\n\nTNA {tna}."],
            ['estilo' => 'numerada', 'titulo' => 'Plan', 'texto' => '{plan_cuotas}'],
            ['estilo' => 'recuadro', 'titulo' => '', 'texto' => 'Puede revocar dentro de los {plazo_revocacion} días hábiles.'],
            ...$extra,
        ];
    }

    public function testElModeloInicialEsValido(): void
    {
        $this->assertSame([], M::validar(M::INICIAL));
        $this->assertSame([], M::validar(self::minimo()));
    }

    public function testComponerNumeraReemplazaYEscapa(): void
    {
        $k = M::componer(M::INICIAL, ['entidad' => 'Crédito <b>Sur</b>', 'entidad_datos' => '', 'cliente' => 'Ana Gómez', 'importe' => '$ 500.000,00', 'plazo_revocacion' => '10']);
        $this->assertCount(11, $k);
        $this->assertSame([null, 1, 2, 3, 4, 5, 6, 7, 8, null, null], array_column($k, 'numero'), 'solo las numeradas, desde 1');
        $this->assertSame('Partes', $k[0]['titulo']);
        $this->assertStringStartsWith('Crédito &lt;b&gt;Sur&lt;/b&gt; (en adelante', $k[0]['parrafos'][0]['html'], 'los valores se escapan');
        $this->assertStringContainsString('<strong>$ 500.000,00</strong>', $k[1]['parrafos'][0]['html'], 'negrita con **');
        $this->assertSame(['cuadro_cft', 'texto'], array_column($k[2]['parrafos'], 'tipo'));
        $this->assertSame(['texto', 'plan_cuotas'], array_column($k[3]['parrafos'], 'tipo'));
        $this->assertSame('recuadro', $k[9]['estilo']);
        $this->assertStringContainsString('dentro de los 10 días hábiles', $k[9]['parrafos'][0]['html']);

        // El texto del Gerente también se escapa, y un salto simple es un salto de línea
        $html = M::componer([['estilo' => 'sin_numero', 'titulo' => '', 'texto' => "<script>x</script>\nSegunda línea"]], [])[0]['parrafos'][0]['html'];
        $this->assertSame("&lt;script&gt;x&lt;/script&gt;<br>\nSegunda línea", $html);
    }

    public function testRechazos(): void
    {
        $casos = [
            'sin cláusulas' => [[], 'clausulas', 'El contrato debe tener al menos una cláusula.'],
            'variable desconocida' => [self::minimo([['estilo' => 'numerada', 'titulo' => 'X', 'texto' => 'Monto {monto} y {tna}']]), 'clausula_4_texto', 'Variable desconocida: {monto}. Use las de la lista.'],
            'llave sin cerrar' => [self::minimo([['estilo' => 'numerada', 'titulo' => 'X', 'texto' => 'Monto {importe']]), 'clausula_4_texto', 'Hay una llave sin cerrar: las variables se escriben entre { y }.'],
            'numerada sin título' => [self::minimo([['estilo' => 'numerada', 'titulo' => '', 'texto' => 'Texto']]), 'clausula_4_titulo', 'Una cláusula numerada lleva título.'],
            'forma inválida' => [self::minimo([['estilo' => 'pie', 'titulo' => '', 'texto' => 'Texto']]), 'clausula_4_estilo', 'Elija la forma de la cláusula.'],
            'texto vacío' => [self::minimo([['estilo' => 'sin_numero', 'titulo' => 'Anexo', 'texto' => '']]), 'clausula_4_texto', 'Escriba el texto de la cláusula.'],
            'texto largo' => [self::minimo([['estilo' => 'sin_numero', 'titulo' => '', 'texto' => str_repeat('a', M::MAX_TEXTO + 1)]]), 'clausula_4_texto', 'El texto admite hasta 5.000 caracteres.'],
            'título largo' => [self::minimo([['estilo' => 'numerada', 'titulo' => str_repeat('t', M::MAX_TITULO + 1), 'texto' => 'Texto']]), 'clausula_4_titulo', 'El título admite hasta 100 caracteres.'],
            'bloque dentro de un párrafo' => [[['estilo' => 'numerada', 'titulo' => 'C', 'texto' => 'El costo es {cuadro_cft}'], ...array_slice(self::minimo(), 1)], 'clausula_1_texto', '{cuadro_cft} va solo en un párrafo: deje una línea en blanco antes y después.'],
            'sin el costo financiero' => [array_slice(self::minimo(), 1), 'clausulas', 'Falta {cuadro_cft}: el costo financiero total debe informarse destacado (art. 36 de la Ley 24.240).'],
            'plan repetido' => [self::minimo([['estilo' => 'numerada', 'titulo' => 'Otra vez', 'texto' => '{plan_cuotas}']]), 'clausulas', '{plan_cuotas} va una sola vez: el contrato debe incluir el plan de pago (art. 36 de la Ley 24.240).'],
            'sin la revocación' => [array_slice(self::minimo(), 0, 2), 'clausulas', 'Falta la cláusula del derecho de revocación con {plazo_revocacion}: el derecho es irrenunciable (art. 1110 del Código Civil y Comercial, RN-79).'],
        ];
        foreach ($casos as $caso => [$clausulas, $campo, $mensaje]) {
            $e = M::validar($clausulas);
            $this->assertSame($mensaje, $e[$campo] ?? null, $caso);
        }
        $this->assertSame('El contrato admite hasta 40 cláusulas.', M::validar(self::minimo(array_fill(0, 38, ['estilo' => 'sin_numero', 'titulo' => '', 'texto' => 'x'])))['clausulas'] ?? null);
        $this->assertSame([], M::validar(self::minimo(array_fill(0, 37, ['estilo' => 'sin_numero', 'titulo' => '', 'texto' => 'x']))), '40 cláusulas se admiten');
    }

    public function testNormalizarElFormulario(): void
    {
        $this->assertSame([
            ['estilo' => 'numerada', 'titulo' => 'Mora', 'texto' => "Primera línea\nSegunda\n\nOtro párrafo"],
            ['estilo' => 'sin_numero', 'titulo' => '', 'texto' => 'Cierre'],
        ], M::normalizar([
            'estilo' => ['numerada', 'numerada', 'sin_numero'],
            'titulo' => ['  Mora   ', '', ''],
            'texto' => ["Primera línea  \r\nSegunda\r\n\r\n\r\n\r\nOtro párrafo ", '   ', 'Cierre'],
        ]));
        $this->assertSame([], M::normalizar(['texto' => 'no es una lista']));
    }
}
