<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Purchaser;

use App\Http\Controllers\Controller;
use App\Models\ShopDailyProductPrice;
use App\Models\ShopInvoice;
use App\Models\ShopInvoiceItem;
use App\Models\ShopOrder;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class BillPriceApiController extends Controller
{
    public function __construct(
        private readonly ShopInvoiceService $shopInvoiceService,
    ) {}

    public function approveSpecialPrice(Request $request): JsonResponse
    {
        return $this->updatePrice($request, true);
    }

    public function updateBillPrice(Request $request): JsonResponse
    {
        return $this->updatePrice($request, (bool) $request->input('special_price'));
    }

    private function updatePrice(Request $request, bool $markSpecial): JsonResponse
    {
        $this->authorizePurchaser($request);

        $validated = $request->validate([
            'id' => ['nullable', 'integer'],
            'item_id' => ['nullable', 'integer'],
            'order_number' => ['required', 'string', 'max:100'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'business_date' => ['nullable', 'date'],
            'date' => ['nullable', 'date'],
            'unit_price' => ['nullable', 'numeric', 'min:0.01'],
            'special_price' => ['nullable', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:500'],
            'price_unit' => ['nullable', 'string', 'max:20'],
        ]);

        $price = $validated['special_price'] ?? $validated['unit_price'] ?? null;
        if ($price === null) {
            throw ValidationException::withMessages([
                'unit_price' => 'A unit price or special price is required.',
            ]);
        }

        $invoice = $this->resolveInvoice($validated);
        $invoice?->loadMissing(['shop', 'items.product', 'order.items.product']);

        $item = $invoice ? $this->resolveInvoiceItem($invoice, $validated) : null;
        $businessDate = $invoice?->business_date?->toDateString()
            ?? $this->requiredBusinessDate($validated);
        $shopId = (int) ($invoice?->shop_id ?? $validated['shop_id'] ?? 0);
        $productId = (int) ($item?->product_id ?? $validated['product_id'] ?? 0);

        if ($shopId <= 0 || $productId <= 0) {
            throw ValidationException::withMessages([
                'product_id' => 'Shop and product are required when no invoice exists yet.',
            ]);
        }

        $userId = (int) $request->user()->id;

        $existingCreatedBy = ShopDailyProductPrice::query()
            ->whereDate('business_date', $businessDate)
            ->where('shop_id', $shopId)
            ->where('product_id', $productId)
            ->value('created_by');

        $specialPrice = ShopDailyProductPrice::query()->updateOrCreate(
            [
                'business_date' => $businessDate,
                'shop_id' => $shopId,
                'product_id' => $productId,
            ],
            [
                'selling_price' => round((float) $price, 2),
                'price_unit' => filled($validated['price_unit'] ?? null)
                    ? trim((string) $validated['price_unit'])
                    : ($item?->price_unit ?: null),
                'status' => 'approved',
                'reason' => filled($validated['notes'] ?? null)
                    ? trim((string) $validated['notes'])
                    : ($markSpecial ? 'Updated from mobile special price approval.' : 'Updated from mobile bill price edit.'),
                'created_by' => $existingCreatedBy ?? $userId,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]
        );

        $invoiceRepriced = false;
        $invoiceRepriceSkipped = false;
        $freshItem = $item;

        if ($invoice) {
            try {
                $invoice = $this->shopInvoiceService->repriceInvoice(
                    $invoice,
                    $userId,
                    'Mobile bill price update by '.$request->user()->name.' for '.$invoice->invoice_number,
                    allowFinalized: true,
                );
                $invoiceRepriced = true;
            } catch (ValidationException $e) {
                $invoiceRepriceSkipped = true;
                $invoice->refresh();

                return response()->json([
                    'success' => false,
                    'message' => $e->validator->errors()->first() ?? $e->getMessage(),
                ], 422);
            }

            $freshItem = $invoice->items()
                ->where('product_id', $productId)
                ->first() ?? $item?->fresh();
        }

        return response()->json([
            'success' => true,
            'message' => 'Special price updated on web.',
            'data' => [
                'special_price_id' => $specialPrice->id,
                'invoice_id' => $invoice?->id,
                'invoice_number' => $invoice?->invoice_number,
                'item_id' => $freshItem?->id,
                'product_id' => $productId,
                'unit_price' => round((float) $price, 2),
                'special_price' => round((float) $price, 2),
                'line_total' => $freshItem ? (float) $freshItem->final_line_total : null,
                'invoice_final_total' => $invoice ? (float) $invoice->final_total : null,
                'invoice_repriced' => $invoiceRepriced,
                'invoice_reprice_skipped' => $invoiceRepriceSkipped,
                'updated_by_name' => (string) $request->user()->name,
                'updated_time' => now()->format('g:i A'),
            ],
        ]);
    }

    private function authorizePurchaser(Request $request): void
    {
        abort_unless($request->user()?->hasRole('purchaser'), 403);
    }

    private function resolveInvoice(array $validated): ?ShopInvoice
    {
        $invoice = ShopInvoice::query()
            ->with(['items.product', 'order'])
            ->where('invoice_number', $validated['order_number'])
            ->first();

        if (! $invoice) {
            $order = ShopOrder::query()
                ->where('order_number', $validated['order_number'])
                ->first();

            if ($order) {
                $invoice = ShopInvoice::query()
                    ->with(['items.product', 'order'])
                    ->where('shop_order_id', $order->id)
                    ->first();
            }
        }

        if (! $invoice && str_starts_with($validated['order_number'], 'DRAFT-')) {
            $orderNumber = substr($validated['order_number'], 6);
            $order = ShopOrder::query()
                ->where('order_number', $orderNumber)
                ->first();

            if ($order) {
                $invoice = ShopInvoice::query()
                    ->with(['items.product', 'order'])
                    ->where('shop_order_id', $order->id)
                    ->first();
            }
        }

        return $invoice;
    }

    private function requiredBusinessDate(array $validated): string
    {
        $date = $validated['business_date'] ?? $validated['date'] ?? null;

        if (! $date) {
            throw ValidationException::withMessages([
                'business_date' => 'Business date is required when no invoice exists yet.',
            ]);
        }

        return Carbon::parse((string) $date)->toDateString();
    }

    private function resolveInvoiceItem(ShopInvoice $invoice, array $validated): ShopInvoiceItem
    {
        $itemId = (int) ($validated['item_id'] ?? $validated['id'] ?? 0);

        if ($itemId > 0) {
            $item = $invoice->items->firstWhere('id', $itemId);
            if ($item instanceof ShopInvoiceItem) {
                return $item;
            }
        }

        $productId = (int) ($validated['product_id'] ?? 0);
        if ($productId > 0) {
            $item = $invoice->items->firstWhere('product_id', $productId);
            if ($item instanceof ShopInvoiceItem) {
                return $item;
            }
        }

        throw ValidationException::withMessages([
            'item_id' => 'No invoice item was found for this product.',
        ]);
    }
}
