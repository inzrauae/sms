<?php

namespace App\Exceptions;

use Exception;

class TextLkException extends Exception
{
    public ?int $statusCode;
    public mixed $body;

    public function __construct(string $message, ?int $statusCode = null, mixed $body = null)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->body = $body;
    }
}
