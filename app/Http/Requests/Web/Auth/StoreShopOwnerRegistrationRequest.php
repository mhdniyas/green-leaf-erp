<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Auth;

use Illuminate\Foundation\Http\FormRequest;

class StoreShopOwnerRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phoneDigits = preg_replace('/\D+/', '', (string) $this->input('phone', ''));

        if (str_starts_with($phoneDigits, '91') && strlen($phoneDigits) === 12) {
            $phoneDigits = substr($phoneDigits, 2);
        }

        $this->merge([
            'phone' => $phoneDigits,
        ]);
    }

    public function rules(): array
    {
        return [
            'shop_name' => ['required', 'string', 'min:2', 'max:100'],
            'owner_name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'digits:10'],
            'address' => ['nullable', 'string', 'max:1000'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
