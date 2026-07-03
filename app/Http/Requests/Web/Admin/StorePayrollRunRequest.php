<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\PayrollRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StorePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PayrollRun::class);
    }

    public function rules(): array
    {
        return [
            'payroll_month' => ['required', 'date_format:Y-m'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('payroll_month')) {
            return;
        }

        $payrollMonth = Carbon::createFromFormat('Y-m', $this->string('payroll_month')->toString())->startOfMonth();

        $this->merge([
            'period_start' => $payrollMonth->toDateString(),
            'period_end' => $payrollMonth->copy()->endOfMonth()->toDateString(),
        ]);
    }

    public function attributes(): array
    {
        return [
            'payroll_month' => 'payroll month',
        ];
    }
}
