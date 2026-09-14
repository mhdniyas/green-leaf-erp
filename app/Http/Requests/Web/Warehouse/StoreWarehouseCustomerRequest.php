<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Warehouse;

use App\Services\Warehouse\WarehouseSalesAccessService;
use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return WarehouseSalesAccessService::userCanMakeSales($user);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'tax_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}
