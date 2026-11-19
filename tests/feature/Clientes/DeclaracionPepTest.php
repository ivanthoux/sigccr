<?php

declare(strict_types=1);

namespace Tests\Feature\Clientes;

use App\Exceptions\ValidacionException;
use App\Libraries\Clientes\RequisitosDocumentales;
use App\Services\Clientes\ClienteService;
use App\Services\Clientes\LegajoService;
use Tests\Support\Feature\Fabrica;
use Tests\Support\Feature\IntegracionTestCase;

/**
 * IN-72 · Declaración jurada de persona expuesta políticamente (D-55; RN-80, CU-11, CU-15, CU-27): opcional en el
 * alta, se puede incorporar después al legajo y se exige para liquidar. El archivo se valida como cualquier documento
 * del legajo (ARC-01 a ARC-06). Plan de pruebas 7.9.2 y Anexo B.19.
 *
 * @internal
 */
final class DeclaracionPepTest extends IntegracionTestCase
{
    private const PEP = RequisitosDocumentales::DECLARACION_PEP;

    /** ARC-01 a ARC-06 con la declaración en el alta; sin ella, el alta se registra igual. */
    public function testDeclaracionEnElAlta(): void
    {
        $servicio = new ClienteService($this->db);
        $responsable = $this->fabrica->responsable();
        $casos = [
            'vacío' => [Fabrica::archivo(self::PEP, 'vacio', null, 'pep.pdf'), 'El archivo pep.pdf está vacío.'],
            '5 MB + 1 byte' => [Fabrica::archivo(self::PEP, 'pdf', 5 * 1024 * 1024 + 1, 'pep.pdf'), 'El archivo pep.pdf supera los 5 MB.'],
            'GIF' => [Fabrica::archivo(self::PEP, 'gif', null, 'pep.gif'), 'El archivo pep.gif no es PDF, JPG ni PNG.'],
            'ejecutable con extensión pdf' => [Fabrica::archivo(self::PEP, 'exe', null, 'pep.pdf'), 'El archivo pep.pdf no es PDF, JPG ni PNG.'],
        ];
        foreach ($casos as $caso => [$archivo, $mensaje]) {
            $antes = $this->ultimoId('clientes');
            try {
                $servicio->registrar($this->fabrica->datosCliente(), [...Fabrica::archivosIdentificacion(), $archivo], $responsable);
                $this->fail("{$caso}: se esperaba el rechazo");
            } catch (ValidacionException $e) {
                $this->assertSame($mensaje, $e->errores['doc_' . self::PEP] ?? null, $caso);
            }
            $this->assertSame($antes, $this->ultimoId('clientes'), "{$caso}: el rechazo no registra el cliente");
        }

        $legajo = new LegajoService($this->db);
        $sin = $servicio->registrar($this->fabrica->datosCliente(), Fabrica::archivosIdentificacion(), $responsable);
        $this->assertFalse($legajo->tieneDocumento($sin, self::PEP), 'Opcional en el alta');
        foreach (['pdf', 'jpg', 'png'] as $tipo) {
            $con = $servicio->registrar($this->fabrica->datosCliente(), [...Fabrica::archivosIdentificacion(), Fabrica::archivo(self::PEP, $tipo, null, "pep.{$tipo}")], $responsable);
            $this->assertTrue($legajo->tieneDocumento($con, self::PEP), $tipo);
        }
        $con = $servicio->registrar($this->fabrica->datosCliente(), [...Fabrica::archivosIdentificacion(), Fabrica::archivo(self::PEP, 'pdf', 5 * 1024 * 1024, 'pep.pdf')], $responsable);
        $this->assertTrue($legajo->tieneDocumento($con, self::PEP), '5 MB exactos se aceptan');
    }

    /**
     * La declaración se incorpora después al legajo (CU-15; la petición HTTP con archivos no puede simularse, como en
     * IN-10) y entonces habilita la liquidación, que sin ella se rechaza (RN-80).
     */
    public function testDeclaracionIncorporadaAlLegajo(): void
    {
        $servicio = new ClienteService($this->db);
        $legajo = new LegajoService($this->db);
        $c = $this->fabrica->cliente();
        $responsable = $this->fabrica->responsable();
        try {
            $servicio->agregarDocumentos($c['id'], [Fabrica::archivo(self::PEP, 'exe', null, 'pep.pdf')], $responsable);
            $this->fail('Un ejecutable no se incorpora');
        } catch (ValidacionException $e) {
            $this->assertSame('El archivo pep.pdf no es PDF, JPG ni PNG.', $e->errores['doc_' . self::PEP] ?? null);
        }
        $this->assertFalse($legajo->tieneDocumento($c['id'], self::PEP));
        $this->assertSame(1, $servicio->agregarDocumentos($c['id'], [Fabrica::archivo(self::PEP, 'pdf', null, 'declaracion pep.pdf')], $responsable));
        $this->assertTrue($legajo->tieneDocumento($c['id'], self::PEP));
        // Una segunda declaración se agrega al legajo; sigue habiendo una vigente
        $servicio->agregarDocumentos($c['id'], [Fabrica::archivo(self::PEP, 'png', null, 'pep.png')], $responsable);
        $this->assertTrue($legajo->tieneDocumento($c['id'], self::PEP));
    }
}
