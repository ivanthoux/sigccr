<?php

declare(strict_types=1);

namespace App\Libraries\Finanzas;

/** Plan de cuotas resultante de una amortización francesa. */
final class Plan
{
    /** @param list<CuotaPlan> $cuotas */
    public function __construct(
        public readonly Decimal $capital,
        public readonly Decimal $tna,
        public readonly Decimal $cuotaOrdinaria,
        public readonly array $cuotas,
    ) {
    }

    public function totalCapital(): Decimal
    {
        return Decimal::sum(array_map(static fn (CuotaPlan $c) => $c->capital, $this->cuotas));
    }

    public function totalIntereses(): Decimal
    {
        return Decimal::sum(array_map(static fn (CuotaPlan $c) => $c->interes, $this->cuotas));
    }

    public function totalAPagar(): Decimal
    {
        return Decimal::sum(array_map(static fn (CuotaPlan $c) => $c->total(), $this->cuotas));
    }

    /** Cuota total más alta (la primera en un plan francés con gasto fijo). */
    public function cuotaTotalMaxima(): Decimal
    {
        $max = Decimal::zero();
        foreach ($this->cuotas as $c) {
            $max = Decimal::max($max, $c->total());
        }

        return $max;
    }

    /** @return list<Decimal> totales por cuota, para el CFTEA */
    public function totales(): array
    {
        return array_map(static fn (CuotaPlan $c) => $c->total(), $this->cuotas);
    }
}
