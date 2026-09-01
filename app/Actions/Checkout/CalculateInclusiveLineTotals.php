<?php

namespace App\Actions\Checkout;

use OverflowException;

class CalculateInclusiveLineTotals
{
    /**
     * @return array{including_tax_cents: int, excluding_tax_cents: int, tax_cents: int}
     */
    public function handle(int $unitPriceCents, int $quantity, int $taxRateBasisPoints): array
    {
        if ($unitPriceCents < 0 || $quantity < 1 || ! in_array($taxRateBasisPoints, [900, 2100], true)) {
            throw new \InvalidArgumentException('Line totals require a non-negative price, positive quantity, and supported VAT rate.');
        }

        if ($unitPriceCents !== 0 && $quantity > intdiv(PHP_INT_MAX, $unitPriceCents)) {
            throw new OverflowException('Line total exceeds the supported integer range.');
        }

        $includingTaxCents = $unitPriceCents * $quantity;
        $divisor = 10_000 + $taxRateBasisPoints;
        $whole = intdiv($includingTaxCents, $divisor);
        $remainder = $includingTaxCents % $divisor;
        $excludingTaxCents = ($whole * 10_000)
            + intdiv(($remainder * 10_000) + intdiv($divisor, 2), $divisor);

        return [
            'including_tax_cents' => $includingTaxCents,
            'excluding_tax_cents' => $excludingTaxCents,
            'tax_cents' => $includingTaxCents - $excludingTaxCents,
        ];
    }
}
