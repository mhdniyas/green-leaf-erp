<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\ShopOwner;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesToPettyCashRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('shop') && $this->user()?->shop_id !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'business_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
