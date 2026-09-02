<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class InvalidWooCommerceWebhook extends Exception implements ShouldntReport {}
