<?php

namespace App\Services\Cashbook;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Models\BusinessSetting;
use App\Models\Cashbook\CompanyAccount;
use App\Models\DirectCompanySale;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Services\Finance\JournalService;
use App\Services\Inventory\StockLedgerService;
use App\Services\Pricing\ApprovedDailyPriceResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DirectCompanySaleInventoryService
{
    public function __construct(
        private readonly ApprovedDailyPriceResolver $priceResolver,
        private readonly StockLedgerService $stockLedgerService,
        private readonly JournalService $journalService,
        private readonly CompanyPaymentReconciliationService $reconciliationService,
    ) {}

    /**
     * @param  array{
     *     business_date:string,
     *     customer_name?:string|null,
     *     reference?:string|null,
     *     note?:string|null,
     *     request_uuid:string,payment_method:'cash'|'bank',company_account_uuid?:string|null,
     *     items:array<int, array{product_uuid:string,unit:string,quantity:float|int|string,unit_rate?:float|int|string|null}>
     * }  $input
     */
    public function create(array $input, int $userId): DirectCompanySale
    {
        return DB::transaction(function () use ($input, $userId): DirectCompanySale {
            $existingSale = DirectCompanySale::query()
                ->where('request_uuid', $input['request_uuid'])
                ->lockForUpdate()
                ->first();

            if ($existingSale instanceof DirectCompanySale) {
                return $existingSale->fresh(['shop', 'items.product', 'items.warehouse']);
            }

            $shop = $this->configuredDirectSaleShop();
            $items = $this->resolvedItems($input, $shop);
            $total = round($items->sum('line_total'), 2);
            $companyAccount = $this->paymentAccount($input);

            $sale = DirectCompanySale::query()->create([
                'request_uuid' => $input['request_uuid'],
                'business_date' => $input['business_date'],
                'customer_name' => $input['customer_name'] ?? null,
                'shop_id' => $shop->id,
                'sale_status' => 'confirmed',
                'amount' => $total,
                'payment_method' => $input['payment_method'],
                'company_account_id' => $companyAccount->id,
                'reference' => $input['reference'] ?? null,
                'note' => $input['note'] ?? null,
                'created_by' => $userId,
            ]);

            $items->each(function (array $item) use ($sale, $userId): void {
                $saleItem = $sale->items()->create([
                    'product_id' => $item['product']->id,
                    'warehouse_id' => $item['warehouse_id'],
                    'unit' => $item['unit'],
                    'quantity' => $item['quantity'],
                    'conversion_to_base' => $item['conversion_to_base'],
                    'base_quantity' => $item['base_quantity'],
                    'unit_rate' => $item['unit_rate'],
                    'line_total' => $item['line_total'],
                    'price_source' => $item['price_source'],
                ]);

                $this->stockLedgerService->consumeStockForProductAllowingNegative(
                    (int) $item['product']->id,
                    (float) $item['base_quantity'],
                    $userId,
                    StockMovementType::Out,
                    'Direct company sale '.$sale->public_uuid.' item '.$saleItem->id,
                    null,
                    (int) $item['warehouse_id'],
                    ProductGrade::GradeA,
                );
            });

            $journal = $this->journalService->recordDirectCompanySale($sale, $companyAccount, $userId);
            $sale->update([
                'journal_entry_id' => $journal->id,
                'reconciliation_status' => 'unreconciled',
            ]);

            $this->reconciliationService->createStatementEntry([
                'company_account_id' => $companyAccount->id,
                'journal_entry_id' => $journal->id,
                'request_uuid' => $input['request_uuid'],
                'transaction_date' => $sale->business_date->toDateString(),
                'value_date' => $sale->business_date->toDateString(),
                'direction' => 'in',
                'amount' => $total,
                'reference' => $sale->reference ?: 'DIRECT-SALE-'.$sale->id,
                'narration' => 'Direct company sale'.($sale->customer_name ? ' - '.$sale->customer_name : ''),
                'source' => 'direct_company_sale',
                'source_type' => DirectCompanySale::class,
                'source_id' => $sale->id,
                'notes' => $sale->note,
            ], $userId);

            return $sale->fresh(['companyAccount', 'journalEntry.transactions.account', 'cashbookMovement', 'shop', 'items.product', 'items.warehouse']);
        }, attempts: 3);
    }

    /** @param array{payment_method:'cash'|'bank',company_account_uuid?:string|null} $input */
    private function paymentAccount(array $input): CompanyAccount
    {
        if ($input['payment_method'] === 'cash') {
            $account = CompanyAccount::query()
                ->where('account_type', 'cash')
                ->where('enabled', true)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $account instanceof CompanyAccount) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Configure an enabled Company Cash account before recording a cash direct sale.',
                ]);
            }

            return $account;
        }

        $account = CompanyAccount::query()
            ->where('public_uuid', $input['company_account_uuid'] ?? null)
            ->where('account_type', 'bank')
            ->where('enabled', true)
            ->lockForUpdate()
            ->first();

        if (! $account instanceof CompanyAccount) {
            throw ValidationException::withMessages([
                'company_account_uuid' => 'Select an enabled Company Bank account for this direct sale.',
            ]);
        }

        return $account;
    }

    private function configuredDirectSaleShop(): Shop
    {
        $shopId = (int) (BusinessSetting::query()
            ->where('key', 'default_direct_sale_shop_id')
            ->value('value') ?? 0);

        if ($shopId <= 0) {
            throw ValidationException::withMessages([
                'default_direct_sale_shop_id' => 'Configure Default Direct Sales Shop before creating direct sales.',
            ]);
        }

        $shop = Shop::query()
            ->with('priceGroup')
            ->whereKey($shopId)
            ->where('status', 'active')
            ->first();

        if (! $shop instanceof Shop) {
            throw ValidationException::withMessages([
                'default_direct_sale_shop_id' => 'Configured Default Direct Sales Shop is missing or inactive.',
            ]);
        }

        return $shop;
    }

    /**
     * @param  array{business_date:string,items:array<int, array{product_uuid:string,unit:string,quantity:float|int|string,unit_rate?:float|int|string|null}>}  $input
     * @return Collection<int, array{
     *     product:Product,
     *     warehouse_id:int,
     *     unit:string,
     *     quantity:float,
     *     conversion_to_base:float,
     *     base_quantity:float,
     *     unit_rate:float,
     *     line_total:float,
     *     price_source:string
     * }>
     */
    private function resolvedItems(array $input, Shop $shop): Collection
    {
        $products = Product::query()
            ->with('orderUnits')
            ->whereIn('public_uuid', collect($input['items'])->pluck('product_uuid')->all())
            ->where('is_active', true)
            ->get()
            ->keyBy('public_uuid');

        $missingWarehouses = [];
        $resolved = new Collection;

        foreach ($input['items'] as $index => $row) {
            $product = $products->get($row['product_uuid']);
            if (! $product instanceof Product) {
                throw ValidationException::withMessages(["items.{$index}.product_uuid" => 'Selected product is invalid or inactive.']);
            }

            if (! $product->default_warehouse_id) {
                $missingWarehouses[] = $product->name;

                continue;
            }

            $quantity = round((float) $row['quantity'], 3);
            $selectedUnit = $this->validProductUnit($product, (string) $row['unit'], $index);
            $conversionToBase = (float) $selectedUnit->conversion_to_base;
            $baseQuantity = round($quantity * $conversionToBase, 3);
            $price = $this->priceResolver->resolve($product, $shop, $input['business_date']);
            $priceUnitConversion = $this->conversionForUnit($product, (string) $price['price_unit']);
            $lineTotal = round((float) $price['price'] * ($baseQuantity / $priceUnitConversion), 2);
            $unitRate = round($lineTotal / $quantity, 2);

            if (isset($row['unit_rate']) && $row['unit_rate'] !== null && abs((float) $row['unit_rate'] - $unitRate) > 0.01) {
                throw ValidationException::withMessages(["items.{$index}.unit_rate" => "{$product->name} price changed. Refresh direct sale prices."]);
            }

            $resolved->push([
                'product' => $product,
                'warehouse_id' => (int) $product->default_warehouse_id,
                'unit' => ProductUnit::normalizeUnit((string) $selectedUnit->unit),
                'quantity' => $quantity,
                'conversion_to_base' => round($conversionToBase, 4),
                'base_quantity' => $baseQuantity,
                'unit_rate' => $unitRate,
                'line_total' => $lineTotal,
                'price_source' => (string) $price['source'],
            ]);
        }

        if ($missingWarehouses !== []) {
            throw ValidationException::withMessages([
                'items' => 'Cannot complete Direct Sale: '.implode(', ', array_unique($missingWarehouses)).' has no default inventory warehouse configured.',
            ]);
        }

        return $resolved;
    }

    private function validProductUnit(Product $product, string $unit, int $index): ProductUnit
    {
        $normalizedUnit = ProductUnit::normalizeUnit($unit);
        $selectedUnit = $product->orderUnits
            ->first(fn (ProductUnit $productUnit): bool => $productUnit->is_orderable
                && ProductUnit::normalizeUnit((string) $productUnit->unit) === $normalizedUnit);

        if (! $selectedUnit instanceof ProductUnit || (float) $selectedUnit->conversion_to_base <= 0.0) {
            throw ValidationException::withMessages(["items.{$index}.unit" => "{$product->name} does not have a valid orderable {$unit} unit conversion."]);
        }

        return $selectedUnit;
    }

    private function conversionForUnit(Product $product, string $unit): float
    {
        $normalizedUnit = ProductUnit::normalizeUnit($unit);
        $productUnit = $product->orderUnits
            ->first(fn (ProductUnit $productUnit): bool => ProductUnit::normalizeUnit((string) $productUnit->unit) === $normalizedUnit);

        if (! $productUnit instanceof ProductUnit || (float) $productUnit->conversion_to_base <= 0.0) {
            throw ValidationException::withMessages([
                'prices' => "{$product->name} price unit {$unit} has no valid inventory conversion.",
            ]);
        }

        return (float) $productUnit->conversion_to_base;
    }
}
