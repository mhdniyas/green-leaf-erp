<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebsiteEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'customer_type' => ['nullable', 'string', 'max:100'],
            'required_date' => ['nullable', 'date'],
            'message' => ['required', 'string', 'max:2000'],
            'source_page' => ['required', 'string', Rule::in(['home', 'products'])],
        ];
    }
}
