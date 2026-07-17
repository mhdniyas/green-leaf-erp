<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\PayrollRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractWorkerPaymentRequest extends FormRequest
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
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'worker_name' => ['required', 'string', 'max:255'],
            'work_type' => ['nullable', 'string', 'max:255'],
            'worked_on' => ['required', 'date'],
            'paid_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', Rule::in(['cash', 'bank'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
