<?php

declare(strict_types=1);

namespace Tests\Unit\Clientes;

use App\Libraries\Clientes\ArchivoLegajo;
use App\Libraries\Clientes\CategoriasMonotributo;
use App\Libraries\Clientes\ReglasClientes;
use App\Libraries\Clientes\RequisitosDocumentales;
use App\Libraries\Validacion\Campos;
use App\Libraries\Validacion\ValidadorCliente;
use PHPUnit\Framework\TestCase;

/** Validación de clientes (CU-11, CU-12; RN-07, RN-08, RN-11, RN-75) y del legajo (RNF-12). */
final class ClientesTest extends TestCase
{
    private const HOY = '2026-10-24';

    /** @return array<string, string> */
    private function humana(): array
    {
        return [
            'tipo_cliente' => 'PersonaHumana', 'nombre' => 'Lucía', 'apellido' => 'Fernández', 'tipo_documento' => 'DNI',
            'nro_documento' => '30.111.222', 'cuit_cuil' => '27-30111222-?', 'fecha_nacimiento' => '15/03/1985', 'estado_civil' => 'Casado',
            'correo' => 'lucia@correo.com', 'telefono' => '+54 351 555-1234', 'domicilio' => 'San Martín 123', 'provincia_id' => '14', 'localidad_id' => '3', 'codigo_postal' => '5000',
            'ingresos_declarados' => '850.000,00', 'categoria_ingresos' => 'RelacionDependencia', 'acepta_politica' => '1',
            'nacionalidad' => 'Argentina', 'ocupacion' => 'Docente', 'persona_expuesta' => 'No',
        ];
    }

    /** @return array<string, string> */
    private function juridica(): array
    {
        return [
            'tipo_cliente' => 'PersonaJuridica', 'razon_social' => 'Panificadora del Centro SRL', 'cuit_cuil' => '30-50001091-2',
            'fecha_constitucion' => '2015-06-01', 'actividad_principal' => 'Elaboración de productos de panadería',
            'representante_nombre' => 'Jorge Pérez', 'representante_documento' => '20111222', 'representante_cargo' => 'Socio gerente',
            'correo' => 'admin@panificadora.com.ar', 'domicilio' => 'Belgrano 450', 'provincia_id' => '14', 'localidad_id' => '3', 'ingresos_declarados' => '12500000', 'acepta_politica' => 'on',
            'persona_expuesta' => 'No',
        ];
    }

    /** @param array<string, string> $datos */
    private function errores(array $datos, bool $alta = true): array
    {
        return ValidadorCliente::validar(ValidadorCliente::normalizar($datos), $alta, self::HOY);
    }

    private function cuilValido(string $prefijo, string $dni): string
    {
        foreach (range(0, 9) as $dv) {
            if (Campos::cuit($prefijo . $dni . $dv) === null) {
                return $prefijo . $dni . $dv;
            }
        }

        return '';
    }

    public function testPersonaHumanaValida(): void
    {
        $this->assertSame([], $this->errores([...$this->humana(), 'cuit_cuil' => $this->cuilValido('27', '30111222')]));
        // O-04 (D-52): obligatorio en el alta; los clientes anteriores lo completan al solicitar un crédito
        $this->assertSame(['cuit_cuil' => 'Complete el CUIL o CUIT.'], $this->errores([...$this->humana(), 'cuit_cuil' => '']));
        $this->assertSame([], $this->errores([...$this->humana(), 'cuit_cuil' => ''], false), 'opcional al modificar');
    }

    /** O-02 (D-52): un tipo de cliente fuera de la lista ya no se toma como persona humana. */
    public function testTipoDeClienteFueraDeLista(): void
    {
        $base = [...$this->humana(), 'cuit_cuil' => $this->cuilValido('27', '30111222')];
        foreach (['Empresa', 'personahumana', '1', ''] as $tipo) {
            $this->assertSame(['tipo_cliente' => 'Seleccione el tipo de cliente.'], $this->errores([...$base, 'tipo_cliente' => $tipo]), $tipo);
        }
        $this->assertArrayNotHasKey('tipo_cliente', $this->errores([...$base, 'tipo_cliente' => ' PersonaHumana ']), 'se recortan los espacios');
    }

    public function testPersonaJuridicaValidaYCategoriaEmpresa(): void
    {
        $this->assertSame([], $this->errores($this->juridica()));
        $d = ValidadorCliente::normalizar([...$this->juridica(), 'categoria_ingresos' => 'RelacionDependencia']);
        $this->assertSame('Empresa', $d['categoria_ingresos']);
    }

    public function testFormularioVacioDePersonaHumana(): void
    {
        $e = $this->errores([]);
        foreach (['tipo_cliente', 'cuit_cuil', 'nombre', 'apellido', 'tipo_documento', 'nro_documento', 'fecha_nacimiento', 'correo', 'domicilio', 'provincia_id', 'ingresos_declarados', 'categoria_ingresos', 'acepta_politica', 'nacionalidad', 'ocupacion', 'persona_expuesta'] as $campo) {
            $this->assertArrayHasKey($campo, $e, $campo);
        }
        $this->assertCount(16, $e);
        $this->assertSame('Seleccione el tipo de cliente.', $e['tipo_cliente']);
    }

    public function testFormularioVacioDePersonaJuridica(): void
    {
        $e = $this->errores(['tipo_cliente' => 'PersonaJuridica']);
        foreach (['razon_social', 'cuit_cuil', 'fecha_constitucion', 'actividad_principal', 'representante_nombre', 'representante_documento', 'representante_cargo', 'correo', 'domicilio', 'ingresos_declarados', 'acepta_politica'] as $campo) {
            $this->assertArrayHasKey($campo, $e, $campo);
        }
        $this->assertSame('Complete el CUIT.', $e['cuit_cuil']);
    }

    public function testPoliticaDeDatosSoloEnElAlta(): void
    {
        $sinPolitica = [...$this->humana(), 'cuit_cuil' => $this->cuilValido('27', '30111222'), 'acepta_politica' => ''];
        $this->assertSame(['acepta_politica' => 'El cliente debe aceptar la política de tratamiento de datos personales.'], $this->errores($sinPolitica));
        $this->assertSame([], $this->errores($sinPolitica, false));
    }

    public function testCuilDebeCorresponderAlDni(): void
    {
        $otro = $this->cuilValido('20', '29999888');
        $this->assertSame(['cuit_cuil' => 'El CUIT/CUIL no corresponde al número de documento.'], $this->errores([...$this->humana(), 'cuit_cuil' => $otro]));
        $this->assertSame(['cuit_cuil' => 'El dígito verificador no corresponde: para 27-30111222 el último dígito es 5.'], $this->errores([...$this->humana(), 'cuit_cuil' => '27301112220']));
    }

    public function testFechaDeNacimiento(): void
    {
        $base = [...$this->humana(), 'cuit_cuil' => $this->cuilValido('27', '30111222')];
        $this->assertSame(['fecha_nacimiento' => 'Ingrese una fecha válida (dd/mm/aaaa).'], $this->errores([...$base, 'fecha_nacimiento' => '31/02/1985']));
        $this->assertSame(['fecha_nacimiento' => 'La fecha de nacimiento no puede ser futura.'], $this->errores([...$base, 'fecha_nacimiento' => '01/01/2030']));
        $this->assertSame(['fecha_nacimiento' => 'El cliente debe ser mayor de edad (18 años).'], $this->errores([...$base, 'fecha_nacimiento' => '25/10/2008']));
        $this->assertSame([], $this->errores([...$base, 'fecha_nacimiento' => '24/10/2008']), 'cumple 18 hoy');
        // O-06 (D-52): hasta 110 años
        $this->assertSame([], $this->errores([...$base, 'fecha_nacimiento' => '25/10/1915']), 'tiene 110 años');
        $this->assertSame(['fecha_nacimiento' => 'Revise la fecha de nacimiento: la edad no puede superar 110 años.'], $this->errores([...$base, 'fecha_nacimiento' => '24/10/1915']), 'cumple 111 hoy');
        $this->assertSame(['fecha_nacimiento' => 'Revise la fecha de nacimiento: la edad no puede superar 110 años.'], $this->errores([...$base, 'fecha_nacimiento' => '01/01/1900']));
    }

    public function testFechaDeConstitucionNoFutura(): void
    {
        $this->assertSame(['fecha_constitucion' => 'La fecha de constitución no puede ser futura.'], $this->errores([...$this->juridica(), 'fecha_constitucion' => '2027-01-01']));
    }

    public function testIngresosTelefonoYEstadoCivil(): void
    {
        $base = [...$this->humana(), 'cuit_cuil' => $this->cuilValido('27', '30111222')];
        $this->assertSame(['ingresos_declarados' => 'Ingrese un importe válido, por ejemplo 850.000,00.'], $this->errores([...$base, 'ingresos_declarados' => '85O.000']));
        $this->assertSame(['ingresos_declarados' => 'Ingrese un importe válido, por ejemplo 850.000,00.'], $this->errores([...$base, 'ingresos_declarados' => '-100']));
        $this->assertSame('850000.00', ValidadorCliente::normalizar($base)['ingresos_declarados']);
        // O-07 (D-52)
        $this->assertSame(['ingresos_declarados' => 'Los ingresos declarados deben ser mayores que cero.'], $this->errores([...$base, 'ingresos_declarados' => '0']));
        $this->assertSame(['ingresos_declarados' => 'Los ingresos declarados deben ser mayores que cero.'], $this->errores([...$base, 'ingresos_declarados' => '0,00']));
        $this->assertSame([], $this->errores([...$base, 'ingresos_declarados' => '0,01']));
        $this->assertSame(['telefono' => 'Ingrese un teléfono válido (solo números, espacios, guiones o +).'], $this->errores([...$base, 'telefono' => 'llamar a la tarde']));
        $this->assertSame([], $this->errores([...$base, 'telefono' => '']), 'el teléfono es opcional');
        $this->assertSame(['estado_civil' => 'Seleccione un estado civil válido.'], $this->errores([...$base, 'estado_civil' => 'Otro']));
    }

    public function testCategoriaSegunTipoDeCliente(): void
    {
        $this->assertSame(['categoria_ingresos' => 'Seleccione una categoría de ingresos válida.'], $this->errores([...$this->humana(), 'cuit_cuil' => $this->cuilValido('27', '30111222'), 'categoria_ingresos' => 'Empresa']));
        $this->assertTrue(RequisitosDocumentales::categoriaValida('PersonaJuridica', 'Empresa'));
        $this->assertFalse(RequisitosDocumentales::categoriaValida('PersonaJuridica', 'Monotributista'));
    }

    public function testAltaSoloExigeIdentificacion(): void
    {
        // D-14: una persona humana se registra solo con su DNI, cualquiera sea su categoría de ingresos
        $this->assertSame([], RequisitosDocumentales::faltantes(RequisitosDocumentales::identificacion('PersonaHumana'), ['DOC_IDENTIDAD']));
        $this->assertSame(['DOC_IDENTIDAD'], RequisitosDocumentales::faltantes(RequisitosDocumentales::identificacion('PersonaHumana'), ['RECIBO_SUELDO']));
        $this->assertSame(['PODER_REPRESENTACION'], RequisitosDocumentales::faltantes(RequisitosDocumentales::identificacion('PersonaJuridica'), ['CONST_CUIT', 'DOC_IDENTIDAD']));
    }

    public function testDocumentacionSugeridaPorCategoria(): void
    {
        $this->assertSame(['DOC_IDENTIDAD', 'RECIBO_SUELDO'], RequisitosDocumentales::sugeridosIniciales('RelacionDependencia'));
        $this->assertSame(['PAGOS_MONOTRIBUTO'], RequisitosDocumentales::faltantes(RequisitosDocumentales::sugeridosIniciales('Monotributista'), ['DOC_IDENTIDAD', 'CONST_MONOTRIBUTO']));
        $this->assertCount(5, RequisitosDocumentales::faltantes(RequisitosDocumentales::sugeridosIniciales('Empresa'), []));
    }

    public function testLaLineaDefineLoExigidoEnLaSolicitud(): void
    {
        // Línea «solo con DNI»: al cliente en relación de dependencia no se le piden recibos
        $express = [['codigo' => 'DOC_IDENTIDAD', 'categoria_ingresos' => 'RelacionDependencia', 'obligatorio' => 1]];
        $this->assertSame(['DOC_IDENTIDAD'], RequisitosDocumentales::exigidosPorLinea($express, 'RelacionDependencia'));
        // Línea con recibos: se exigen; una fila sin categoría aplica a todas y una opcional no se exige
        $motos = [
            ['codigo' => 'DOC_IDENTIDAD', 'categoria_ingresos' => null, 'obligatorio' => 1],
            ['codigo' => 'RECIBO_SUELDO', 'categoria_ingresos' => 'RelacionDependencia', 'obligatorio' => 1],
            ['codigo' => 'CONST_MONOTRIBUTO', 'categoria_ingresos' => 'Monotributista', 'obligatorio' => 1],
            ['codigo' => 'DDJJ_GANANCIAS', 'categoria_ingresos' => 'RelacionDependencia', 'obligatorio' => 0],
        ];
        $exigidos = RequisitosDocumentales::exigidosPorLinea($motos, 'RelacionDependencia');
        $this->assertSame(['DOC_IDENTIDAD', 'RECIBO_SUELDO'], $exigidos);
        $this->assertSame(['RECIBO_SUELDO'], RequisitosDocumentales::faltantes($exigidos, ['DOC_IDENTIDAD']));
    }

    public function testArchivosDelLegajo(): void
    {
        $this->assertNull(ArchivoLegajo::validar('dni.pdf', 120000, 'application/pdf'));
        $this->assertSame('El archivo recibo.pdf supera los 5 MB.', ArchivoLegajo::validar('recibo.pdf', 5 * 1024 * 1024 + 1, 'application/pdf'));
        $this->assertSame('El archivo planilla.xlsx no es PDF, JPG ni PNG.', ArchivoLegajo::validar('planilla.xlsx', 1000, 'application/vnd.ms-excel'));
        $this->assertSame('El archivo foto.jpg está vacío.', ArchivoLegajo::validar('foto.jpg', 0, 'image/jpeg'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}\.png$/', ArchivoLegajo::nombreAleatorio('image/png'));
        $this->assertSame('recibo.pdf', ArchivoLegajo::nombreSeguro('C:\\Users\\x\\..\\recibo.pdf'));
        $this->assertSame('passwd', ArchivoLegajo::nombreSeguro('../../etc/passwd'));
    }

    public function testCamposNuevos(): void
    {
        $this->assertSame('1234567.89', Campos::importe('1.234.567,89'));
        $this->assertSame('0.50', Campos::importe('0,5'));
        $this->assertNull(Campos::importe('12,345'));
        $this->assertSame('1990-12-31', Campos::fecha('31/12/1990'));
        $this->assertNull(Campos::fecha('1990-02-30'));
        $this->assertTrue(Campos::cuilCorrespondeADni('27301112224', '30111222'));
        $this->assertTrue(Campos::cuilCorrespondeADni('20055512343', '5551234'));
    }

    public function testDesactivacionDelCliente(): void
    {
        $nada = ['cuotas' => 0, 'solicitudes' => 0, 'propuestas' => 0, 'saldos' => 0, 'ordenes' => 0, 'ofertas' => 0];
        $this->assertSame([], ReglasClientes::motivosContraDesactivar('Activo', $nada));
        $this->assertSame(['El cliente ya está desactivado.'], ReglasClientes::motivosContraDesactivar('Desactivado', $nada));
        $this->assertSame(
            ['3 cuotas pendientes de pago', '1 solicitud de crédito en curso', '1 oferta de campaña vigente'],
            ReglasClientes::motivosContraDesactivar('Activo', [...$nada, 'cuotas' => 3, 'solicitudes' => 1, 'ofertas' => 1]),
        );
    }

    public function testCambiosRelevantes(): void
    {
        $antes = ['ingresos_declarados' => '850000.00', 'telefono' => '351', 'categoria_ingresos' => 'RelacionDependencia'];
        $this->assertSame([], ReglasClientes::cambiosRelevantes($antes, [...$antes, 'telefono' => '3515']));
        $this->assertSame(['ingresos_declarados'], ReglasClientes::cambiosRelevantes($antes, [...$antes, 'ingresos_declarados' => '900000.00']));
    }

    /** D-25: la documentación propuesta incluye la identificación y solo tipos activos que corresponden. */
    public function testDocumentacionSugeridaConfigurable(): void
    {
        $aplica = ['DOC_IDENTIDAD' => 'Ambos', 'RECIBO_SUELDO' => 'PersonaHumana', 'CERT_SERVICIOS' => 'PersonaHumana', 'CONST_CUIT' => 'PersonaJuridica', 'PODER_REPRESENTACION' => 'PersonaJuridica'];
        $base = array_map(static fn () => ['DOC_IDENTIDAD'], RequisitosDocumentales::CATEGORIAS['PersonaHumana']) + ['Empresa' => ['CONST_CUIT', 'DOC_IDENTIDAD', 'PODER_REPRESENTACION']];
        $this->assertSame([], RequisitosDocumentales::validarSugeridos(['RelacionDependencia' => ['DOC_IDENTIDAD', 'RECIBO_SUELDO', 'CERT_SERVICIOS']] + $base, $aplica));
        $e = RequisitosDocumentales::validarSugeridos(['RelacionDependencia' => ['RECIBO_SUELDO'], 'Monotributista' => ['DOC_IDENTIDAD', 'CONST_CUIT']] + $base, $aplica);
        $this->assertStringContainsString('identificación', $e['RelacionDependencia']);
        $this->assertStringContainsString('no corresponden', $e['Monotributista']);
        $this->assertSame('CERTIFICADO_DE_SERVICIOS_DEL_E', RequisitosDocumentales::codigoPara('Certificado de servicios del empleador'));
        $this->assertSame('CONSTANCIA_DE_CBU_N_1', RequisitosDocumentales::codigoPara('Constancia de CBU (n.º 1)'));
    }

    /** D-26: categoría del monotributo obligatoria y consistente con los ingresos declarados. */
    public function testCategoriaDelMonotributo(): void
    {
        $escala = ['A' => ['tope' => '12009410.45', 'vigente_desde' => '2026-08-01'], 'B' => ['tope' => '17595182.74', 'vigente_desde' => '2026-08-01']];
        $this->assertNull(CategoriasMonotributo::inconsistencia('A', '1000000.00', $escala));
        $this->assertStringContainsString('superan el tope de la categoría A', (string) CategoriasMonotributo::inconsistencia('A', '1200000.00', $escala));
        $this->assertNull(CategoriasMonotributo::inconsistencia('B', '1200000.00', $escala));
        $this->assertNotNull(CategoriasMonotributo::inconsistencia('K', '100.00', $escala));
        $v = CategoriasMonotributo::validarEscala(['A' => '100,00', 'B' => '90,00'] + array_fill_keys(['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'], ''));
        $this->assertStringContainsString('mayor que el de la anterior', $v['errores']['tope_B']);
        $this->assertArrayHasKey('tope_K', $v['errores']);
    }

    /** RN-80 (D-31): nacionalidad, actividad principal y declaración de persona expuesta políticamente. */
    public function testIdentificacionParaLaUif(): void
    {
        $e = $this->errores(['nacionalidad' => '', 'ocupacion' => '', 'persona_expuesta' => ''] + $this->humana());
        $this->assertSame(['nacionalidad', 'ocupacion', 'persona_expuesta'], array_keys(array_intersect_key($e, array_flip(['nacionalidad', 'ocupacion', 'persona_expuesta']))));
        $this->assertArrayHasKey('persona_expuesta', $this->errores(['persona_expuesta' => ''] + $this->juridica()));
        $pep = ValidadorCliente::normalizar(['persona_expuesta' => 'Si'] + $this->humana());
        $this->assertTrue($pep['persona_expuesta']);
        $this->assertFalse(ValidadorCliente::normalizar($this->humana())['persona_expuesta']);

        // Cliente registrado antes de D-31: debe completar los datos antes de solicitar crédito
        $anterior = ['tipo_cliente' => 'PersonaHumana', 'cuit_cuil' => '27301112224', 'nacionalidad' => null, 'ocupacion' => '', 'persona_expuesta' => null];
        $this->assertSame(['nacionalidad', 'actividad laboral o profesional principal', 'declaración de persona expuesta políticamente'], ValidadorCliente::identificacionIncompleta($anterior));
        $this->assertSame([], ValidadorCliente::identificacionIncompleta(['nacionalidad' => 'Argentina', 'ocupacion' => 'Docente', 'persona_expuesta' => 0] + $anterior));
        $this->assertSame(['CUIT/CUIL'], ValidadorCliente::identificacionIncompleta(['tipo_cliente' => 'PersonaJuridica', 'cuit_cuil' => null, 'persona_expuesta' => 1]));
    }
}
