<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Sales;

use App\Models\Shop;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSalesShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sales.customer.update');
    }

    public function rules(): array
    {
        /** @var Shop|null $shop */
        $shop = $this->route('shop');

        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'code' => ['required', 'string', 'max:20', Rule::unique('shops', 'code')->ignore($shop?->id)],
            'warehouse_tag' => ['nullable', 'string', 'max:12', Rule::unique('shops', 'warehouse_tag')->ignore($shop?->id)],
            'shop_price_group_id' => ['nullable', 'integer', 'exists:shop_price_groups,id'],
            'allow_grade_b_purchase' => ['sometimes', 'boolean'],
            'allowed_product_grades' => ['sometimes', 'nullable', 'array'],
            'allowed_product_grades.*' => ['string', Rule::in(['A', 'B', 'C'])],
            'default_product_grade' => ['sometimes', 'nullable', 'string', Rule::in(['A', 'B', 'C'])],
            'shop_purchasing_enabled' => ['sometimes', 'boolean'],
            'destination_type' => ['required', 'string', 'in:client,direct'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'client_name' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $this->string('destination_type')->toString() === 'client'
                    && ! $this->filled('client_id')
                    && ! $this->filled('client_name')
                ) {
                    $validator->errors()->add('client_id', 'Select a client or enter a new client name.');
                }

                if ($this->has('allowed_product_grades')) {
                    if (! $this->user()?->hasRole('admin')) {
                        $validator->errors()->add('allowed_product_grades', 'Only administrators can configure allowed product grades.');
                    }

                    $rawGrades = (array) $this->input('allowed_product_grades', []);
                    $grades = array_values(array_filter($rawGrades, fn ($g) => filled($g)));
                    if ($grades === []) {
                        $validator->errors()->add('allowed_product_grades', 'At least one product grade must be selected.');
                    }

                    foreach ($grades as $grade) {
                        if (in_array(strtoupper((string) $grade), ['D', 'U', 'DAMAGE', 'UNSORTED'], true)) {
                            $validator->errors()->add('allowed_product_grades', 'Damage and unsorted classifications cannot be configured as orderable grades.');
                        }
                    }

                    if ($this->filled('default_product_grade')) {
                        $defaultGrade = strtoupper(trim((string) $this->input('default_product_grade')));
                        $normalizedGrades = array_map(fn ($g) => strtoupper(trim((string) $g)), $grades);
                        if (! in_array($defaultGrade, $normalizedGrades, true)) {
                            $validator->errors()->add('default_product_grade', 'The default grade must be one of the allowed product grades.');
                        }
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(str_replace([' ', '-'], '_', trim((string) $this->input('code')))),
            'warehouse_tag' => filled($this->input('warehouse_tag')) ? strtoupper(trim((string) $this->input('warehouse_tag'))) : null,
        ]);
    }
}
