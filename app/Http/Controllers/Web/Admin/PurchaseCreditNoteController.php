<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\WastageReason;
use App\Enums\Purchasing\PurchaseCreditInventoryAction;
use App\Enums\Purchasing\PurchaseCreditMoneyEffect;
use App\Enums\Purchasing\PurchaseCreditReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StorePurchaseCreditNoteRequest;
use App\Http\Requests\Purchasing\VoidPurchaseCreditNoteRequest;
use App\Models\Cashbook\CompanyAccount;
use App\Models\PurchaseCreditNote;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaseCreditNoteService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseCreditNoteController extends Controller
{
    public function __construct(
        private readonly PurchaseCreditNoteService $creditNoteService,
    ) {}

    public function create(Request $request, Supplier $supplier): View
    {
        $rawInvoices = PurchaseInvoice::query()
            ->where('supplier_id', $supplier->id)
            ->notCancelled()
            ->with([
                'purchaserCart:id,business_date',
                'vendorSettlementAllocations',
                'purchaseCreditNotes' => fn ($q) => $q->active(),
            ])
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $invoices = $rawInvoices->map(function (PurchaseInvoice $inv): array {
            $gross = round((float) $inv->amount, 2);
            $discount = round((float) ($inv->discount_amount ?? 0), 2);
            $net = max(0.0, round($gross - $discount, 2));
            $outstanding = round((float) $inv->remainingBalance(), 2);
            $settled = max(0.0, round($net - $outstanding, 2));
            $date = $inv->purchaserCart?->business_date
                ? Carbon::parse($inv->purchaserCart->business_date)->format('d M Y')
                : ($inv->created_at ? $inv->created_at->format('d M Y') : '—');
            $rawDate = $inv->purchaserCart?->business_date
                ? Carbon::parse($inv->purchaserCart->business_date)->format('Y-m-d')
                : ($inv->created_at ? $inv->created_at->format('Y-m-d') : '');
            $paymentClass = (str_contains(strtolower((string) $inv->payment_method), 'cash')) ? 'Cash' : 'Credit';

            return [
                'id' => $inv->id,
                'public_uuid' => $inv->public_uuid,
                'invoice_number' => $inv->invoice_number,
                'date' => $date,
                'raw_date' => $rawDate,
                'payment_class' => $paymentClass,
                'payment_method' => $inv->payment_method ?: 'Credit',
                'gross_amount' => $gross,
                'discount_amount' => $discount,
                'net_amount' => $net,
                'settled_amount' => $settled,
                'outstanding_amount' => $outstanding,
            ];
        })->values()->all();

        $products = $supplier->relatedProducts();

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $companyAccounts = CompanyAccount::query()
            ->where('enabled', true)
            ->orderBy('name')
            ->get(['id', 'name', 'account_type']);

        $reasons = PurchaseCreditReason::cases();
        $moneyEffects = PurchaseCreditMoneyEffect::cases();
        $inventoryActions = PurchaseCreditInventoryAction::cases();
        $wastageReasons = WastageReason::cases();
        $grades = ProductGrade::cases();

        return view('admin.cashbook.finance.purchase.credit-notes.create', compact(
            'supplier',
            'invoices',
            'products',
            'warehouses',
            'companyAccounts',
            'reasons',
            'moneyEffects',
            'inventoryActions',
            'wastageReasons',
            'grades',
        ));
    }

    public function store(StorePurchaseCreditNoteRequest $request, Supplier $supplier): RedirectResponse
    {
        $payload = array_merge($request->validated(), [
            'supplier_id' => $supplier->id,
        ]);

        $creditNote = $this->creditNoteService->create($payload, $request->user());

        return redirect()
            ->route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $supplier->public_uuid])
            ->with('status', "Purchase Credit Note {$creditNote->credit_note_number} created successfully.");
    }

    public function show(Request $request, Supplier $supplier, PurchaseCreditNote $creditNote): View
    {
        abort_unless((int) $creditNote->supplier_id === (int) $supplier->id, 404);

        $creditNote->loadMissing([
            'items.product',
            'purchaseInvoice',
            'companyAccount',
            'warehouse',
            'vendorAdvance',
            'journalEntry.transactions.account',
            'createdBy',
            'voidedBy',
        ]);

        return view('admin.cashbook.finance.purchase.credit-notes.show', compact('supplier', 'creditNote'));
    }

    public function void(VoidPurchaseCreditNoteRequest $request, Supplier $supplier, PurchaseCreditNote $creditNote): RedirectResponse
    {
        abort_unless((int) $creditNote->supplier_id === (int) $supplier->id, 404);

        $this->creditNoteService->void($creditNote, $request->validated('reason'), $request->user());

        return redirect()
            ->back()
            ->with('status', "Purchase Credit Note {$creditNote->credit_note_number} has been voided successfully.");
    }

    public function checkStock(Request $request, Supplier $supplier): JsonResponse
    {
        $warehouseId = $request->input('warehouse_id') ? (int) $request->input('warehouse_id') : null;
        $inventoryAction = (string) $request->input('inventory_action', PurchaseCreditInventoryAction::Out->value);
        $deductStockForDamage = filter_var($request->input('deduct_stock_for_damage', true), FILTER_VALIDATE_BOOLEAN);
        $items = (array) $request->input('items', []);

        if ($warehouseId === null || empty($items)) {
            return response()->json([
                'has_shortage' => false,
                'shortages' => [],
            ]);
        }

        $stockCheck = $this->creditNoteService->checkStockAvailability(
            warehouseId: $warehouseId,
            items: $items,
            inventoryAction: $inventoryAction,
            deductStockForDamage: $deductStockForDamage,
        );

        return response()->json($stockCheck);
    }

    public function returnableItems(Request $request, Supplier $supplier, PurchaseInvoice $purchaseInvoice): JsonResponse
    {
        abort_unless((int) $purchaseInvoice->supplier_id === (int) $supplier->id, 404);

        $purchaseInvoice->loadMissing([
            'purchaserCart.items.product:id,name,unit',
            'goodsReceived.items.product:id,name,unit',
            'purchaseCreditNotes' => fn ($q) => $q->active()->with('items'),
        ]);

        $creditedTotals = [];
        foreach ($purchaseInvoice->purchaseCreditNotes as $pcn) {
            foreach ($pcn->items as $pcnItem) {
                $key = ((int) $pcnItem->product_id).'|'.((string) $pcnItem->grade);
                $creditedTotals[$key] = ($creditedTotals[$key] ?? 0.0) + (float) $pcnItem->quantity;
            }
        }

        $lines = [];
        if ($purchaseInvoice->purchaserCart && $purchaseInvoice->purchaserCart->items->isNotEmpty()) {
            foreach ($purchaseInvoice->purchaserCart->items as $cartItem) {
                $grade = (string) ($cartItem->grade ?: 'A');
                $key = ((int) $cartItem->product_id).'|'.$grade;
                $purchasedQty = (float) $cartItem->quantity;
                $alreadyCredited = $creditedTotals[$key] ?? 0.0;
                $returnableQty = max(0.0, round($purchasedQty - $alreadyCredited, 3));

                $lines[] = [
                    'purchaser_cart_item_id' => $cartItem->id,
                    'product_id' => $cartItem->product_id,
                    'product_name' => $cartItem->product?->name ?? "Product #{$cartItem->product_id}",
                    'unit' => $cartItem->product?->unit ?? 'kg',
                    'grade' => $grade,
                    'purchased_qty' => $purchasedQty,
                    'already_credited_qty' => $alreadyCredited,
                    'returnable_qty' => $returnableQty,
                    'unit_price' => (float) $cartItem->unit_price,
                ];
            }
        } elseif ($purchaseInvoice->goodsReceived && $purchaseInvoice->goodsReceived->items->isNotEmpty()) {
            foreach ($purchaseInvoice->goodsReceived->items as $grnItem) {
                $grade = (string) ($grnItem->grade ?: 'A');
                $key = ((int) $grnItem->product_id).'|'.$grade;
                $purchasedQty = (float) $grnItem->received_qty;
                $alreadyCredited = $creditedTotals[$key] ?? 0.0;
                $returnableQty = max(0.0, round($purchasedQty - $alreadyCredited, 3));
                $unitPrice = (float) ($grnItem->purchaseOrderItem?->unit_price ?? 0.0);

                $lines[] = [
                    'purchaser_cart_item_id' => null,
                    'product_id' => $grnItem->product_id,
                    'product_name' => $grnItem->product?->name ?? "Product #{$grnItem->product_id}",
                    'unit' => $grnItem->product?->unit ?? 'kg',
                    'grade' => $grade,
                    'purchased_qty' => $purchasedQty,
                    'already_credited_qty' => $alreadyCredited,
                    'returnable_qty' => $returnableQty,
                    'unit_price' => $unitPrice,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'invoice' => [
                'id' => $purchaseInvoice->id,
                'public_uuid' => $purchaseInvoice->public_uuid,
                'invoice_number' => $purchaseInvoice->invoice_number,
                'amount' => (float) $purchaseInvoice->amount,
                'remaining_balance' => (float) $purchaseInvoice->remainingBalance(),
            ],
            'items' => $lines,
        ]);
    }
}
