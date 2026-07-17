<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

abstract class BaseService
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    protected function logActivity(string $action, ?string $description = null, array $properties = []): void
    {
        activity()
            ->performedOn($this->model)
            ->withProperties(array_merge($properties, [
                'model' => class_basename($this->model),
                'id' => $this->model->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'method' => request()->method(),
                'url' => request()->fullUrl(),
            ]))
            ->log($action);
    }
}
