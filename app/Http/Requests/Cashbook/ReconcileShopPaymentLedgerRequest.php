<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashbook;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ReconcileShopPaymentLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->isMainAdmin();
    }

    public function rules(): array
    {
        return [
            'payment_ref' => ['required', 'string', 'max:2048'],
            'allocations' => ['required', 'array', 'min:1', 'max:100'],
            'allocations.*.ledger_ref' => ['required', 'string', 'max:2048', 'distinct'],
            'allocations.*.amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
