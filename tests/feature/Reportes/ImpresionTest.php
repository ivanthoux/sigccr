<?php

declare(strict_types=1);

namespace Tests\Feature\Reportes;

use Tests\Support\Feature\Fabrica;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-73 · Impresión formal (D-57; RNF-02): cada pantalla del panel lleva la cabecera y el pie de la hoja impresa
 * (entidad, título, fecha y hora de emisión, usuario y «Página N de M») y la cabecera formal de la primera hoja. El
 * título puede contener datos ingresados por los usuarios (el nombre de una línea, la denominación de un cliente): no
 * puede cerrar el estilo ni el atributo en que se inserta. Plan de pruebas 7.9.2 y Anexo B.19.
 *
 * @internal
 */
final class ImpresionTest extends IntegracionTestCase
{
    /** @return array{0: string, 1: string} contenido del estilo de impresión y resto de la página */
    private function estiloImpreso(string $html): array
    {
        $this->assertSame(1, preg_match('#<style media="print">(.*?)</style>#s', $html, $m), 'La página tiene el estilo de impresión');

        return [$m[1], $html];
    }

    public function testCabeceraYPieDeCadaHoja(): void
    {
        $this->comoRol('Gerente');
        foreach (['admin/tablero' => [], 'admin/reportes/cartera' => ['generar' => '1'], 'admin/clientes' => [], 'admin/caja-diaria' => []] as $ruta => $parametros) {
            [$estilo, $html] = $this->estiloImpreso((string) $this->pedir($ruta, $parametros)->response()->getBody());
            $this->assertStringContainsString('counter(page)', $estilo, $ruta);
            $this->assertStringContainsString('counter(pages)', $estilo, $ruta);
            $this->assertStringContainsString(str_replace('"', '\\"', config('Sigccr')->entidadRazonSocial), $estilo, $ruta);
            $this->assertStringContainsString('Emitido el ', $estilo, $ruta);
            $this->assertStringContainsString('cabecera-impresion', $html, $ruta);
            $this->assertMatchesRegularExpression('/Emitido el \d{2}\/\d{2}\/\d{4} \d{2}:\d{2} por [^"]+ · SIG-CCR/u', $estilo, "{$ruta}: fecha, hora y usuario que emite");
        }
    }

    /** Un título con comillas, barra invertida, llaves y una etiqueta de cierre no sale del estilo ni del HTML. */
    public function testTituloConDatosDelUsuario(): void
    {
        $nombre = 'Motos "A" \\ } @page { </style><script>alert(1)</script>';
        $linea = $this->fabrica->linea(['nombre' => $nombre, 'codigo' => 'P' . strtoupper(substr(Fabrica::sufijo(), 0, 8))]);
        $this->comoRol('Gerente');
        $html = (string) $this->pedir("admin/lineas/{$linea['id']}")->response()->getBody();
        [$estilo] = $this->estiloImpreso($html);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html, 'La etiqueta nunca llega sin escapar');
        $this->assertStringContainsString('content: "Motos \\"A\\" \\\\ } @page { \\3C /style\\3E \\3C script\\3E alert(1)\\3C /script\\3E "', $estilo);
        $this->assertStringContainsString(esc($nombre), $html, 'En la cabecera formal, el título escapado');
    }
}
