<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Warehouse;

use App\Models\WarehouseSale;
use Illuminate\Foundation\Http\FormRequest;

class CancelWarehouseSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $sale = $this->route('warehouseSale');
        if (! $sale instanceof WarehouseSale) {
            return false;
        }

        return $user->can('cancel', $sale);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
