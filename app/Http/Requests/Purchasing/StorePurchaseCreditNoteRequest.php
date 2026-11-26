<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Enums\Inventory\WastageReason;
use App\Enums\Purchasing\PurchaseCreditInventoryAction;
use App\Enums\Purchasing\PurchaseCreditMoneyEffect;
use App\Enums\Purchasing\PurchaseCreditReason;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StorePurchaseCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        return (method_exists($user, 'isMainAdmin') && $user->isMainAdmin())
            || $user->can('cashbook.reconciliation.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $supplier = $this->route('supplier');
        $allowedProductIds = $supplier instanceof Supplier
            ? $supplier->relatedProductIds($this->input('purchase_invoice_id') ? (int) $this->input('purchase_invoice_id') : null)
            : [];

        return [
            'credit_date' => ['required', 'date'],
            'reason' => ['required', new Enum(PurchaseCreditReason::class)],
            'money_effect' => ['required', new Enum(PurchaseCreditMoneyEffect::class)],
            'inventory_action' => ['required', new Enum(PurchaseCreditInventoryAction::class)],
            'purchase_invoice_id' => ['nullable', 'integer', 'exists:purchase_invoices,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id', 'required_if:inventory_action,out,in'],
            'company_account_id' => ['nullable', 'integer', 'exists:cashbook_company_accounts,id', 'required_if:money_effect,in,out'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'deduct_stock_for_damage' => ['nullable', 'boolean'],
            'allow_negative_stock' => ['nullable', 'boolean'],
            'submission_token' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                'exists:products,id',
                Rule::in($allowedProductIds),
            ],
            'items.*.grade' => ['required', 'string', 'max:10'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'items.*.purchaser_cart_item_id' => ['nullable', 'integer'],
            'items.*.wastage_reason' => ['nullable', new Enum(WastageReason::class)],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_account_id.required_if' => 'Company account is required when money effect is Money IN or Money OUT.',
            'warehouse_id.required_if' => 'Warehouse is required when stock is being returned (Stock OUT) or received (Stock IN).',
            'items.required' => 'At least one line item is required.',
            'items.*.product_id.required' => 'Each line must have a valid product selected.',
            'items.*.product_id.in' => 'The selected product is not associated with this vendor.',
            'items.*.quantity.gt' => 'Item quantity must be greater than zero.',
            'items.*.unit_price.gte' => 'Item unit price cannot be negative.',
        ];
    }
}
