<?php

namespace App\Exceptions;

use Exception;

class DispatchException extends Exception
{
    public $code;
    public array $extra;

    public function __construct(
        string $message,
        string $code = 'SEND_FAILED',
        array $extra = [],
    ) {
        parent::__construct($message);
        $this->code = $code;
        $this->extra = $extra;
    }
}
