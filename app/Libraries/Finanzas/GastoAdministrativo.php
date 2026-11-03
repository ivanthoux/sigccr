<?php

declare(strict_types=1);

namespace App\Libraries\Finanzas;

use InvalidArgumentException;

/**
 * Gasto administrativo por cuota de una línea (RN-68, D-28): un importe fijo o un porcentaje del capital otorgado.
 * En los dos casos es el mismo en todas las cuotas, de modo que la cuota del sistema francés se mantiene constante, y
 * lleva IVA.
 */
final class GastoAdministrativo
{
    public const TIPOS = ['Fijo' => 'Importe fijo por cuota', 'Porcentual' => 'Porcentaje del capital en cada cuota'];

    private function __construct(public readonly string $tipo, public readonly Decimal $valor)
    {
        if (!isset(self::TIPOS[$tipo])) {
            throw new InvalidArgumentException("Tipo de gasto administrativo desconocido: {$tipo}.");
        }
        if ($valor->isNegative() || ($tipo === 'Porcentual' && $valor->compare(1) >= 0)) {
            throw new InvalidArgumentException('El gasto administrativo debe ser no negativo y, si es porcentual, menor que el 100 %.');
        }
    }

    public static function fijo(Decimal|string $importe): self
    {
        return new self('Fijo', $importe instanceof Decimal ? $importe : Decimal::of($importe));
    }

    public static function porcentual(Decimal|string $proporcion): self
    {
        return new self('Porcentual', $proporcion instanceof Decimal ? $proporcion : Decimal::of($proporcion));
    }

    /**
     * Desde una fila de versiones_condiciones (o un arreglo con las mismas claves). Las filas anteriores a D-28 no
     * tienen tipo y son de importe fijo.
     *
     * @param array<string, mixed> $version gasto_administrativo_cuota, tipo_gasto_administrativo, gasto_administrativo_pct
     */
    public static function deVersion(array $version): self
    {
        return ($version['tipo_gasto_administrativo'] ?? 'Fijo') === 'Porcentual'
            ? self::porcentual((string) $version['gasto_administrativo_pct'])
            : self::fijo((string) ($version['gasto_administrativo_cuota'] ?? '0'));
    }

    public static function de(Decimal|self $gasto): self
    {
        return $gasto instanceof self ? $gasto : self::fijo($gasto);
    }

    /** Importe del gasto en cada cuota, sin IVA, para el capital otorgado. */
    public function porCuota(Decimal $capital): Decimal
    {
        return $this->tipo === 'Fijo' ? $this->valor->round(2) : $capital->mul($this->valor)->round(2);
    }

    /** Parte fija del gasto por cuota (para el capital máximo). */
    public function parteFija(): Decimal
    {
        return $this->tipo === 'Fijo' ? $this->valor : Decimal::zero();
    }

    /** Proporción del capital que se cobra en cada cuota (para el capital máximo). */
    public function proporcion(): Decimal
    {
        return $this->tipo === 'Porcentual' ? $this->valor : Decimal::zero();
    }
}
