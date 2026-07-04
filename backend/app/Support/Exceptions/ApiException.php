<?php

namespace App\Support\Exceptions;

use Exception;

class ApiException extends Exception
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 400,
        public readonly ?string $errorCode = null,
        public readonly mixed $errors = null,
    ) {
        parent::__construct($message, $statusCode);
    }
}