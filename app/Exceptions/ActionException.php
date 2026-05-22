<?php

namespace App\Exceptions;

use Exception;

class ActionException extends Exception
{
    public function __construct(
        string $message = 'Action failed',
        int $code = 422,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function render()
    {
        return response()->json([
            'success' => false,
            'message' => $this->message,
            'errors' => [],
        ], $this->code);
    }
}
