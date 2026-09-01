<?php

namespace App\Support;

use InvalidArgumentException;

class DecimalQuantity
{
    public static function toMilliunits(string $quantity): int
    {
        if (! preg_match('/\A(?<sign>-?)(?<whole>\d{1,12})(?:\.(?<fraction>\d{1,3}))?\z/', $quantity, $parts)) {
            throw new InvalidArgumentException('Inventory quantities must have at most three decimal places.');
        }

        $value = ((int) $parts['whole'] * 1000)
            + (int) str_pad($parts['fraction'] ?? '', 3, '0');

        return $parts['sign'] === '-' ? -$value : $value;
    }

    public static function fromMilliunits(int $quantity): string
    {
        $absoluteQuantity = abs($quantity);

        return ($quantity < 0 ? '-' : '')
            .intdiv($absoluteQuantity, 1000)
            .'.'
            .str_pad((string) ($absoluteQuantity % 1000), 3, '0', STR_PAD_LEFT);
    }
}
