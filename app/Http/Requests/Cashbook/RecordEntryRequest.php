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
            'entry_type_code' => 'required_without:collection_group_id|string',
            'amount'          => 'required_without:collection_group_id|numeric|min:0.01',
            'funding_source'  => 'nullable|string',
            'notes'           => 'nullable|string|max:255',
            'collection_group_id' => 'nullable|integer|exists:shop_ledger_collection_groups,id',
            'collection_lines' => 'nullable|array',
            'collection_lines.*.entry_type_id' => 'required_with:collection_lines|integer|exists:ledger_entry_types,id',
            'collection_lines.*.amount' => 'required_with:collection_lines|numeric|min:0',
        ];
    }
}
