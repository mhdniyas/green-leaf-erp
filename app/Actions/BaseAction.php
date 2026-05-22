<?php

namespace App\Actions;

use App\Exceptions\ActionException;
use Exception;
use Illuminate\Support\Facades\DB;

abstract class BaseAction
{
    abstract public function execute();

    protected function transaction(callable $callback)
    {
        try {
            return DB::transaction($callback);
        } catch (Exception $e) {
            throw new ActionException(
                "Action failed: {$e->getMessage()}",
                previous: $e
            );
        }
    }
}
