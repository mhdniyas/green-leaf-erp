<?php

namespace App\Exceptions;

use Exception;

class ModelNotFoundException extends Exception
{
    public function __construct(
        string $model = 'Resource',
        int $code = 404,
    ) {
        $message = "{$model} not found";
        parent::__construct($message, $code);
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
