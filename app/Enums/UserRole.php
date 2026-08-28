<?php

namespace App\Enums;

enum UserRole: string
{
    case Cashier = 'cashier';
    case Manager = 'manager';
    case Admin = 'admin';
}
