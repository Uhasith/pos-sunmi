<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class WooCommerceWebhookConflict extends Exception implements ShouldntReport {}
