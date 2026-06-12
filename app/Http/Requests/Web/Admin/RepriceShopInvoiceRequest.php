<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RepriceShopInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
