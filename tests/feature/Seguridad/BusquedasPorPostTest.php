<?php

declare(strict_types=1);

namespace Tests\Feature\Seguridad;

use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-79 · Las búsquedas con DNI, CUIT o CUIL se envían por POST y no dejan el identificador en la URL
 * (Ley 25.326 art. 4; OWASP ASVS 5.0 V14.2.1). Plan de pruebas 7.10.
 *
 * @internal
 */
final class BusquedasPorPostTest extends IntegracionTestCase
{
    /** El listado de clientes se busca por documento y por CUIL con un POST protegido por CSRF. */
    public function testBusquedaDeClientePorDocumentoYCuilPorPost(): void
    {
        $this->comoRol('AnalistaCredito');
        $c = $this->fabrica->cliente();
        $enlace = "admin/clientes/{$c['id']}\"";

        $porDni = $this->enviar('admin/clientes/buscar', ['documento' => (string) $c['datos']['nro_documento']]);
        $porDni->assertOK();
        $this->assertStringContainsString($enlace, (string) $porDni->response()->getBody());

        $porCuil = $this->enviar('admin/clientes/buscar', ['documento' => (string) $c['datos']['cuit_cuil']]);
        $porCuil->assertOK();
        $this->assertStringContainsString($enlace, (string) $porCuil->response()->getBody());
    }

    /** Sin token CSRF la búsqueda se rechaza como cualquier otro POST. */
    public function testBusquedaPorPostSinTokenSeRechaza(): void
    {
        $this->comoRol('AnalistaCredito');
        $c = $this->fabrica->cliente();

        $r = $this->enviar('admin/clientes/buscar', ['documento' => (string) $c['datos']['nro_documento']], false);
        $this->assertStringNotContainsString("admin/clientes/{$c['id']}\"", (string) $r->response()->getBody());
    }

    /** La búsqueda por POST exige el mismo permiso que el listado. */
    public function testBusquedaPorPostRequierePermiso(): void
    {
        $this->comoRol('Cajero');
        $this->enviar('admin/clientes/buscar', ['documento' => '20123456'])->assertStatus(403);
    }

    /** Las pantallas de listado envían su formulario por POST a la ruta de búsqueda. */
    public function testListadosEnvianElFormularioPorPost(): void
    {
        $this->comoRol('Gerente');
        $esperadas = [
            'admin/clientes' => 'admin/clientes/buscar',
            'admin/solicitudes' => 'admin/solicitudes/buscar',
            'admin/usuarios' => 'admin/usuarios/buscar',
        ];
        foreach ($esperadas as $pagina => $destino) {
            $html = (string) $this->pedir($pagina)->response()->getBody();
            $this->assertMatchesRegularExpression('#<form method="post" action="[^"]*' . preg_quote($destino, '#') . '"#', $html, $pagina);
            $this->assertStringContainsString('name="csrf_sigccr"', $html, $pagina);
        }
    }
}
