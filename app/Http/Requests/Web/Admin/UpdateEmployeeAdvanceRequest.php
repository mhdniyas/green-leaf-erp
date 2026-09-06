<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\PayrollRun;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PayrollRun::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'requested_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
