<?php

declare(strict_types=1);

namespace App\Libraries\Finanzas;

use DateTimeImmutable;

/** Cuota de un plan con sus conceptos discriminados (RN-50, RN-68). */
final class CuotaPlan
{
    public function __construct(
        public readonly int $numero,
        public readonly DateTimeImmutable $vencimiento,
        public readonly Decimal $capital,
        public readonly Decimal $interes,
        public readonly Decimal $gastos,
        public readonly Decimal $iva,
        public readonly Decimal $noCapitalizable,
    ) {
    }

    /** Total de la cuota: suma de los importes exigibles por concepto (RN-50). */
    public function total(): Decimal
    {
        return Decimal::sum([$this->capital, $this->interes, $this->gastos, $this->iva, $this->noCapitalizable]);
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'numero' => (string) $this->numero,
            'vencimiento' => $this->vencimiento->format('Y-m-d'),
            'capital' => $this->capital->toFixed(2),
            'interes' => $this->interes->toFixed(2),
            'gastos' => $this->gastos->toFixed(2),
            'iva' => $this->iva->toFixed(2),
            'no_capitalizable' => $this->noCapitalizable->toFixed(2),
            'total' => $this->total()->toFixed(2),
        ];
    }
}
