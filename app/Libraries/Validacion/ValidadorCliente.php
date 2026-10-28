<?php

declare(strict_types=1);

namespace App\Libraries\Validacion;

use App\Libraries\Clientes\RequisitosDocumentales;

/**
 * Datos de un cliente (CU-11 pasos 2 a 5 y A7, A8; CU-12 paso 5; RN-07, RN-08, RN-75). La unicidad del documento,
 * del CUIT/CUIL y del correo la verifica el servicio contra la base.
 */
final class ValidadorCliente
{
    public const ESTADOS_CIVILES = ['Soltero' => 'Soltero/a', 'Casado' => 'Casado/a', 'UnionConvivencial' => 'Unión convivencial', 'Divorciado' => 'Divorciado/a', 'Viudo' => 'Viudo/a'];
    public const EDAD_MINIMA = 18;

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
            'correo' => Campos::correo($d['correo'] ?? ''),
            'telefono' => $t('telefono'),
            'domicilio' => $t('domicilio'),
            'ingresos_texto' => $t('ingresos_declarados'),
            'ingresos_declarados' => Campos::importe($d['ingresos_declarados'] ?? ''),
            'categoria_ingresos' => $tipo === 'PersonaJuridica' ? 'Empresa' : $t('categoria_ingresos'),
            'cuit_cuil' => str_replace(['-', ' ', '.'], '', $t('cuit_cuil')),
            'acepta_politica' => !empty($d['acepta_politica']),
            'acepta_comunicaciones' => !empty($d['acepta_comunicaciones']),
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
            'ocupacion' => $t('ocupacion'),
            'empleador' => $t('empleador'),
        ];
    }

    /**
     * @param array<string, mixed> $d datos normalizados
     * @param bool $alta en el alta se exige aceptar la política de tratamiento de datos (A7)
     * @param string $hoy fecha de negocio aaaa-mm-dd
     * @return array<string, string> campo => mensaje
     */
    public static function validar(array $d, bool $alta, string $hoy): array
    {
        $juridica = $d['tipo_cliente'] === 'PersonaJuridica';
        $r = [];

        if ($juridica) {
            $r['razon_social'] = [Campos::obligatorio($d['razon_social'], 'la razón social'), Campos::largoMaximo($d['razon_social'], 200, 'la razón social')];
            $r['cuit_cuil'] = [Campos::obligatorio($d['cuit_cuil'], 'el CUIT'), Campos::cuit($d['cuit_cuil'])];
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
            $r['cuit_cuil'] = [
                Campos::cuit($d['cuit_cuil']),
                $d['cuit_cuil'] !== '' && $d['tipo_documento'] === 'DNI' && Campos::numeroDocumento('DNI', $d['nro_documento']) === null
                    && !Campos::cuilCorrespondeADni($d['cuit_cuil'], $d['nro_documento']) ? 'El CUIT/CUIL no corresponde al número de documento.' : null,
            ];
            $r['fecha_nacimiento'] = [
                Campos::obligatorio($d['fecha_nacimiento_texto'], 'la fecha de nacimiento'),
                $d['fecha_nacimiento_texto'] !== '' && $d['fecha_nacimiento'] === null ? 'Ingrese una fecha válida (dd/mm/aaaa).' : null,
                $d['fecha_nacimiento'] !== null && $d['fecha_nacimiento'] > $hoy ? 'La fecha de nacimiento no puede ser futura.' : null,
                $d['fecha_nacimiento'] !== null && $d['fecha_nacimiento'] <= $hoy && Campos::edad($d['fecha_nacimiento'], $hoy) < self::EDAD_MINIMA ? 'El cliente debe ser mayor de edad (' . self::EDAD_MINIMA . ' años).' : null,
            ];
            $r['estado_civil'] = [$d['estado_civil'] !== '' && !isset(self::ESTADOS_CIVILES[$d['estado_civil']]) ? 'Seleccione un estado civil válido.' : null];
            $r['ocupacion'] = [Campos::largoMaximo($d['ocupacion'], 100, 'la ocupación')];
            $r['empleador'] = [Campos::largoMaximo($d['empleador'], 150, 'el empleador o actividad')];
        }

        $r['correo'] = [Campos::obligatorio($d['correo'], 'el correo electrónico'), Campos::formatoCorreo($d['correo'])];
        $r['telefono'] = [Campos::telefono($d['telefono'])];
        $r['domicilio'] = [Campos::obligatorio($d['domicilio'], $juridica ? 'el domicilio legal' : 'el domicilio'), Campos::largoMaximo($d['domicilio'], 200, 'el domicilio')];
        $r['ingresos_declarados'] = [
            Campos::obligatorio($d['ingresos_texto'], $juridica ? 'los ingresos netos mensuales promedio' : 'los ingresos declarados'),
            $d['ingresos_texto'] !== '' && $d['ingresos_declarados'] === null ? 'Ingrese un importe válido, por ejemplo 850.000,00.' : null,
        ];
        $r['categoria_ingresos'] = [
            $d['categoria_ingresos'] === '' ? 'Seleccione la categoría de ingresos.' : null,
            $d['categoria_ingresos'] !== '' && !RequisitosDocumentales::categoriaValida($d['tipo_cliente'], $d['categoria_ingresos']) ? 'Seleccione una categoría de ingresos válida.' : null,
        ];
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
}
