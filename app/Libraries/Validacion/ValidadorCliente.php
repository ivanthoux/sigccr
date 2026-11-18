<?php

declare(strict_types=1);

namespace App\Libraries\Validacion;

use App\Libraries\Clientes\CatalogoGeografico;
use App\Libraries\Clientes\CategoriasMonotributo;
use App\Libraries\Clientes\RequisitosDocumentales;
use App\Libraries\Finanzas\Decimal;

/**
 * Datos de un cliente (CU-11 pasos 2 a 5 y A7, A8, A11; CU-12 paso 5; RN-07, RN-08, RN-75, RN-80). La unicidad del
 * documento, del CUIT/CUIL y del correo la verifica el servicio contra la base.
 *
 * RN-80 (Resolución UIF 200/2024): la persona humana informa su nacionalidad y su actividad laboral o profesional
 * principal, y todo cliente declara si es persona expuesta políticamente. Los clientes registrados antes de D-31
 * completan estos datos en su próxima modificación.
 */
final class ValidadorCliente
{
    public const ESTADOS_CIVILES = ['Soltero' => 'Soltero/a', 'Casado' => 'Casado/a', 'UnionConvivencial' => 'Unión convivencial', 'Divorciado' => 'Divorciado/a', 'Viudo' => 'Viudo/a'];
    public const EDAD_MINIMA = 18;

    /** O-06 (D-52): edad máxima admisible, para detectar errores de carga en la fecha de nacimiento. */
    public const EDAD_MAXIMA = 110;

    public const TIPOS_CLIENTE = ['PersonaHumana', 'PersonaJuridica'];

    /**
     * @param array<string, mixed> $d
     * @return array<string, mixed>
     */
    public static function normalizar(array $d): array
    {
        $t = static fn (string $k): string => Campos::texto($d[$k] ?? '');
        $tipo = $t('tipo_cliente') === 'PersonaJuridica' ? 'PersonaJuridica' : 'PersonaHumana';
        $comunes = [
            'tipo_cliente' => $tipo,
            // O-02 (D-52): un tipo fuera de la lista se rechaza; el valor normalizado solo elige las reglas a aplicar
            'tipo_cliente_valido' => in_array($t('tipo_cliente'), self::TIPOS_CLIENTE, true),
            'correo' => Campos::correo($d['correo'] ?? ''),
            'telefono' => $t('telefono'),
            'domicilio' => $t('domicilio'),
            'provincia_id' => ctype_digit($t('provincia_id')) ? (int) $t('provincia_id') : 0,
            'localidad_id' => ctype_digit($t('localidad_id')) ? (int) $t('localidad_id') : 0,
            'codigo_postal_texto' => $t('codigo_postal'),
            'codigo_postal' => CatalogoGeografico::codigoPostal($t('codigo_postal')),
            'ingresos_texto' => $t('ingresos_declarados'),
            'ingresos_declarados' => Campos::importe($d['ingresos_declarados'] ?? ''),
            'categoria_ingresos' => $tipo === 'PersonaJuridica' ? 'Empresa' : $t('categoria_ingresos'),
            'categoria_monotributo' => $tipo === 'PersonaHumana' && $t('categoria_ingresos') === 'Monotributista' ? strtoupper($t('categoria_monotributo')) : null,
            'cuit_cuil' => str_replace(['-', ' ', '.'], '', $t('cuit_cuil')),
            'acepta_politica' => !empty($d['acepta_politica']),
            'acepta_comunicaciones' => !empty($d['acepta_comunicaciones']),
            'persona_expuesta' => match ($t('persona_expuesta')) {
                'Si' => true,
                'No' => false,
                default => null,
            },
        ];
        if ($tipo === 'PersonaJuridica') {
            return $comunes + [
                'razon_social' => $t('razon_social'),
                'fecha_constitucion_texto' => $t('fecha_constitucion'),
                'fecha_constitucion' => Campos::fecha($d['fecha_constitucion'] ?? ''),
                'actividad_principal' => $t('actividad_principal'),
                'representante_nombre' => $t('representante_nombre'),
                'representante_documento' => Campos::documento($d['representante_documento'] ?? ''),
                'representante_cargo' => $t('representante_cargo'),
            ];
        }

        return $comunes + [
            'nombre' => $t('nombre'),
            'apellido' => $t('apellido'),
            'tipo_documento' => $t('tipo_documento'),
            'nro_documento' => Campos::documento($d['nro_documento'] ?? ''),
            'fecha_nacimiento_texto' => $t('fecha_nacimiento'),
            'fecha_nacimiento' => Campos::fecha($d['fecha_nacimiento'] ?? ''),
            'estado_civil' => $t('estado_civil'),
            'nacionalidad' => $t('nacionalidad'),
            'ocupacion' => $t('ocupacion'),
            'empleador' => $t('empleador'),
        ];
    }

    /**
     * @param array<string, mixed> $d datos normalizados
     * @param bool $alta en el alta se exige aceptar la política de tratamiento de datos (A7)
     * @param string $hoy fecha de negocio aaaa-mm-dd
     * @param array<string, array{tope: string, vigente_desde: string}> $escalaMonotributo topes vigentes (D-26)
     * @return array<string, string> campo => mensaje
     */
    public static function validar(array $d, bool $alta, string $hoy, array $escalaMonotributo = []): array
    {
        $juridica = $d['tipo_cliente'] === 'PersonaJuridica';
        $r = ['tipo_cliente' => [($d['tipo_cliente_valido'] ?? true) ? null : 'Seleccione el tipo de cliente.']];

        if ($juridica) {
            $r['razon_social'] = [Campos::obligatorio($d['razon_social'], 'la razón social'), Campos::largoMaximo($d['razon_social'], 200, 'la razón social')];
            $r['cuit_cuil'] = [Campos::obligatorio($d['cuit_cuil'], 'el CUIT'), Campos::cuit($d['cuit_cuil'], 'PersonaJuridica')];
            $r['fecha_constitucion'] = [
                Campos::obligatorio($d['fecha_constitucion_texto'], 'la fecha de constitución'),
                $d['fecha_constitucion_texto'] !== '' && $d['fecha_constitucion'] === null ? 'Ingrese una fecha válida (dd/mm/aaaa).' : null,
                $d['fecha_constitucion'] !== null && $d['fecha_constitucion'] > $hoy ? 'La fecha de constitución no puede ser futura.' : null,
            ];
            $r['actividad_principal'] = [Campos::obligatorio($d['actividad_principal'], 'la actividad principal'), Campos::largoMaximo($d['actividad_principal'], 150, 'la actividad principal')];
            $r['representante_nombre'] = [Campos::obligatorio($d['representante_nombre'], 'el nombre del representante'), Campos::nombrePersona($d['representante_nombre'], 'el nombre del representante')];
            $r['representante_documento'] = [Campos::obligatorio($d['representante_documento'], 'el documento del representante'), Campos::numeroDocumento('DNI', $d['representante_documento'])];
            $r['representante_cargo'] = [Campos::obligatorio($d['representante_cargo'], 'el cargo del representante'), Campos::largoMaximo($d['representante_cargo'], 80, 'el cargo')];
        } else {
            $r['nombre'] = [Campos::obligatorio($d['nombre'], 'el nombre'), Campos::nombrePersona($d['nombre'], 'el nombre')];
            $r['apellido'] = [Campos::obligatorio($d['apellido'], 'el apellido'), Campos::nombrePersona($d['apellido'], 'el apellido')];
            $r['tipo_documento'] = [Campos::obligatorio($d['tipo_documento'], 'el tipo de documento'), Campos::tipoDocumento($d['tipo_documento'])];
            $r['nro_documento'] = [Campos::obligatorio($d['nro_documento'], 'el número de documento'), Campos::numeroDocumento($d['tipo_documento'], $d['nro_documento'])];
            // O-04 (D-52): el CUIL es obligatorio en el alta; los clientes anteriores lo completan al solicitar un crédito
            $r['cuit_cuil'] = [
                $alta ? Campos::obligatorio($d['cuit_cuil'], 'el CUIL o CUIT') : null,
                Campos::cuit($d['cuit_cuil'], 'PersonaHumana'),
                $d['cuit_cuil'] !== '' && $d['tipo_documento'] === 'DNI' && Campos::numeroDocumento('DNI', $d['nro_documento']) === null
                    && !Campos::cuilCorrespondeADni($d['cuit_cuil'], $d['nro_documento']) ? 'El CUIT/CUIL no corresponde al número de documento.' : null,
            ];
            $r['fecha_nacimiento'] = [
                Campos::obligatorio($d['fecha_nacimiento_texto'], 'la fecha de nacimiento'),
                $d['fecha_nacimiento_texto'] !== '' && $d['fecha_nacimiento'] === null ? 'Ingrese una fecha válida (dd/mm/aaaa).' : null,
                $d['fecha_nacimiento'] !== null && $d['fecha_nacimiento'] > $hoy ? 'La fecha de nacimiento no puede ser futura.' : null,
                $d['fecha_nacimiento'] !== null && $d['fecha_nacimiento'] <= $hoy && Campos::edad($d['fecha_nacimiento'], $hoy) < self::EDAD_MINIMA ? 'El cliente debe ser mayor de edad (' . self::EDAD_MINIMA . ' años).' : null,
                $d['fecha_nacimiento'] !== null && $d['fecha_nacimiento'] <= $hoy && Campos::edad($d['fecha_nacimiento'], $hoy) > self::EDAD_MAXIMA ? 'Revise la fecha de nacimiento: la edad no puede superar ' . self::EDAD_MAXIMA . ' años.' : null,
            ];
            $r['estado_civil'] = [$d['estado_civil'] !== '' && !isset(self::ESTADOS_CIVILES[$d['estado_civil']]) ? 'Seleccione un estado civil válido.' : null];
            $r['nacionalidad'] = [Campos::obligatorio($d['nacionalidad'], 'la nacionalidad'), Campos::largoMaximo($d['nacionalidad'], 60, 'la nacionalidad')];
            $r['ocupacion'] = [Campos::obligatorio($d['ocupacion'], 'la actividad laboral o profesional principal'), Campos::largoMaximo($d['ocupacion'], 100, 'la actividad laboral o profesional')];
            $r['empleador'] = [Campos::largoMaximo($d['empleador'], 150, 'el empleador o actividad')];
        }

        $r['correo'] = [Campos::obligatorio($d['correo'], 'el correo electrónico'), Campos::formatoCorreo($d['correo'])];
        $r['telefono'] = [Campos::telefono($d['telefono'])];
        $r['domicilio'] = [Campos::obligatorio($d['domicilio'], $juridica ? 'la calle y el número del domicilio legal' : 'la calle y el número del domicilio'), Campos::largoMaximo($d['domicilio'], 200, 'el domicilio')];
        // D-18: provincia y localidad del domicilio; que la localidad pertenezca a la provincia lo verifica el servicio
        $r['provincia_id'] = [$d['provincia_id'] === 0 ? 'Seleccione la provincia.' : null];
        $r['localidad_id'] = [$d['provincia_id'] !== 0 && $d['localidad_id'] === 0 ? 'Seleccione la localidad.' : null];
        $r['codigo_postal'] = [$d['codigo_postal_texto'] !== '' && $d['codigo_postal'] === null ? 'Ingrese un código postal de 4 dígitos o el CPA de 8 caracteres (por ejemplo, N3350ABC).' : null];
        $r['ingresos_declarados'] = [
            Campos::obligatorio($d['ingresos_texto'], $juridica ? 'los ingresos netos mensuales promedio' : 'los ingresos declarados'),
            $d['ingresos_texto'] !== '' && $d['ingresos_declarados'] === null ? 'Ingrese un importe válido, por ejemplo 850.000,00.' : null,
            // O-07 (D-52)
            $d['ingresos_declarados'] !== null && !Decimal::of((string) $d['ingresos_declarados'])->isPositive() ? 'Los ingresos declarados deben ser mayores que cero.' : null,
        ];
        $r['categoria_ingresos'] = [
            $d['categoria_ingresos'] === '' ? 'Seleccione la categoría de ingresos.' : null,
            $d['categoria_ingresos'] !== '' && !RequisitosDocumentales::categoriaValida($d['tipo_cliente'], $d['categoria_ingresos']) ? 'Seleccione una categoría de ingresos válida.' : null,
        ];
        // D-26: el monotributista informa su categoría, consistente con sus ingresos declarados
        if (($d['categoria_ingresos'] ?? '') === 'Monotributista') {
            $r['categoria_monotributo'] = [
                ($d['categoria_monotributo'] ?? '') === '' ? 'Seleccione la categoría del monotributo.'
                    : ($d['ingresos_declarados'] === null ? null : CategoriasMonotributo::inconsistencia((string) $d['categoria_monotributo'], (string) $d['ingresos_declarados'], $escalaMonotributo)),
            ];
        }
        $r['persona_expuesta'] = [$d['persona_expuesta'] === null ? 'Registre la declaración jurada sobre la condición de persona expuesta políticamente (RN-80).' : null];
        if ($alta) {
            $r['acepta_politica'] = [$d['acepta_politica'] ? null : 'El cliente debe aceptar la política de tratamiento de datos personales.'];
        }

        $errores = [];
        foreach ($r as $campo => $resultados) {
            foreach ($resultados as $m) {
                if ($m !== null) {
                    $errores[$campo] = $m;
                    break;
                }
            }
        }

        return $errores;
    }

    /**
     * Denominación del cliente para búsquedas y listados: "Apellido, Nombre" o la razón social.
     *
     * @param array<string, mixed> $d
     */
    public static function denominacion(array $d): string
    {
        return $d['tipo_cliente'] === 'PersonaJuridica' ? $d['razon_social'] : $d['apellido'] . ', ' . $d['nombre'];
    }

    /**
     * RN-80: datos de identificación que faltan para registrar una solicitud (CU-22). Cubre a los clientes registrados
     * antes de D-31, que completan los datos con CU-12.
     *
     * @param array<string, mixed> $c fila del cliente con su subclase
     * @return list<string>
     */
    public static function identificacionIncompleta(array $c): array
    {
        $vacio = static fn ($v): bool => $v === null || trim((string) $v) === '';
        $faltan = [];
        if ($vacio($c['cuit_cuil'] ?? null)) {
            $faltan[] = 'CUIT/CUIL';
        }
        if (($c['tipo_cliente'] ?? '') === 'PersonaHumana') {
            if ($vacio($c['nacionalidad'] ?? null)) {
                $faltan[] = 'nacionalidad';
            }
            if ($vacio($c['ocupacion'] ?? null)) {
                $faltan[] = 'actividad laboral o profesional principal';
            }
        }
        if (($c['persona_expuesta'] ?? null) === null) {
            $faltan[] = 'declaración de persona expuesta políticamente';
        }

        return $faltan;
    }
}
