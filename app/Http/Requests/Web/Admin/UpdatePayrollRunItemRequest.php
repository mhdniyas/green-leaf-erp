<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollRunItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PayrollRunItem $payrollRunItem */
        $payrollRunItem = $this->route('payrollRunItem');

        return $this->user()->can('create', PayrollRun::class)
            && $payrollRunItem->payrollRun?->status === 'draft';
    }

    public function rules(): array
    {
        return [
            'override_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
