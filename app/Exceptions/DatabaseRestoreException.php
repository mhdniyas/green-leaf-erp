<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class DatabaseRestoreException extends RuntimeException
{
    public function __construct(
        string $message,
        int $code = 422,
        ?Throwable $previous = null,
        public readonly ?string $safetyBackupPath = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
