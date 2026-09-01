<?php

namespace App\Exceptions;

use Exception;

class CheckoutConflict extends Exception
{
    /**
     * @param  array<string, mixed>|null  $details
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly ?array $details = null,
    ) {
        parent::__construct($message);
    }
}
