<?php

declare(strict_types=1);

namespace App\Libraries\Clientes;

/**
 * Reglas del padrón sin acceso a la base: el servicio reúne los datos y estas funciones deciden.
 */
final class ReglasClientes
{
    /** Estados finales de una solicitud (3.5.3). */
    public const SOLICITUD_FINAL = ['Liquidada', 'Rechazada', 'Cancelada', 'Vencida'];

    /** Datos usados en la evaluación crediticia y la estructuración: su cambio afecta solicitudes en curso (RN-12). */
    public const CAMPOS_RELEVANTES = [
        'ingresos_declarados', 'categoria_ingresos', 'cuit_cuil', 'tipo_documento', 'nro_documento', 'fecha_nacimiento',
        'empleador', 'ocupacion', 'fecha_constitucion', 'actividad_principal',
    ];

    /**
     * CU-13 paso 3 (RN-09): motivos que impiden desactivar al cliente.
     *
     * @param array{cuotas: int, solicitudes: int, propuestas: int, saldos: int, ordenes: int, ofertas: int} $pendientes
     * @return list<string>
     */
    public static function motivosContraDesactivar(string $estado, array $pendientes): array
    {
        if ($estado !== 'Activo') {
            return ['El cliente ya está desactivado.'];
        }
        $textos = [
            'cuotas' => ['cuota pendiente de pago', 'cuotas pendientes de pago'],
            'solicitudes' => ['solicitud de crédito en curso', 'solicitudes de crédito en curso'],
            'propuestas' => ['propuesta de refinanciación en curso', 'propuestas de refinanciación en curso'],
            'saldos' => ['saldo a favor pendiente de aplicación o devolución', 'saldos a favor pendientes de aplicación o devolución'],
            'ordenes' => ['orden de pago electrónico pendiente o en conciliación', 'órdenes de pago electrónico pendientes o en conciliación'],
            'ofertas' => ['oferta de campaña vigente', 'ofertas de campaña vigentes'],
        ];
        $motivos = [];
        foreach ($textos as $clave => [$singular, $plural]) {
            $n = $pendientes[$clave] ?? 0;
            if ($n > 0) {
                $motivos[] = $n . ' ' . ($n === 1 ? $singular : $plural);
            }
        }

        return $motivos;
    }

    /**
     * CU-12 paso 6 (RN-12): campos relevantes que cambiaron.
     *
     * @param array<string, mixed> $antes
     * @param array<string, mixed> $despues
     * @return list<string>
     */
    public static function cambiosRelevantes(array $antes, array $despues): array
    {
        $cambios = [];
        foreach (self::CAMPOS_RELEVANTES as $c) {
            if (array_key_exists($c, $despues) && (string) ($antes[$c] ?? '') !== (string) ($despues[$c] ?? '')) {
                $cambios[] = $c;
            }
        }

        return $cambios;
    }
}
