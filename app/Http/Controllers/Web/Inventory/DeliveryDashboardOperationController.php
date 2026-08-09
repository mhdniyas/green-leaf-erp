<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Domains\ShopOrder\Actions\ResolveDeliveryReviewAction;
use App\Http\Controllers\Controller;
use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryDashboardOperationController extends Controller
{
    public function __construct(
        private readonly ResolveDeliveryReviewAction $resolveDeliveryReviewAction,
        private readonly ShopInvoiceService $shopInvoiceService,
    ) {}

    public function lockInvoice(Request $request, ShopOrder $shopOrder): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $shopOrder->loadMissing(['items.product', 'invoice.items']);

        $invoice = $shopOrder->invoice;

        if (! $invoice instanceof ShopInvoice) {
            $invoice = $this->shopInvoiceService->synchronizeOrderInvoice(
                $shopOrder,
                (int) $request->user()->id,
            );
        }

        if ($invoice->isFinalLocked()) {
            return redirect()->back()->with('success', 'Invoice is already locked.');
        }

        try {
            if ($shopOrder->delivery_status === 'pending_approval' && $shopOrder->delivery_review_status === 'pending') {
                $this->resolveDeliveryReviewAction->approve(
                    $shopOrder,
                    $this->approvedDeliveredQuantities($shopOrder),
                    [],
                    [],
                    [],
                    [],
                    (int) $request->user()->id,
                    'Locked from delivery dashboard.',
                );

                return redirect()->back()->with('success', 'Invoice locked after approving delivery review.');
            }

            if ($shopOrder->delivery_review_status === 'approved' || in_array((string) $shopOrder->delivery_status, ['delivered', 'partially_delivered'], true)) {
                DB::transaction(function () use ($shopOrder, $invoice, $request): void {
                    $invoice = ShopInvoice::query()
                        ->with('items')
                        ->lockForUpdate()
                        ->findOrFail($invoice->id);

                    $invoice->update([
                        'delivery_status' => (float) $invoice->shortage_total > 0.0001 ? 'approved_after_discrepancy' : 'received_full',
                        'status' => 'finalized',
                        'delivery_confirmed_by' => $invoice->delivery_confirmed_by ?? (int) $request->user()->id,
                        'delivery_confirmed_at' => $invoice->delivery_confirmed_at ?? now(),
                        'delivery_note' => trim((string) ($invoice->delivery_note ?? '')."\nLocked from delivery dashboard."),
                    ]);

                    $this->shopInvoiceService->recalculate($invoice->fresh('items'));

                    $shopOrder->update([
                        'is_delivered' => true,
                        'delivered_at' => $shopOrder->delivered_at ?? now(),
                        'delivered_by' => $shopOrder->delivered_by ?? (int) $request->user()->id,
                        'delivery_review_status' => $shopOrder->delivery_review_status === 'not_started'
                            ? 'approved'
                            : $shopOrder->delivery_review_status,
                    ]);
                });

                return redirect()->back()->with('success', 'Invoice locked.');
            }
        } catch (ValidationException $exception) {
            return redirect()->back()->withErrors($exception->errors());
        }

        return redirect()->back()->withErrors([
            'invoice' => 'Complete delivery check-in or admin delivery review before locking this invoice.',
        ]);
    }

    /**
     * @return array<int, float>
     */
    private function approvedDeliveredQuantities(ShopOrder $shopOrder): array
    {
        return $shopOrder->items
            ->mapWithKeys(function (ShopOrderItem $item): array {
                $quantity = $item->shop_reported_received_qty
                    ?? $item->delivered_qty
                    ?? $item->loaded_qty
                    ?? $item->approved_qty
                    ?? 0;

                return [(int) $item->id => (float) $quantity];
            })
            ->all();
    }
}
