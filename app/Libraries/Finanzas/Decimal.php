<?php

declare(strict_types=1);

namespace App\Libraries\Finanzas;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Número decimal inmutable de precisión arbitraria basado en BCMath (RNF-15).
 *
 * - Nunca se construye a partir de float: solo de cadenas o enteros.
 * - Las operaciones intermedias trabajan con ESCALA decimales (30), por encima de los
 *   28 dígitos significativos que exige RN-50.
 * - BCMath trunca; el redondeo a la mitad hacia arriba (alejándose del cero) se implementa en round().
 */
final class Decimal implements JsonSerializable, Stringable
{
    public const ESCALA = 30;

    private string $valor;

    private function __construct(string $valor)
    {
        $this->valor = $valor;
    }

    public static function of(string|int|self $valor): self
    {
        if ($valor instanceof self) {
            return $valor;
        }
        $txt = trim((string) $valor);
        if (!preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)$/', $txt)) {
            throw new InvalidArgumentException("Valor decimal inválido: '{$txt}'");
        }
        // BCMath no acepta ".5" ni "5.": se completa el dígito faltante.
        $txt = preg_replace(['/^([+-]?)\./', '/\.$/'], ['${1}0.', ''], $txt);

        return new self(self::normalizar(bcadd($txt, '0', self::ESCALA)));
    }

    public static function zero(): self
    {
        return new self('0');
    }

    public static function one(): self
    {
        return new self('1');
    }

    // ------------------------------------------------------------------ aritmética

    public function add(string|int|self $o): self
    {
        return new self(self::normalizar(bcadd($this->valor, self::of($o)->valor, self::ESCALA)));
    }

    public function sub(string|int|self $o): self
    {
        return new self(self::normalizar(bcsub($this->valor, self::of($o)->valor, self::ESCALA)));
    }

    public function mul(string|int|self $o): self
    {
        return new self(self::normalizar(bcmul($this->valor, self::of($o)->valor, self::ESCALA)));
    }

    public function div(string|int|self $o): self
    {
        $d = self::of($o);
        if ($d->isZero()) {
            throw new InvalidArgumentException('División por cero.');
        }

        return new self(self::normalizar(bcdiv($this->valor, $d->valor, self::ESCALA)));
    }

    /** Potencia entera (positiva o negativa). */
    public function pow(int $exp): self
    {
        if ($exp >= 0) {
            return new self(self::normalizar(bcpow($this->valor, (string) $exp, self::ESCALA)));
        }

        return self::one()->div($this->pow(-$exp));
    }

    public function neg(): self
    {
        return self::zero()->sub($this);
    }

    public function abs(): self
    {
        return $this->isNegative() ? $this->neg() : $this;
    }

    // ------------------------------------------------------------------ redondeo

    /** Redondeo a la mitad hacia arriba (half up, alejándose del cero) a $decimales. */
    public function round(int $decimales = 2): self
    {
        $mitad = '0.' . str_repeat('0', $decimales) . '5';
        $ajustado = $this->isNegative()
            ? bcsub($this->valor, $mitad, self::ESCALA)
            : bcadd($this->valor, $mitad, self::ESCALA);

        return new self(self::normalizar(bcadd($ajustado, '0', $decimales)));
    }

    /** Truncamiento hacia cero a $decimales. */
    public function truncate(int $decimales = 2): self
    {
        return new self(self::normalizar(bcadd($this->valor, '0', $decimales)));
    }

    /** Mayor múltiplo de $paso que no supera el valor (para valores no negativos). */
    public function floorToMultiple(string|int|self $paso): self
    {
        $p = self::of($paso);
        if ($p->compare(0) <= 0) {
            throw new InvalidArgumentException('El paso debe ser positivo.');
        }
        $cociente = bcdiv($this->valor, $p->valor, 0);

        return self::of($cociente)->mul($p);
    }

    // ------------------------------------------------------------------ comparación

    public function compare(string|int|self $o): int
    {
        return bccomp($this->valor, self::of($o)->valor, self::ESCALA);
    }

    public function equals(string|int|self $o): bool
    {
        return $this->compare($o) === 0;
    }

    public function isZero(): bool
    {
        return $this->compare(0) === 0;
    }

    public function isNegative(): bool
    {
        return $this->compare(0) < 0;
    }

    public function isPositive(): bool
    {
        return $this->compare(0) > 0;
    }

    public static function min(self $a, self $b): self
    {
        return $a->compare($b) <= 0 ? $a : $b;
    }

    public static function max(self $a, self $b): self
    {
        return $a->compare($b) >= 0 ? $a : $b;
    }

    /** @param iterable<self|string|int> $valores */
    public static function sum(iterable $valores): self
    {
        $t = self::zero();
        foreach ($valores as $v) {
            $t = $t->add($v);
        }

        return $t;
    }

    // ------------------------------------------------------------------ presentación

    /** Representación con exactamente $decimales (redondeando a la mitad hacia arriba). */
    public function toFixed(int $decimales = 2): string
    {
        $r = $this->round($decimales)->valor;
        if ($decimales === 0) {
            return $r;
        }
        [$e, $f] = array_pad(explode('.', $r, 2), 2, '');

        return $e . '.' . str_pad($f, $decimales, '0');
    }

    /** Porcentaje con dos decimales: 0.795856 -> "79.59". */
    public function toPercent(int $decimales = 2): string
    {
        return $this->mul(100)->toFixed($decimales);
    }

    public function __toString(): string
    {
        return $this->valor;
    }

    public function jsonSerialize(): string
    {
        return $this->valor;
    }

    private static function normalizar(string $v): string
    {
        if (str_contains($v, '.')) {
            $v = rtrim(rtrim($v, '0'), '.');
        }
        if ($v === '-0' || $v === '' || $v === '-') {
            return '0';
        }

        return $v;
    }
}
