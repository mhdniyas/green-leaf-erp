<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopLedgerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCashbookSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isMainAdmin() || $this->user()->hasRole('admin'));
    }

    public function profile(): ShopLedgerProfile
    {
        $shop = (string) $this->route('shop');

        return ShopLedgerProfile::query()->where('enabled', true)
            ->where(function ($query) use ($shop): void {
                $query->where('slug', $shop)->orWhere('uuid', $shop)->orWhere('code', $shop);
                if (ctype_digit($shop)) {
                    $query->orWhere('shop_id', (int) $shop);
                }
            })->firstOrFail();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $profile = $this->profile();
        $relation = $this->route('settlement') === null ? null : ShopCashbookRelation::where('shop_id', $profile->shop_id)->where('public_uuid', $this->route('settlement'))->firstOrFail();

        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('shop_cashbook_relations', 'name')->where('shop_id', $profile->shop_id)->ignore($relation)],
            'enabled' => ['required', 'boolean'],
            'is_company_payable' => ['nullable', 'boolean'],
            'is_net_balance' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*' => ['required', 'array'],
            'items.*.setting_id' => ['nullable', 'required_without_all:items.*.header_group_id,items.*.source_settlement_id', 'integer', Rule::exists('shop_ledger_entry_settings', 'id')->where('shop_id', $profile->shop_id)->where('enabled', true)],
            'items.*.header_group_id' => ['nullable', 'required_without_all:items.*.setting_id,items.*.source_settlement_id', 'integer', Rule::exists('shop_ledger_header_groups', 'id')->where('shop_id', $profile->shop_id)->where('enabled', true)],
            'items.*.source_settlement_id' => ['nullable', 'required_without_all:items.*.setting_id,items.*.header_group_id', 'integer', Rule::exists('shop_cashbook_relations', 'id')->where('shop_id', $profile->shop_id)->where('enabled', true)],
            'items.*.header_mode' => ['nullable', Rule::in(['all_categories', 'categories_and_products', 'tagged_products_only'])],
            'items.*.role' => ['required', Rule::in(['add', 'subtract'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required' => 'Choose at least one category, header group, or settlement for the formula.',
            'items.*.setting_id.exists' => 'Choose an active category belonging to this shop.',
            'items.*.header_group_id.exists' => 'Choose an active header group belonging to this shop.',
            'items.*.source_settlement_id.exists' => 'Choose an active settlement belonging to this shop.',
        ];
    }
}
