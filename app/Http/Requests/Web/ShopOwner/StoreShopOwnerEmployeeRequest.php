<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\ShopOwner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShopOwnerEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('shop') && $this->user()?->can('hr.attendance.mark-owned-shop');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'alternate_phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'joined_on' => ['required', 'date'],
            'id_type' => ['required', Rule::in(['aadhaar', 'passport', 'driving_licence', 'voter_id', 'pan', 'other'])],
            'other_id_type' => ['nullable', 'required_if:id_type,other', 'string', 'max:50'],
            'id_number' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photo_data_url' => ['required', 'string'],
            'id_front_data_url' => ['required', 'string'],
            'id_back_data_url' => ['nullable', 'string'],
        ];
    }
}
