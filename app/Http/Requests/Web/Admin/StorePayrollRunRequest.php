<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\PayrollRun;
use Illuminate\Foundation\Http\FormRequest;

class StorePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PayrollRun::class);
    }

    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ];
    }
}
