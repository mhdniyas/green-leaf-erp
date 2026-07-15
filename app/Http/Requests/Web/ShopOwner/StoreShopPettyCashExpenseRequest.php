<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\ShopOwner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShopPettyCashExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->hasRole('shop')
            && $this->user()->shop_id !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'business_date' => ['required', Rule::date()->todayOrBefore()],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
