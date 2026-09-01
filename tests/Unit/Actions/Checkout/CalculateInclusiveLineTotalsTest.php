<?php

use App\Actions\Checkout\CalculateInclusiveLineTotals;

it('extracts inclusive VAT using line-level half-up rounding', function (int $price, int $quantity, int $vat, array $expected) {
    expect(app(CalculateInclusiveLineTotals::class)->handle($price, $quantity, $vat))->toBe($expected);
})->with([
    '9 percent' => [109, 1, 900, ['including_tax_cents' => 109, 'excluding_tax_cents' => 100, 'tax_cents' => 9]],
    '21 percent' => [121, 1, 2100, ['including_tax_cents' => 121, 'excluding_tax_cents' => 100, 'tax_cents' => 21]],
    'just below half cent' => [103, 1, 900, ['including_tax_cents' => 103, 'excluding_tax_cents' => 94, 'tax_cents' => 9]],
    'just above half cent' => [6, 1, 900, ['including_tax_cents' => 6, 'excluding_tax_cents' => 6, 'tax_cents' => 0]],
    'zero price' => [0, 12, 2100, ['including_tax_cents' => 0, 'excluding_tax_cents' => 0, 'tax_cents' => 0]],
]);

it('rejects line-total integer overflow', function () {
    app(CalculateInclusiveLineTotals::class)->handle(PHP_INT_MAX, 2, 2100);
})->throws(OverflowException::class);
