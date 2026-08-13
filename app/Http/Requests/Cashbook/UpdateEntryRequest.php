<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashbook;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user instanceof User && $user->isMainAdmin();
    }

    public function rules(): array
    {
        return [
            'transaction_id' => 'required|integer|exists:shop_ledger_transactions,id',
            'amount'         => 'required|numeric|min:0.01',
        ];
    }
}
