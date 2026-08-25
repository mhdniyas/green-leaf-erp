<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\Cashbook\CompanyAccount;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePayrollPaymentRequest extends FormRequest
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
            'payroll_run_item_id' => ['required', 'integer', 'exists:payroll_run_items,id'],
            'payment_type' => ['required', 'string', Rule::in(['full', 'partial', 'custom'])],
            'payment_method' => ['required', 'string', Rule::in(['cash', 'bank'])],
            'company_account_uuid' => ['required', 'uuid', 'exists:cashbook_company_accounts,public_uuid'],
            'request_uuid' => ['required', 'uuid'],
            'reference' => ['nullable', 'string', 'max:160'],
            'paid_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $item = PayrollRunItem::query()
                    ->with('payments')
                    ->find($this->integer('payroll_run_item_id'));

                if (! $item instanceof PayrollRunItem) {
                    return;
                }

                $amount = (float) $this->input('amount', 0);
                $remainingAmount = $item->remainingGreenLeafAmount();

                if ($amount > $remainingAmount) {
                    $validator->errors()->add('amount', 'The payment amount cannot be more than the remaining Green Leaf salary.');
                }

                $companyAccount = CompanyAccount::query()
                    ->where('public_uuid', $this->string('company_account_uuid')->toString())
                    ->first();

                if (! $companyAccount instanceof CompanyAccount || ! $companyAccount->enabled || ! in_array($companyAccount->account_type, ['cash', 'bank'], true)) {
                    $validator->errors()->add('company_account_uuid', 'Select an enabled company cash or bank account.');

                    return;
                }

                if ($companyAccount->account_type !== $this->string('payment_method')->toString()) {
                    $validator->errors()->add('payment_method', 'Payment method must match the selected company account type.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'payroll_run_item_id' => 'payroll item',
            'company_account_uuid' => 'company account',
            'request_uuid' => 'payment request',
            'paid_on' => 'payment date',
        ];
    }
}
