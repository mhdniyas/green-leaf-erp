<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class EmptyWarehouseStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'business_date' => ['required', 'date', 'before_or_equal:today'],
            'confirmation_code' => ['required', 'string', 'size:6'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('business_date') || ! $this->filled('confirmation_code')) {
                return;
            }

            $expectedCode = Carbon::parse($this->input('business_date'))->format('dmy');

            if ($this->input('confirmation_code') !== $expectedCode) {
                $validator->errors()->add('confirmation_code', 'Enter the date verification code shown in the confirmation prompt.');
            }
        }];
    }
}
