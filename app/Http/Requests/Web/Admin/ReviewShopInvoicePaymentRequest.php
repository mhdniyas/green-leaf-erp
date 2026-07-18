<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Support\AccountingAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewShopInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AccountingAccess::canViewDashboard($this->user());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
