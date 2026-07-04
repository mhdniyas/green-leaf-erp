<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\PayrollRun;
use Illuminate\Foundation\Http\FormRequest;

class FinalizePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PayrollRun $payrollRun */
        $payrollRun = $this->route('payrollRun');

        return $this->user()->can('create', PayrollRun::class)
            && $payrollRun->status === 'draft';
    }

    public function rules(): array
    {
        return [];
    }
}
