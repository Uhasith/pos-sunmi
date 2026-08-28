<?php

namespace App\Enums;

enum InventoryTransactionType: string
{
    case OpeningBalance = 'opening_balance';
    case ManualAdjustment = 'manual_adjustment';
}
