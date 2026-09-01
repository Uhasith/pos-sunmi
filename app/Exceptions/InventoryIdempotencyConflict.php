<?php

namespace App\Exceptions;

use DomainException;
use Illuminate\Contracts\Debug\ShouldntReport;

class InventoryIdempotencyConflict extends DomainException implements ShouldntReport {}
