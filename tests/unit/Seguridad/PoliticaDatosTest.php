<?php

declare(strict_types=1);

namespace Tests\Unit\Seguridad;

use App\Libraries\Clientes\PoliticaDatos;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * S-13 · Contenido de la política de tratamiento de datos personales (RNF-14; Ley 25.326, arts. 4, 5, 6, 14, 16 y 20;
 * Res. AAIP 14/2018). Plan de pruebas 7.10.
 *
 * @internal
 */
final class PoliticaDatosTest extends CIUnitTestCase
{
    private const ENTIDAD = ['razonSocial' => 'Financiera Ejemplo S.A.', 'cuit' => '30712345678', 'domicilio' => 'Av. Siempre Viva 742, Posadas', 'correo' => 'datos@ejemplo.com.ar'];

    private function texto(array $entidad = self::ENTIDAD): string
    {
        $t = '';
        foreach (PoliticaDatos::secciones($entidad) as $s) {
            $t .= $s['titulo'] . "\n" . implode("\n", $s['parrafos']) . "\n";
        }

        return $t;
    }

    /** Art. 6: identifica al responsable con los datos de la configuración. */
    public function testIdentificaAlResponsable(): void
    {
        $t = $this->texto();
        foreach (['Financiera Ejemplo S.A.', '30712345678', 'Av. Siempre Viva 742, Posadas', 'datos@ejemplo.com.ar'] as $dato) {
            $this->assertStringContainsString($dato, $t);
        }
    }

    /** Sin datos opcionales de la entidad la política igual se lee completa (sin llaves ni vacíos). */
    public function testSinDatosOpcionalesDeLaEntidad(): void
    {
        $t = $this->texto(['razonSocial' => 'Entidad', 'cuit' => '', 'domicilio' => '', 'correo' => '']);
        $this->assertStringContainsString('el domicilio de la sucursal', $t);
        $this->assertStringNotContainsString(', CUIT ', $t);
        $this->assertDoesNotMatchRegularExpression('/\{|\}|null/', $t);
    }

    /** Art. 6, incisos a a f: finalidad, destinatarios, carácter de las respuestas, consecuencias y derechos. */
    public function testIncluyeLosPuntosDelArticuloSeis(): void
    {
        $titulos = array_column(PoliticaDatos::secciones(self::ENTIDAD), 'titulo');
        foreach (['Responsable del tratamiento', 'Qué datos se recogen', 'Para qué se usan', 'Carácter de las respuestas', 'A quién se comunican', 'Cuánto tiempo se conservan', 'Cómo se protegen', 'Sus derechos', 'Decisiones automatizadas', 'Reclamos'] as $titulo) {
            $this->assertContains($titulo, $titulos);
        }
    }

    /** Arts. 14 y 16: los plazos de la política son los que controla CU-108. */
    public function testInformaLosPlazosDeLosDerechos(): void
    {
        $this->assertSame(10, PoliticaDatos::DIAS_ACCESO);
        $this->assertSame(5, PoliticaDatos::DIAS_RECTIFICACION);
        $t = $this->texto();
        $this->assertStringContainsString('10 días corridos', $t);
        $this->assertStringContainsString('5 días hábiles', $t);
    }

    /** Art. 4, inc. 7: informa el plazo de eliminación de los pre-registros. */
    public function testInformaLaConservacionDePreRegistros(): void
    {
        $this->assertSame(90, PoliticaDatos::DIAS_CONSERVACION_PRE_REGISTRO);
        $this->assertStringContainsString('se eliminan a los 90 días', $this->texto());
    }

    /** Art. 20: informa el derecho a la revisión humana de la valoración automatizada. */
    public function testInformaLaRevisionHumana(): void
    {
        $this->assertStringContainsString('una persona de la entidad la revise', $this->texto());
    }

    /** Res. AAIP 14/2018: la leyenda lleva los tres elementos exigidos. */
    public function testLaLeyendaDeLaAaip(): void
    {
        $l = PoliticaDatos::LEYENDA_AAIP;
        $this->assertStringContainsString('en forma gratuita a intervalos no inferiores a seis meses', $l);
        $this->assertStringContainsString('artículo 14, inciso 3 de la Ley N.º 25.326', $l);
        $this->assertStringContainsString('Agencia de Acceso a la Información Pública', $l);
        $this->assertStringContainsString('atender las denuncias y reclamos', $l);
        $this->assertStringContainsString($l, $this->texto(), 'La política cierra con la leyenda.');
    }

    /** Art. 7: la política declara que no se recogen datos sensibles. */
    public function testNoSeRecogenDatosSensibles(): void
    {
        $this->assertStringContainsString('No se recogen datos sensibles', $this->texto());
    }
}
