<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashbook;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class RecordEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user instanceof User && $user->isMainAdmin();
    }

    public function rules(): array
    {
        return [
            'shop_id'         => 'required|integer',
            'business_date'   => 'required|date_format:Y-m-d',
            'entry_type_code' => 'required|string',
            'amount'          => 'required|numeric|min:0.01',
            'funding_source'  => 'nullable|string',
            'notes'           => 'nullable|string|max:255',
        ];
    }
}
