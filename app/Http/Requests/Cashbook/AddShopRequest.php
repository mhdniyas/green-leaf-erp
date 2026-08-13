<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashbook;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AddShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user instanceof User && $user->isMainAdmin();
    }

    public function rules(): array
    {
        return [
            'shop_id'           => 'required|integer|unique:shop_ledger_profiles,shop_id',
            'code'              => 'required|string|max:50',
            'name'              => 'required|string|max:100',
            'ownership_type'    => 'nullable|string|in:client,direct',
            'client_id'         => 'nullable|integer',
            'profile_template'  => 'required|string',
            'copy_from_shop_id' => 'nullable|integer',
        ];
    }
}
