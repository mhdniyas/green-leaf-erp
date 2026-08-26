@extends('purchase-manager.layouts.app')

@section('title', $invoice->invoice_number)
@section('page_title', 'Shop Daily Invoice')
@section('page_description', 'Review invoice lines, delivery status, delivery discrepancies, and payment actions.')

@section('content')
    @php
        $invoiceDate = $invoice->business_date->toDateString();
        $invoiceCarbonDate = $invoice->business_date->copy();
        $canManageInvoiceMoney = \App\Support\AccountingAccess::canViewDashboard(auth()->user());
        $isDeliveryReviewPending = $invoice->delivery_status === 'awaiting_review' || $invoice->order?->delivery_status === 'pending_approval';
        $showAdminDeliveryOverride = ($canOverride ?? false) && ! $isDeliveryReviewPending;
        $showDeliveryReviewControls = ($canEdit ?? false) && ($isDeliveryReviewPending || $showAdminDeliveryOverride);
        $grossPayableAmount = round(max(0, (float) $invoice->subtotal - (float) $invoice->shortage_total + (float) $invoice->excess_total), 2);
        $maxDiscountAmount = round(max(0, $grossPayableAmount - (float) $invoice->paid_amount), 2);
        $canSeeRevertDeliveryApproval = auth()->user()->hasRole('admin')
            && $invoice->order
            && $invoice->order->delivery_review_status === 'approved'
            && in_array((string) $invoice->order->delivery_status, ['delivered', 'partially_delivered'], true);
        $canRevertDeliveryApproval = $canSeeRevertDeliveryApproval
            && (float) $invoice->paid_amount <= 0
            && $invoice->payment_approved_at === null
            && ! in_array((string) $invoice->payment_status, ['partially_paid', 'paid'], true);
        $revertBlockedReason = 'Revert is blocked because payment activity already started for this invoice.';
        $formatUnit = fn (?string $unit): string => \App\Models\ProductUnit::normalizeUnit($unit) === 'piece'
            ? 'PCE'
            : strtoupper(str_replace('_', ' ', \App\Models\ProductUnit::normalizeUnit($unit)));
        $priceQuantityFor = function ($item, float $baseQuantity): float {
            $product = $item->product;
            $priceUnit = \App\Models\ProductUnit::normalizeUnit((string) ($item->price_unit ?: $item->unit));

            if (! $product) {
                return $baseQuantity;
            }

            $baseUnit = \App\Models\ProductUnit::normalizeUnit((string) $product->unit);

            if ($priceUnit === $baseUnit) {
                return round($baseQuantity, 4);
            }

            $conversionToBase = $product->conversionToBaseForUnit($priceUnit);

            return $conversionToBase && (float) $conversionToBase > 0
                ? round($baseQuantity / (float) $conversionToBase, 4)
                : round($baseQuantity, 4);
        };
        $loadedInvoiceValue = round((float) $invoice->items->sum('line_subtotal'), 2);
        $netDeliveryImpact = round((float) $invoice->excess_total - (float) $invoice->shortage_total, 2);
        $reviewItems = $invoice->items->filter(function ($item) {
            $reportedShort = (float) ($item->orderItem?->shop_reported_missing_qty ?? $item->shortage_qty ?? 0);
            $reportedExcess = (float) ($item->orderItem?->shop_reported_excess_qty ?? $item->excess_qty ?? 0);
            $shopNote = trim((string) $item->orderItem?->shop_verification_note);
            $discrepancyNote = trim((string) $item->orderItem?->delivery_discrepancy_note);

            return $reportedShort > 0.0001 || $reportedExcess > 0.0001 || $shopNote !== '' || $discrepancyNote !== '';
        });
        if (($isDeliveryReviewPending || $showAdminDeliveryOverride) && $reviewItems->isEmpty()) {
            $reviewItems = $invoice->items;
        }
        $hiddenItemCount = max(0, $invoice->items->count() - $reviewItems->count());
        $originalInvoiceAmount = round((float) $invoice->items->sum('line_subtotal'), 2);
        $currentAdjustedAmount = round((float) $invoice->final_total, 2);
        $invoiceDifference = round($currentAdjustedAmount - $originalInvoiceAmount, 2);
        $finalizationLabel = $invoice->finalized_at ? 'Finalized' : ($isDeliveryReviewPending ? 'Needs Admin Review' : 'Ready to Finalize');
    @endphp
    <div class="space-y-6">
        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Shop Daily Invoice</p>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $invoice->invoice_number }}</h2>
                    <p class="mt-2 text-sm text-slate-600">{{ $invoice->shop?->name }} · {{ $invoice->business_date->format('d F Y') }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('purchasing.shop-invoices.pdf', $invoice) }}" target="_blank" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-slate-700">
                        Print / PDF
                    </a>
                    <a href="{{ route('purchasing.bill-prices.show', $invoice) }}" class="rounded-full border border-cyan-200 bg-cyan-50 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-cyan-800">
                        Edit Prices
                    </a>
                    @if ($canSeeRevertDeliveryApproval)
                        @if ($canRevertDeliveryApproval)
                            <form method="POST" action="{{ route('purchasing.shop-invoices.revert-approval', $invoice) }}" onsubmit="return confirm('Revert this delivery approval and reopen admin review editing?');">
                                @csrf
                                <button type="submit" class="rounded-full border border-amber-300 bg-amber-50 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-amber-800 hover:bg-amber-100">
                                    Revert Approval (Edit)
                                </button>
                            </form>
                        @else
                            <button type="button" disabled title="{{ $revertBlockedReason }}" class="cursor-not-allowed rounded-full border border-slate-200 bg-slate-100 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-slate-500 opacity-80">
                                Revert Approval (Edit)
                            </button>
                        @endif
                    @endif
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-slate-700">
                        {{ $shopSubmitted ?? false ? 'Shop Submitted' : 'Shop not submitted' }}
                    </span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-slate-700">
                        {{ $finalizationLabel }}
                    </span>
                </div>
            </div>

            <div class="border-b border-slate-100 px-5 py-4">
                <form method="GET" action="{{ route('purchasing.shop-invoices.index') }}" class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('purchasing.shop-invoices.index', ['date' => $invoiceCarbonDate->copy()->subDay()->toDateString()]) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-50">
                        Prev Date
                    </a>
                    <input type="date" name="date" value="{{ $invoiceDate }}" onchange="this.form.submit()" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-black text-slate-900">
                    <a href="{{ route('purchasing.shop-invoices.index', ['date' => today()->toDateString()]) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black uppercase tracking-[0.16em] text-emerald-700 hover:bg-emerald-50">
                        Today
                    </a>
                    <a href="{{ route('purchasing.shop-invoices.index', ['date' => $invoiceCarbonDate->copy()->addDay()->toDateString()]) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-50">
                        Next Date
                    </a>
                    <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-white hover:bg-slate-800">
                        Open Invoices For Date
                    </button>
                </form>
            </div>

            <div class="px-5 py-5">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Original Invoice Amount</p>
                        <p class="mt-2 text-lg font-black text-slate-950">Rs. {{ number_format($originalInvoiceAmount, 2) }}</p>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Current Adjusted Amount</p>
                        <p class="mt-2 text-lg font-black text-amber-800">Rs. {{ number_format($grossPayableAmount, 2) }}</p>
                    </div>
                    <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-cyan-700">Difference</p>
                        <p class="mt-2 text-lg font-black text-cyan-800">Rs. {{ number_format($invoiceDifference, 2) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Discount</p>
                        <p class="mt-2 text-lg font-black text-slate-950">Rs. {{ number_format((float) $invoice->discount_total, 2) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Final Amount</p>
                        <p class="mt-2 text-lg font-black text-slate-950">Rs. {{ number_format((float) $invoice->final_total, 2) }}</p>
                    </div>
                </div>

                @if ($showDeliveryReviewControls)
                    <div class="mt-4 flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-sm font-black text-amber-950">{{ $isDeliveryReviewPending ? 'Approval waiting' : 'Shop not submitted' }}</p>
                            <p class="mt-1 text-sm font-semibold text-amber-800">{{ $reviewItems->count() }} item{{ $reviewItems->count() === 1 ? '' : 's' }} available for admin review{{ $hiddenItemCount > 0 ? ', '.$hiddenItemCount.' unchanged item'.($hiddenItemCount === 1 ? '' : 's').' hidden below' : '' }}.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="#delivery-review-approval" class="inline-flex h-10 items-center justify-center rounded-xl bg-amber-600 px-4 text-xs font-black uppercase tracking-[0.14em] text-white hover:bg-amber-700">
                                {{ $isDeliveryReviewPending ? 'Review & Approve' : 'Review on behalf of Shop' }}
                            </a>
                            @if($invoice->order)
                                <form method="POST" action="{{ route('warehouse.loadout.move-to-loadout', $invoice->order) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl border border-amber-300 bg-white px-4 text-xs font-black uppercase tracking-[0.14em] text-amber-800 hover:bg-amber-100">
                                        Edit Loadout
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </section>

        @php
            $rawDeliveryNote = $invoice->delivery_note ?: $invoice->order?->delivery_notes;
            $orderNote = $invoice->order?->manager_note;

            $deliveryNote = $rawDeliveryNote;

            $itemsWithNotes = $invoice->items->filter(function ($item) {
                $itemVerif = trim((string) $item->orderItem?->shop_verification_note);
                $itemDiscrepancy = trim((string) $item->orderItem?->delivery_discrepancy_note);
                $itemOrder = trim((string) $item->orderItem?->notes);

                return !empty($itemVerif) || !empty($itemDiscrepancy) || !empty($itemOrder);
            });

            $paymentNotes = $invoice->paymentRequests->filter(fn ($pr) => !empty($pr->shop_note));
            $hasShopOwnerNotes = $deliveryNote || $orderNote || $itemsWithNotes->isNotEmpty() || $paymentNotes->isNotEmpty();
        @endphp

        @if ($hasShopOwnerNotes)
            <details class="rounded-3xl border border-amber-200 bg-amber-50/60 shadow-sm overflow-hidden" {{ $isDeliveryReviewPending ? '' : 'open' }}>
                <summary class="cursor-pointer list-none border-b border-amber-200/80 bg-amber-100/60 px-5 py-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-amber-500 text-white shadow-xs">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.255-3.388c.195-.291.515-.475.865-.501 1.153-.086 2.294-.213 3.423-.379 1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-black text-amber-950">Notes from Shop Incharge</h3>
                            <p class="text-xs font-semibold text-amber-800">Verification remarks, delivery notes & product feedback submitted by shop incharge.</p>
                        </div>
                    </div>
                    @if ($invoice->order?->shop_checked_at)
                        <span class="w-fit text-xs font-bold text-amber-900 bg-amber-200/80 px-3 py-1.5 rounded-full border border-amber-300">
                            Verified by {{ $invoice->order->shopCheckedBy?->name ?? 'Shop Incharge' }} &middot; {{ $invoice->order->shop_checked_at->format('d M Y, h:i A') }}
                        </span>
                    @endif
                    </div>
                </summary>

                <div class="p-5 space-y-4">
                    @if ($deliveryNote)
                        <div class="rounded-2xl border border-amber-200/80 bg-white p-4 shadow-xs">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Delivery Review Note</p>
                            <p class="mt-1 text-sm font-bold text-slate-900 leading-relaxed">{{ $deliveryNote }}</p>
                        </div>
                    @endif

                    @if ($orderNote)
                        <div class="rounded-2xl border border-amber-200/80 bg-white p-4 shadow-xs">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Order Requisition Note</p>
                            <p class="mt-1 text-sm font-bold text-slate-900 leading-relaxed">{{ $orderNote }}</p>
                        </div>
                    @endif

                    @if ($itemsWithNotes->isNotEmpty())
                        <div class="rounded-2xl border border-amber-200/80 bg-white p-4 shadow-xs">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700 mb-3">Item Remarks from Shop Incharge</p>
                            <div class="divide-y divide-slate-100">
                                @foreach ($itemsWithNotes as $item)
                                    <div class="py-2.5 first:pt-0 last:pb-0">
                                        <p class="font-black text-slate-950 text-sm">{{ $item->product_name }}</p>
                                        <div class="mt-1 space-y-1">
                                            @if ($item->orderItem?->shop_verification_note)
                                                <p class="text-xs text-amber-900 font-semibold">
                                                    <span class="font-black text-amber-700">Shop Note:</span> {{ $item->orderItem->shop_verification_note }}
                                                </p>
                                            @endif
                                            @if ($item->orderItem?->delivery_discrepancy_note)
                                                <p class="text-xs text-rose-800 font-semibold">
                                                    <span class="font-black text-rose-600">Discrepancy:</span> {{ $item->orderItem->delivery_discrepancy_note }}
                                                </p>
                                            @endif
                                            @if ($item->orderItem?->notes)
                                                <p class="text-xs text-slate-600 font-semibold">
                                                    <span class="font-black text-slate-500">Order Note:</span> {{ $item->orderItem->notes }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($paymentNotes->isNotEmpty())
                        <div class="rounded-2xl border border-amber-200/80 bg-white p-4 shadow-xs">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700 mb-3">Payment Request Notes</p>
                            <div class="divide-y divide-slate-100">
                                @foreach ($paymentNotes as $pr)
                                    <div class="py-2 first:pt-0 last:pb-0 text-xs font-semibold text-slate-800">
                                        <span class="font-black text-amber-800">{{ ucfirst(str_replace('_', ' ', $pr->request_type)) }} (Rs. {{ number_format((float)$pr->requested_amount, 2) }}):</span>
                                        {{ $pr->shop_note }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </details>
        @endif

        @if ($showDeliveryReviewControls)
            <section id="delivery-review-approval" class="overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-amber-100 bg-amber-50/70 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-amber-700">Delivery Review</p>
                        <h2 class="mt-1 text-lg font-black text-slate-950">Review Quantities & Finalize</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-600">{{ $shopSubmitted ?? false ? 'Correct final quantities, then record one overall delivery review note.' : 'Shop not submitted. Admin can review on behalf of shop and finalize this invoice.' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if($invoice->order)
                            <form method="POST" action="{{ route('warehouse.loadout.move-to-loadout', $invoice->order) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-amber-300 bg-white px-3 py-1.5 text-xs font-black text-amber-800 hover:bg-amber-100 transition-colors cursor-pointer border-none">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                    </svg>
                                    Edit Loadout Mistakes
                                </button>
                            </form>
                        @endif
                        <span class="w-fit rounded-full border border-amber-200 bg-white px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-amber-700">
                            Finance impact pending
                        </span>
                    </div>
                </div>

                <form id="delivery-discrepancy-approve-form" method="POST" action="{{ $isDeliveryReviewPending ? route('requisitions.delivery.approve', $invoice->order?->order_number) : route('purchasing.shop-invoices.finalize-on-behalf', $invoice) }}">
                    @csrf
                    <input type="hidden" name="invoice_number" value="{{ $invoice->invoice_number }}">
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Product</th>
                                <th class="px-3 py-3 text-right">Approved</th>
                                <th class="px-3 py-3 text-right">Received</th>
                                <th class="px-3 py-3 text-right">Short</th>
                                <th class="px-3 py-3 text-right">Excess</th>
                                <th class="px-3 py-3 text-right">Bill Impact</th>
                                <th class="px-3 py-3">Inventory Impact</th>
                                <th class="px-3 py-3">Final Qty</th>
                                <th class="px-4 py-3">Adjustment Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($reviewItems as $item)
                                @php
                                    $reportedDeliveredQty = (float) ($item->orderItem?->shop_reported_received_qty ?? $item->delivered_qty ?? $item->orderItem?->loaded_qty ?? $item->approved_qty);
                                    $reportedShortageQty = (float) ($item->orderItem?->shop_reported_missing_qty ?? $item->shortage_qty);
                                    $reportedExcessQty = (float) ($item->orderItem?->shop_reported_excess_qty ?? $item->excess_qty);
                                    $approvedDeliveredQty = old("approved_delivered_qty.{$item->shop_order_item_id}", number_format($reportedDeliveredQty, 2, '.', ''));
                                    $reportedShortageAmount = round($priceQuantityFor($item, $reportedShortageQty) * (float) $item->unit_price, 2);
                                    $reportedExcessAmount = round($priceQuantityFor($item, $reportedExcessQty) * (float) $item->unit_price, 2);
                                    $billImpact = round($reportedExcessAmount - $reportedShortageAmount, 2);
                                    $defaultInventoryAction = $reportedExcessQty > 0
                                        ? 'deduct_extra'
                                        : ($reportedShortageQty > 0 ? 'add_back' : 'none');
                                    $inventoryAction = old("item_inventory_actions.{$item->shop_order_item_id}", $defaultInventoryAction);
                                @endphp
                                <tr class="delivery-review-row align-middle hover:bg-slate-50/70" data-product-name="{{ $item->product_name }}">
                                    <td class="px-4 py-2.5">
                                        <p class="whitespace-nowrap font-black text-slate-950">{{ $item->product_name }}</p>
                                        @if($item->orderItem?->requested_unit_quantity && strtolower($item->orderItem->requested_unit ?? '') !== 'kg')
                                            <p class="mt-0.5 text-[10px] font-semibold text-slate-600">
                                                Ordered: <span class="font-black text-slate-900">{{ number_format((float) $item->orderItem->requested_unit_quantity, 2, '.', '') }} {{ strtoupper($item->orderItem->requested_unit_label ?: $item->orderItem->requested_unit) }}</span>
                                                &middot; Loaded: <span class="font-black text-slate-900">{{ number_format((float) ($item->orderItem->loaded_order_unit_qty ?? $item->orderItem->requested_unit_quantity), 2, '.', '') }} {{ strtoupper($item->orderItem->requested_unit_label ?: $item->orderItem->requested_unit) }}</span>
                                                &middot; Delivered: <span class="font-black text-emerald-700">{{ number_format((float) $item->delivered_qty, 2) }} {{ strtoupper($item->product->unit) }}</span>
                                            </p>
                                        @else
                                            <p class="mt-0.5 text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">{{ strtoupper($item->product->unit) }}</p>
                                        @endif
                                        @if ($formatUnit($item->price_unit ?: $item->unit) !== $formatUnit($item->unit))
                                            <p class="mt-0.5 text-[10px] font-black uppercase tracking-[0.12em] text-cyan-600">Bill {{ $formatUnit($item->price_unit) }}</p>
                                        @endif
                                        @php
                                            $itemVerif = trim((string) $item->orderItem?->shop_verification_note);
                                            $showItemVerifNote = !empty($itemVerif);
                                        @endphp
                                        @if ($showItemVerifNote)
                                            <div class="mt-1.5 rounded-lg border border-amber-200 bg-amber-50 p-2 text-xs">
                                                <span class="font-black text-amber-800">Shop Note:</span>
                                                <span class="font-semibold text-slate-900">{{ $itemVerif }}</span>
                                            </div>
                                        @endif
                                        @if ($item->orderItem?->delivery_discrepancy_note)
                                            <div class="mt-1 rounded-lg border border-rose-200 bg-rose-50 p-2 text-xs">
                                                <span class="font-black text-rose-800">Discrepancy:</span>
                                                <span class="font-semibold text-slate-900">{{ $item->orderItem->delivery_discrepancy_note }}</span>
                                            </div>
                                        @endif
                                        @if ($item->orderItem?->notes)
                                            <div class="mt-1 text-xs text-slate-500">
                                                <span class="font-bold text-slate-600">Order Note:</span> {{ $item->orderItem->notes }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-semibold text-slate-900">{{ number_format((float) $item->approved_qty, 2) }}</td>
                                    <td class="px-3 py-2.5 text-right font-semibold text-slate-900">{{ number_format($reportedDeliveredQty, 2) }}</td>
                                    <td class="px-3 py-2.5 text-right font-black {{ $reportedShortageQty > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ number_format($reportedShortageQty, 2) }}</td>
                                    <td class="px-3 py-2.5 text-right font-black {{ $reportedExcessQty > 0 ? 'text-cyan-700' : 'text-emerald-700' }}">{{ number_format($reportedExcessQty, 2) }}</td>
                                    <td class="px-3 py-2.5 text-right font-black {{ $billImpact < 0 ? 'text-rose-600' : ($billImpact > 0 ? 'text-cyan-700' : 'text-slate-900') }}">
                                        {{ $billImpact > 0 ? '+' : ($billImpact < 0 ? '-' : '') }}Rs. {{ number_format(abs($billImpact), 2) }}
                                    </td>
                                    <td class="px-3 py-2.5">
                                        @if ($reportedShortageQty > 0)
                                            <div class="space-y-1.5">
                                                <label class="flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-800">
                                                <input
                                                    form="delivery-discrepancy-approve-form"
                                                    type="radio"
                                                        name="item_inventory_actions[{{ $item->shop_order_item_id }}]"
                                                    value="add_back"
                                                    data-inventory-impact="Add back {{ number_format($reportedShortageQty, 2) }} {{ $item->unit }} to inventory"
                                                        @checked($inventoryAction === 'add_back')
                                                        class="mt-0.5 border-emerald-300 text-emerald-600 focus:ring-emerald-500"
                                                    >
                                                    <span>Add back {{ number_format($reportedShortageQty, 2) }} {{ $item->unit }} to inventory</span>
                                                </label>
                                                <label class="flex items-start gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700">
                                                    <input
                                                        form="delivery-discrepancy-approve-form"
                                                        type="radio"
                                                        name="item_inventory_actions[{{ $item->shop_order_item_id }}]"
                                                        value="none"
                                                        @checked($inventoryAction === 'none')
                                                        class="mt-0.5 border-slate-300 text-slate-700 focus:ring-slate-500"
                                                    >
                                                    <span>No inventory add-back</span>
                                                </label>
                                            </div>
                                        @elseif ($reportedExcessQty > 0)
                                            <div class="space-y-1.5">
                                                <label class="flex items-start gap-2 rounded-xl border border-cyan-200 bg-cyan-50 px-3 py-2 text-xs font-bold text-cyan-800">
                                                    <input
                                                        form="delivery-discrepancy-approve-form"
                                                        type="radio"
                                                        name="item_inventory_actions[{{ $item->shop_order_item_id }}]"
                                                    value="deduct_extra"
                                                    data-inventory-impact="Deduct extra {{ number_format($reportedExcessQty, 2) }} {{ $item->unit }} from inventory. This can create negative stock."
                                                        @checked($inventoryAction === 'deduct_extra')
                                                        class="mt-0.5 border-cyan-300 text-cyan-600 focus:ring-cyan-500"
                                                    >
                                                    <span>Deduct extra {{ number_format($reportedExcessQty, 2) }} {{ $item->unit }} from inventory</span>
                                                </label>
                                                <label class="flex items-start gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700">
                                                    <input
                                                        form="delivery-discrepancy-approve-form"
                                                        type="radio"
                                                        name="item_inventory_actions[{{ $item->shop_order_item_id }}]"
                                                        value="none"
                                                        @checked($inventoryAction === 'none')
                                                        class="mt-0.5 border-slate-300 text-slate-700 focus:ring-slate-500"
                                                    >
                                                    <span>No inventory deduction</span>
                                                </label>
                                            </div>
                                        @else
                                            <input form="delivery-discrepancy-approve-form" type="hidden" name="item_inventory_actions[{{ $item->shop_order_item_id }}]" value="none">
                                            <p class="max-w-[13rem] text-xs font-bold leading-5 text-emerald-700">No stock adjustment.</p>
                                        @endif
                                        @error("item_inventory_actions.{$item->shop_order_item_id}")
                                            <span class="mt-1 block text-xs font-semibold text-rose-600">{{ $message }}</span>
                                        @enderror
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <input
                                            form="delivery-discrepancy-approve-form"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            name="approved_delivered_qty[{{ $item->shop_order_item_id }}]"
                                            value="{{ $approvedDeliveredQty }}"
                                            class="h-9 w-24 rounded-xl border border-slate-200 bg-white px-3 text-right text-sm font-black text-slate-900 focus:border-amber-400 focus:outline-none"
                                        >
                                        @error("approved_delivered_qty.{$item->shop_order_item_id}")
                                            <span class="mt-1 block text-xs font-semibold text-rose-600">{{ $message }}</span>
                                        @enderror
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <input form="delivery-discrepancy-approve-form" type="hidden" name="item_review_notes[{{ $item->shop_order_item_id }}]" value="">
                                        <p class="max-w-[16rem] text-xs font-semibold leading-5 text-slate-600">Use overall note below for this delivery review.</p>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-200 bg-slate-50/80 px-4 py-4">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-2xl border border-emerald-200 bg-white p-4">
                            <label class="block">
                                <span class="mb-2 block text-[10px] font-black uppercase tracking-[0.14em] text-emerald-700">Delivery Note</span>
                                <textarea form="delivery-discrepancy-approve-form" name="review_note" rows="2" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900 focus:border-emerald-400 focus:outline-none" placeholder="One overall note for shortages, damage, or correction reason">{{ old('review_note', $invoice->delivery_note) }}</textarea>
                            </label>
                            <button form="delivery-discrepancy-approve-form" type="submit" class="mt-3 inline-flex h-10 w-full items-center justify-center rounded-xl bg-emerald-600 px-4 text-sm font-black text-white hover:bg-emerald-700 sm:w-auto">
                                {{ $shopSubmitted ?? false ? 'Finalize Invoice' : 'Review on behalf of Shop - Finalize Invoice' }}
                            </button>
                        </div>

                        <form method="POST" action="{{ route('requisitions.delivery.reject', $invoice->order?->order_number) }}" class="rounded-2xl border border-rose-200 bg-white p-4">
                            @csrf
                            <input type="hidden" name="invoice_number" value="{{ $invoice->invoice_number }}">
                            <label class="block">
                                <span class="mb-2 block text-[10px] font-black uppercase tracking-[0.14em] text-rose-700">Correction Note</span>
                                <textarea name="review_note" rows="2" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900 focus:border-rose-400 focus:outline-none" placeholder="Tell the shop incharge what to correct">{{ old('review_note') }}</textarea>
                            </label>
                            <button type="submit" class="mt-3 inline-flex h-10 w-full items-center justify-center rounded-xl bg-rose-600 px-4 text-sm font-black text-white hover:bg-rose-700 sm:w-auto">
                                Request Delivery Correction
                            </button>
                        </form>
                    </div>
                </div>
            </section>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const form = document.getElementById('delivery-discrepancy-approve-form');

                    if (!form) {
                        return;
                    }

                    form.addEventListener('submit', function (event) {
                        const impacts = Array.from(document.querySelectorAll('input[form="delivery-discrepancy-approve-form"][data-inventory-impact]:checked'))
                            .map((input) => {
                                const row = input.closest('.delivery-review-row');
                                const product = row?.dataset.productName || 'Product';

                                return `${product}: ${input.dataset.inventoryImpact}`;
                            });

                        if (impacts.length === 0) {
                            return;
                        }

                        const message = `Inventory impact will be posted:\n\n${impacts.join('\n')}\n\nContinue approval?`;

                        if (!window.confirm(message)) {
                            event.preventDefault();
                        }
                    });
                });
            </script>
        @endif

        <details class="rounded-3xl border border-slate-200 bg-white shadow-sm" {{ $isDeliveryReviewPending ? '' : 'open' }}>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="text-lg font-black text-slate-950">All Invoice Items</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">{{ $invoice->items->count() }} item{{ $invoice->items->count() === 1 ? '' : 's' }} in the generated invoice</p>
                </div>
                <span class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-black uppercase tracking-[0.14em] text-slate-700">
                    Toggle
                </span>
            </summary>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Product</th>
                            <th class="px-5 py-4 text-right">Ordered / Loaded</th>
                            <th class="px-5 py-4 text-right">Shop Submitted</th>
                            <th class="px-5 py-4 text-right">Admin Final Qty</th>
                            <th class="px-5 py-4 text-right">Unit Price</th>
                            <th class="px-5 py-4 text-right">Original Line Amount</th>
                            <th class="px-5 py-4 text-right">Final Line Amount</th>
                            <th class="px-5 py-4 text-right">Difference</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($invoice->items as $item)
                            <tr>
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-slate-950">{{ $item->product_name }}</p>
                                    <p class="mt-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Order {{ $formatUnit($item->product->unit) }}</p>
                                    @php
                                        $itemVerif = trim((string) $item->orderItem?->shop_verification_note);
                                        $showItemVerifNote = !empty($itemVerif);
                                    @endphp
                                    @if ($showItemVerifNote)
                                        <div class="mt-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-xs">
                                            <span class="font-black text-amber-800">Shop Note:</span>
                                            <span class="font-semibold text-slate-900">{{ $itemVerif }}</span>
                                        </div>
                                    @endif
                                    @if ($item->orderItem?->delivery_discrepancy_note)
                                        <div class="mt-1 rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-xs">
                                            <span class="font-black text-rose-800">Discrepancy:</span>
                                            <span class="font-semibold text-slate-900">{{ $item->orderItem->delivery_discrepancy_note }}</span>
                                        </div>
                                    @endif
                                    @if ($item->orderItem?->notes)
                                        <div class="mt-1 text-xs text-slate-500">
                                            <span class="font-bold text-slate-600">Order Note:</span> {{ $item->orderItem->notes }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right text-slate-700">{{ number_format((float) $item->approved_qty, 2) }} {{ $formatUnit($item->product->unit) }}</td>
                                <td class="px-5 py-4 text-right font-black text-slate-900">{{ number_format((float) ($item->orderItem?->shop_reported_received_qty ?? $item->delivered_qty), 2) }} {{ $formatUnit($item->product->unit) }}</td>
                                <td class="px-5 py-4 text-right text-slate-700">{{ number_format((float) $item->delivered_qty, 2) }} {{ $formatUnit($item->product->unit) }}</td>
                                <td class="px-5 py-4 text-right text-slate-900">Rs. {{ number_format((float) $item->unit_price, 2) }} / {{ $formatUnit($item->price_unit ?: $item->product->unit) }}</td>
                                <td class="px-5 py-4 text-right text-slate-700">Rs. {{ number_format((float) $item->line_subtotal, 2) }}</td>
                                <td class="px-5 py-4 text-right font-black text-slate-950">Rs. {{ number_format((float) $item->final_line_total, 2) }}</td>
                                <td class="px-5 py-4 text-right font-black {{ ((float) $item->final_line_total - (float) $item->line_subtotal) < 0 ? 'text-rose-700' : 'text-cyan-700' }}">Rs. {{ number_format((float) $item->final_line_total - (float) $item->line_subtotal, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>

        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-lg font-black text-slate-950">Adjustment History</h2>
                <p class="mt-1 text-sm text-slate-600">Quantity, discount, finalization, and note changes recorded for this invoice.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Date / Time</th>
                            <th class="px-5 py-4">Changed By</th>
                            <th class="px-5 py-4">Field / Product</th>
                            <th class="px-5 py-4">Before</th>
                            <th class="px-5 py-4">After</th>
                            <th class="px-5 py-4">Reason / Note</th>
                            <th class="px-5 py-4">Source</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse (($activities ?? collect()) as $activity)
                            @php
                                $properties = $activity->properties?->toArray() ?? [];
                                $before = $properties['before'] ?? [];
                                $after = $properties['after'] ?? [];
                            @endphp
                            <tr>
                                <td class="px-5 py-4 text-xs font-semibold text-slate-600">{{ $activity->created_at?->format('d M Y h:i A') }}</td>
                                <td class="px-5 py-4 font-semibold text-slate-900">{{ $activity->causer?->name ?? 'System' }}</td>
                                <td class="px-5 py-4 text-xs font-black text-slate-700">{{ str((string) $activity->event)->replace('_', ' ')->title() }}</td>
                                <td class="px-5 py-4"><pre class="whitespace-pre-wrap text-xs font-semibold text-slate-600">{{ json_encode($before, JSON_PRETTY_PRINT) }}</pre></td>
                                <td class="px-5 py-4"><pre class="whitespace-pre-wrap text-xs font-semibold text-slate-600">{{ json_encode($after, JSON_PRETTY_PRINT) }}</pre></td>
                                <td class="px-5 py-4 text-xs font-semibold text-slate-700">{{ $properties['reason'] ?? 'No reason recorded.' }}</td>
                                <td class="px-5 py-4 text-xs font-black text-slate-500">{{ str((string) ($properties['source'] ?? 'system'))->replace('_', ' ')->title() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-8 text-center text-sm font-semibold text-slate-500">No adjustment activity recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if (auth()->user()->hasRole('purchase') || auth()->user()->hasRole('admin'))
            <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-lg font-black text-slate-950">Shop Payment Requests</h2>
                    <p class="mt-1 text-sm text-slate-600">Approve or reject requested collection adjustments from the shop.</p>
                </div>

                @if ($invoice->paymentRequests->isEmpty())
                    <div class="px-5 py-10 text-center text-sm font-bold text-slate-500">
                        No shop payment requests for this invoice yet.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                                <tr>
                                    <th class="px-5 py-4">Type</th>
                                    <th class="px-5 py-4">Status</th>
                                    <th class="px-5 py-4 text-right">Requested Amount</th>
                                    <th class="px-5 py-4">Requested By</th>
                                    <th class="px-5 py-4">Notes</th>
                                    <th class="px-5 py-4">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($invoice->paymentRequests as $paymentRequest)
                                    <tr class="align-top">
                                        <td class="px-5 py-4 font-black text-slate-900">{{ ucfirst(str_replace('_', ' ', $paymentRequest->request_type)) }}</td>
                                        <td class="px-5 py-4">
                                            <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-slate-700">
                                                {{ $paymentRequest->statusLabel() }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 text-right font-black text-slate-950">Rs. {{ number_format((float) $paymentRequest->requested_amount, 2) }}</td>
                                        <td class="px-5 py-4 text-slate-700">
                                            {{ $paymentRequest->requestedBy?->name ?? 'Shop Incharge' }}
                                            <div class="mt-1 text-xs text-slate-500">{{ $paymentRequest->created_at?->format('d M Y h:i A') }}</div>
                                        </td>
                                        <td class="px-5 py-4 text-slate-700">
                                            @if ($paymentRequest->shop_note)
                                                <p><span class="font-black text-slate-900">Shop:</span> {{ $paymentRequest->shop_note }}</p>
                                            @endif
                                            @if ($paymentRequest->admin_note)
                                                <p class="mt-2"><span class="font-black text-slate-900">Admin:</span> {{ $paymentRequest->admin_note }}</p>
                                            @endif
                                            @if (! $paymentRequest->shop_note && ! $paymentRequest->admin_note)
                                                <span class="text-slate-400">No notes</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="text-sm font-semibold text-slate-500">
                                                {{ $paymentRequest->status === 'pending' ? 'Waiting for accounting approval' : 'No action pending' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                @php
                    $pendingBalanceAmount = max(0, round((float) $invoice->final_total - (float) $invoice->paid_amount, 2));
                @endphp
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-lg font-black text-slate-950">Payment Status</h2>
                    <p class="mt-1 text-sm text-slate-600">Purchasing can review the invoice. Payment approval and journal impact are handled by Admin Accounting.</p>
                </div>
                <div class="px-5 py-5">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Paid</p>
                            <p class="mt-2 text-lg font-black text-emerald-700">Rs. {{ number_format((float) $invoice->paid_amount, 2) }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Balance</p>
                            <p class="mt-2 text-lg font-black {{ $pendingBalanceAmount > 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format($pendingBalanceAmount, 2) }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Status</p>
                            <p class="mt-2 text-lg font-black text-slate-950">{{ ucfirst(str_replace('_', ' ', (string) $invoice->payment_status)) }}</p>
                        </div>
                    </div>
                    @if ($invoice->payment_note)
                        <p class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 text-sm font-semibold text-slate-600">{{ $invoice->payment_note }}</p>
                    @endif
                    @if ((float) $invoice->discount_total > 0)
                        <div class="mt-4 rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-indigo-700">Discount Approval</p>
                            <p class="mt-2 text-sm font-semibold text-slate-700">{{ $invoice->discount_note ?: 'No discount reason recorded.' }}</p>
                            <p class="mt-2 text-xs font-semibold text-slate-500">
                                {{ $invoice->discountApprovedBy?->name ?? 'Admin' }}
                                @if ($invoice->discount_approved_at)
                                    · {{ $invoice->discount_approved_at->format('d M Y h:i A') }}
                                @endif
                            </p>
                        </div>
                    @endif
                </div>
            </section>

            @if ($canManageInvoiceMoney)
                <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="text-lg font-black text-slate-950">Admin Billing Actions</h2>
                        <p class="mt-1 text-sm text-slate-600">Apply audited discount first, then record collected money against the finalized bill.</p>
                    </div>

                    @if ($invoice->finalized_at)
                        <div class="px-5 py-5">
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                                <p class="text-sm font-black text-emerald-900">Finalized bill is locked.</p>
                                <p class="mt-1 text-sm font-semibold text-emerald-800">Normal admin review, shop delivery edits, and repricing cannot change this invoice.</p>
                                @if (auth()->user()->hasRole('admin'))
                                    <button type="button" disabled class="mt-4 inline-flex h-10 cursor-not-allowed items-center justify-center rounded-xl border border-emerald-300 bg-white px-4 text-xs font-black uppercase tracking-[0.14em] text-emerald-800 opacity-80" title="Finalized correction workflow is not wired on this page.">
                                        Edit Finalized Invoice
                                    </button>
                                @endif
                            </div>
                        </div>
                    @elseif ($isDeliveryReviewPending)
                        <div class="px-5 py-5">
                            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                                <p class="text-sm font-black text-amber-900">Approve delivery review before discount or payment changes.</p>
                                <p class="mt-1 text-sm font-semibold text-amber-800">The final bill can still change from shortage or excess quantities.</p>
                            </div>
                        </div>
                    @else
                        <div class="grid gap-5 px-5 py-5 lg:grid-cols-2">
                            <form method="POST" action="{{ route('admin.accounting.shop-invoices.discount', $invoice) }}" class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
                                @csrf
                                @method('PATCH')
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-indigo-700">Before Discount</p>
                                        <p class="mt-1 text-sm font-black text-slate-950">Rs. {{ number_format($grossPayableAmount, 2) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-indigo-700">Current Discount</p>
                                        <p class="mt-1 text-sm font-black text-indigo-800">Rs. {{ number_format((float) $invoice->discount_total, 2) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-indigo-700">Max Allowed</p>
                                        <p class="mt-1 text-sm font-black text-slate-950">Rs. {{ number_format($maxDiscountAmount, 2) }}</p>
                                    </div>
                                </div>
                                <label class="mt-4 block">
                                    <span class="mb-2 block text-[10px] font-black uppercase tracking-[0.16em] text-indigo-700">Total discount amount</span>
                                    <input type="number" step="0.01" min="0" max="{{ number_format($maxDiscountAmount, 2, '.', '') }}" name="discount_total" value="{{ old('discount_total', number_format((float) $invoice->discount_total, 2, '.', '')) }}" class="h-11 w-full rounded-2xl border border-indigo-200 bg-white px-4 text-sm font-bold text-slate-900 focus:border-indigo-400 focus:outline-none">
                                    @error('discount_total')
                                        <span class="mt-1 block text-xs font-semibold text-rose-600">{{ $message }}</span>
                                    @enderror
                                </label>
                                <label class="mt-4 block">
                                    <span class="mb-2 block text-[10px] font-black uppercase tracking-[0.16em] text-indigo-700">Discount reason</span>
                                    <textarea name="discount_note" rows="3" required class="w-full rounded-2xl border border-indigo-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-indigo-400 focus:outline-none" placeholder="Reason required for audit">{{ old('discount_note', $invoice->discount_note) }}</textarea>
                                    @error('discount_note')
                                        <span class="mt-1 block text-xs font-semibold text-rose-600">{{ $message }}</span>
                                    @enderror
                                </label>
                                <button type="submit" class="mt-4 inline-flex h-11 w-full items-center justify-center rounded-2xl bg-indigo-600 px-5 text-sm font-black text-white transition hover:bg-indigo-500 sm:w-auto">
                                    Apply Discount
                                </button>
                            </form>

                            <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4">
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-cyan-700">Final Payable</p>
                                        <p class="mt-1 text-sm font-black text-slate-950">Rs. {{ number_format((float) $invoice->final_total, 2) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-cyan-700">Collected</p>
                                        <p class="mt-1 text-sm font-black text-emerald-700">Rs. {{ number_format((float) $invoice->paid_amount, 2) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-cyan-700">Balance</p>
                                        <p class="mt-1 text-sm font-black text-rose-700">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</p>
                                    </div>
                                </div>
                                @if ((float) $invoice->balance_amount > 0)
                                    <p class="mt-4 text-sm font-semibold text-cyan-800">Payment collection, cheque clearance and approval are handled from Finance V2 Payments.</p>
                                    <a href="{{ route('admin.finance-v2.payments.create', ['date' => $invoice->business_date?->toDateString() ?? today()->toDateString(), 'shop_id' => $invoice->shop_id, 'requested_amount' => round((float) $invoice->balance_amount, 2)]) }}" class="mt-4 inline-flex h-11 w-full items-center justify-center rounded-2xl bg-cyan-600 px-5 text-sm font-black text-white transition hover:bg-cyan-500 sm:w-auto">
                                        Finance payment
                                    </a>
                                @else
                                    <div class="mt-4 rounded-2xl border border-emerald-200 bg-white p-4 text-sm font-black text-emerald-700">
                                        This bill is fully paid.
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </section>
            @endif
        @endif

        @if (auth()->user()->hasRole('admin'))
            <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-lg font-black text-slate-950">Admin Price Refresh</h2>
                    <p class="mt-1 text-sm text-slate-600">Push updated approved prices into this invoice using a single full-width row.</p>
                </div>
                <div class="overflow-x-auto border-b border-slate-100">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                            <tr>
                                <th class="px-4 py-4">Product</th>
                                <th class="px-4 py-4 text-right">Approved Qty</th>
                                <th class="px-4 py-4 text-right">Current Unit Price</th>
                                <th class="px-4 py-4 text-right">Current Line Subtotal</th>
                                <th class="px-4 py-4 text-right">Current Shortage Amount</th>
                                <th class="px-4 py-4 text-right">Current Excess Amount</th>
                                <th class="px-4 py-4 text-right">Current Final Line Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($invoice->items as $item)
                                <tr>
                                    <td class="px-4 py-4 font-semibold text-slate-900">{{ $item->product_name }}</td>
                                    <td class="px-4 py-4 text-right text-slate-700">
                                        {{ number_format((float) ($item->price_quantity ?: $item->approved_qty), 4) }} {{ $formatUnit($item->price_unit ?: $item->unit) }}
                                    </td>
                                    <td class="px-4 py-4 text-right font-black text-slate-900">Rs. {{ number_format((float) $item->unit_price, 2) }} / {{ $formatUnit($item->price_unit ?: $item->unit) }}</td>
                                    <td class="px-4 py-4 text-right font-black text-slate-900">Rs. {{ number_format((float) $item->line_subtotal, 2) }}</td>
                                    <td class="px-4 py-4 text-right font-black text-amber-700">Rs. {{ number_format((float) $item->shortage_amount, 2) }}</td>
                                    <td class="px-4 py-4 text-right font-black text-cyan-700">Rs. {{ number_format((float) $item->excess_amount, 2) }}</td>
                                    <td class="px-4 py-4 text-right font-black text-slate-900">Rs. {{ number_format((float) $item->final_line_total, 2) }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-slate-50/70">
                                <td class="px-4 py-4 font-black text-slate-950">Total</td>
                                <td class="px-4 py-4"></td>
                                <td class="px-4 py-4"></td>
                                <td class="px-4 py-4 text-right font-black text-slate-950">Rs. {{ number_format((float) $invoice->subtotal, 2) }}</td>
                                <td class="px-4 py-4 text-right font-black text-amber-700">Rs. {{ number_format((float) $invoice->shortage_total, 2) }}</td>
                                <td class="px-4 py-4 text-right font-black text-cyan-700">Rs. {{ number_format((float) $invoice->excess_total, 2) }}</td>
                                <td class="px-4 py-4 text-right font-black text-slate-950">Rs. {{ number_format((float) $invoice->final_total, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <form method="POST" action="{{ route('purchasing.shop-invoices.reprice', $invoice) }}" class="space-y-4 px-5 py-5">
                    @csrf
                    @method('PATCH')
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-4">Reason</th>
                                    <th class="px-4 py-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="align-top">
                                    <td class="px-4 py-4">
                                        <textarea name="reason" rows="4" class="w-full min-w-[480px] rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900">{{ old('reason', $invoice->admin_price_note) }}</textarea>
                                    </td>
                                    <td class="px-4 py-4">
                                        <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-indigo-600 px-5 text-sm font-black text-white hover:bg-indigo-700">
                                            Refresh Invoice Prices
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </form>
            </section>
        @endif
    </div>
@endsection
