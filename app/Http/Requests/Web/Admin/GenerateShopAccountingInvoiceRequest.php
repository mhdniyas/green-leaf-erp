<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Support\AccountingAccess;
use Illuminate\Foundation\Http\FormRequest;

class GenerateShopAccountingInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AccountingAccess::canGenerateInvoices($this->user());
    }

    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
