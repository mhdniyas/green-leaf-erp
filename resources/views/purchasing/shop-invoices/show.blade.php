@extends('purchase-manager.layouts.app')

@section('title', $invoice->invoice_number)
@section('page_title', 'Shop Bill')
@section('page_description', 'Review the invoice bill, totals, notes, and finalization status.')

@section('content')
    @php
        $invoiceDate = $invoice->business_date->toDateString();
        $canManageInvoiceMoney = \App\Support\AccountingAccess::canViewDashboard(auth()->user());
        $isDeliveryReviewPending = $invoice->delivery_status === 'awaiting_review' || $invoice->order?->delivery_status === 'pending_approval';
        $showAdminDeliveryOverride = ($canOverride ?? false) && ! $isDeliveryReviewPending;
        $canReviewDelivery = ($canEdit ?? false) && ($isDeliveryReviewPending || $showAdminDeliveryOverride);
        $isFinalized = $isFinalized ?? $invoice->isFinalized();
        $canMoveBackToTransit = auth()->user()?->hasRole('admin')
            && ! $isFinalized
            && $invoice->order !== null
            && ((float) $invoice->paid_amount <= 0.0001)
            && ($invoice->order->delivery_status !== 'in_transit' || $invoice->order->delivery_review_status !== 'not_started' || $invoice->order->shop_checked_at !== null || $invoice->delivery_status !== 'pending');
        $canEditBill = ! $isFinalized;
        $canEditPrices = $canEditBill && (auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('purchase') || auth()->user()?->hasRole('purchaser'));
        $canEditDiscount = $canManageInvoiceMoney && ! $isFinalized && ! $isDeliveryReviewPending;
        $formatUnit = fn (?string $unit): string => \App\Models\ProductUnit::normalizeUnit($unit) === 'piece'
            ? 'PCE'
            : strtoupper(str_replace('_', ' ', \App\Models\ProductUnit::normalizeUnit($unit)));
        $money = fn (float|int|string|null $amount): string => 'Rs. '.number_format((float) $amount, 2);
        $lineQuantity = function ($item): float {
            if ($item->delivered_price_quantity !== null && $item->delivered_price_quantity !== '') {
                return round((float) $item->delivered_price_quantity, 4);
            }
            if ($item->price_quantity !== null && $item->price_quantity !== '') {
                return round((float) $item->price_quantity, 4);
            }
            if ($item->delivered_qty !== null && $item->delivered_qty !== '') {
                return round((float) $item->delivered_qty, 4);
            }
            return round((float) ($item->approved_qty ?? 0), 4);
        };
        $finalQuantity = function ($item) use ($lineQuantity): float {
            if ($item->delivered_qty !== null && $item->delivered_qty !== '') {
                return round((float) $item->delivered_qty, 4);
            }
            if ($item->orderItem?->delivered_qty !== null && $item->orderItem?->delivered_qty !== '') {
                return round((float) $item->orderItem->delivered_qty, 4);
            }
            if ($item->orderItem?->shop_reported_received_qty !== null && $item->orderItem?->shop_reported_received_qty !== '') {
                return round((float) $item->orderItem->shop_reported_received_qty, 4);
            }
            return round((float) $lineQuantity($item), 4);
        };
        $lineAmount = fn ($item): float => round((float) ($item->final_line_total ?: $item->line_subtotal), 2);
        $discountTotal = round((float) $invoice->discount_total, 2);
        $subtotal = round((float) $invoice->subtotal, 2);
        $finalTotal = round((float) $invoice->final_total, 2);
        $invoiceStatus = $isFinalized ? 'FINALIZED' : strtoupper(str_replace('_', ' ', (string) $invoice->status));
        $activityChanges = ($activities ?? collect())
            ->filter(fn ($activity) => in_array(data_get($activity->properties, 'source'), ['admin_item_adjustment', 'admin_discount'], true));
        $latestActivities = $activityChanges->take(5);
        $remainingActivities = max(0, $activityChanges->count() - $latestActivities->count());
        $itemChanges = $activityChanges
            ->filter(fn ($activity) => data_get($activity->properties, 'source') === 'admin_item_adjustment')
            ->keyBy(fn ($activity) => (int) data_get($activity->properties, 'after.item_id'));
        $changeLines = function ($activity) use ($money): array {
            $before = data_get($activity->properties, 'before', []);
            $after = data_get($activity->properties, 'after', []);
            $lines = [];

            if (data_get($before, 'qty') !== data_get($after, 'qty')) {
                $lines[] = 'Qty '.rtrim(rtrim(number_format((float) data_get($before, 'qty'), 4), '0'), '.').' -> '.rtrim(rtrim(number_format((float) data_get($after, 'qty'), 4), '0'), '.');
            }

            if (data_get($before, 'price') !== data_get($after, 'price')) {
                $lines[] = 'Price '.$money(data_get($before, 'price')).' -> '.$money(data_get($after, 'price'));
            }

            if (data_get($before, 'discount_total') !== data_get($after, 'discount_total')) {
                $lines[] = 'Discount '.$money(data_get($before, 'discount_total')).' -> '.$money(data_get($after, 'discount_total'));
            }

            return $lines;
        };
        $reviewAction = $invoice->order
            ? ($isDeliveryReviewPending
                ? route('requisitions.delivery.approve', $invoice->order->order_number)
                : route('purchasing.shop-invoices.finalize-on-behalf', $invoice))
            : null;
        $overallNote = $invoice->delivery_note
            ?: $invoice->order?->delivery_notes
            ?: $invoice->order?->manager_note
            ?: $invoice->discount_note;
    @endphp

    <div class="mx-auto max-w-5xl space-y-4 px-0 sm:px-2">
        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="mx-auto max-w-[46rem] px-3 py-5 sm:px-6 sm:py-7">
                <div class="border-b-2 border-dashed border-slate-200 pb-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Shop Bill</p>
                            <h2 class="mt-1 text-xl font-black text-slate-950 sm:text-2xl">{{ $invoice->shop?->name ?? 'Shop' }}</h2>
                            <p class="mt-1 text-sm font-semibold text-slate-600">{{ $invoice->shop?->code ?? $invoice->shop?->warehouse_tag }}</p>
                        </div>
                        <div class="flex flex-col gap-2 sm:items-end">
                            @if ($isFinalized)
                                <span class="w-fit rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-black uppercase tracking-[0.14em] text-emerald-700">
                                    FINALIZED BILL
                                </span>
                            @else
                                <span class="w-fit rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-black uppercase tracking-[0.14em] text-slate-700">
                                    {{ $invoiceStatus }}
                                </span>
                            @endif
                            @if (! ($shopSubmitted ?? false) && ! $isFinalized)
                                <span class="w-fit rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-black text-amber-800">
                                    Shop not submitted
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Invoice Number</p>
                            <p class="mt-1 font-black text-slate-950">{{ $invoice->invoice_number }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Date</p>
                            <p class="mt-1 font-black text-slate-950">{{ $invoice->business_date->format('d M Y') }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Status</p>
                            <p class="mt-1 font-black text-slate-950">{{ $isFinalized ? 'Finalized' : 'Open' }}</p>
                        </div>
                    </div>

                    @if ($isFinalized)
                        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm text-emerald-950">
                            <p class="font-black uppercase tracking-[0.14em]">FINALIZED BILL</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-emerald-700">Finalized By</p>
                                    <p class="mt-1 font-black">{{ $invoice->finalizedBy?->name ?? 'System' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-emerald-700">Finalized At</p>
                                    <p class="mt-1 font-black">{{ $invoice->finalized_at?->format('d M Y, h:i A') ?? 'Recorded' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-emerald-700">Final Amount</p>
                                    <p class="mt-1 font-black">{{ $money($finalTotal) }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mt-4 overflow-hidden rounded-lg border border-slate-200">
                    <div class="hidden grid-cols-[minmax(0,1fr)_5rem_4rem_6rem_7rem] gap-3 border-b border-slate-200 bg-slate-50 px-3 py-2 text-[10px] font-black uppercase tracking-[0.14em] text-slate-500 sm:grid">
                        <div>Product</div>
                        <div class="text-right">Qty</div>
                        <div>Unit</div>
                        <div class="text-right">Price</div>
                        <div class="text-right">Amount</div>
                    </div>

                    <div class="divide-y divide-slate-100">
                        @foreach ($invoice->items as $item)
                            @php
                                $qty = $lineQuantity($item);
                                $displayQty = rtrim(rtrim(number_format($qty, 4), '0'), '.');
                                $originalQty = round((float) ($item->orderItem?->requested_qty ?: $item->approved_qty ?: $qty), 4);
                                $loadedQty = round((float) ($item->orderItem?->loaded_qty ?: $item->delivered_qty ?: $qty), 4);
                                $finalQty = $finalQuantity($item);
                                $price = round((float) $item->unit_price, 2);
                                $productName = $item->product?->name ?? $item->product_name;
                                $lineTotal = $lineAmount($item);
                                $itemChange = $itemChanges->get((int) $item->id);
                                $itemChangeLines = $itemChange ? $changeLines($itemChange) : [];
                            @endphp

                            @if (! $invoice->isFinalized())
                                <button
                                    type="button"
                                    class="grid w-full grid-cols-[minmax(0,1fr)_auto] gap-2 px-3 py-3 text-left transition hover:bg-cyan-50 focus:bg-cyan-50 focus:outline-hidden sm:grid-cols-[minmax(0,1fr)_5rem_4rem_6rem_7rem] sm:gap-3"
                                    data-item-edit
                                    data-item-id="{{ $item->id }}"
                                    data-order-item-id="{{ $item->shop_order_item_id }}"
                                    data-action="{{ route('purchasing.shop-invoices.items.update', [$invoice, $item]) }}"
                                    data-product-id="{{ $item->product_id }}"
                                    data-product-name="{{ $productName }}"
                                    data-unit="{{ $formatUnit($item->unit) }}"
                                    data-price-unit="{{ $item->price_unit ?: $item->unit }}"
                                    data-original-qty="{{ $originalQty }}"
                                    data-loaded-qty="{{ $loadedQty }}"
                                    data-final-qty="{{ $finalQty }}"
                                    data-original-price="{{ $price }}"
                                    data-final-price="{{ $price }}"
                                    data-line-amount="{{ $lineTotal }}"
                                >
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-black text-slate-950">{{ $productName }}</span>
                                        <span class="mt-1 block text-xs font-semibold text-slate-500 sm:hidden">{{ $displayQty }} {{ $formatUnit($item->unit) }} at {{ $money($price) }}</span>
                                        @if ($itemChangeLines !== [])
                                            <span class="mt-1 block text-xs font-bold text-amber-700" data-change-product="{{ $productName }}">Changed: {{ implode(' | ', $itemChangeLines) }}</span>
                                        @endif
                                    </span>
                                    <span class="text-right text-sm font-black text-slate-950 sm:hidden" data-row-amount>{{ $money($lineTotal) }}</span>
                                    <span class="hidden text-right text-sm font-bold text-slate-800 sm:block" data-row-qty>{{ $displayQty }}</span>
                                    <span class="hidden text-sm font-bold text-slate-600 sm:block">{{ $formatUnit($item->unit) }}</span>
                                    <span class="hidden text-right text-sm font-bold text-slate-800 sm:block" data-row-price>{{ $money($price) }}</span>
                                    <span class="hidden text-right text-sm font-black text-slate-950 sm:block" data-row-amount>{{ $money($lineTotal) }}</span>
                                </button>
                            @else
                                <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-2 px-3 py-3 text-left sm:grid-cols-[minmax(0,1fr)_5rem_4rem_6rem_7rem] sm:gap-3">
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-black text-slate-950">{{ $productName }}</span>
                                        <span class="mt-1 block text-xs font-semibold text-slate-500 sm:hidden">{{ $displayQty }} {{ $formatUnit($item->unit) }} at {{ $money($price) }}</span>
                                        @if ($itemChangeLines !== [])
                                            <span class="mt-1 block text-xs font-bold text-amber-700" data-change-product="{{ $productName }}">Changed: {{ implode(' | ', $itemChangeLines) }}</span>
                                        @endif
                                    </span>
                                    <span class="text-right text-sm font-black text-slate-950 sm:hidden">{{ $money($lineTotal) }}</span>
                                    <span class="hidden text-right text-sm font-bold text-slate-800 sm:block">{{ $displayQty }}</span>
                                    <span class="hidden text-sm font-bold text-slate-600 sm:block">{{ $formatUnit($item->unit) }}</span>
                                    <span class="hidden text-right text-sm font-bold text-slate-800 sm:block">{{ $money($price) }}</span>
                                    <span class="hidden text-right text-sm font-black text-slate-950 sm:block">{{ $money($lineTotal) }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                <div class="mt-5 border-t-2 border-dashed border-slate-200 pt-4">
                    <div class="ml-auto max-w-sm space-y-2">
                        <div class="flex items-center justify-between text-sm font-bold text-slate-700">
                            <span>Subtotal</span>
                            <span>{{ $money($subtotal) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm font-bold text-slate-700">
                            <span class="flex items-center gap-2">
                                Discount
                                @if ($canEditDiscount)
                                    <button type="button" class="text-xs font-black text-cyan-700 hover:text-cyan-900" data-open-discount>
                                        Edit Discount
                                    </button>
                                @endif
                            </span>
                            <span>{{ $money($discountTotal) }}</span>
                        </div>
                        <div class="border-t border-slate-200 pt-3">
                            <div class="flex items-center justify-between text-lg font-black text-slate-950">
                                <span>Final Total</span>
                                <span>{{ $money($finalTotal) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <a href="{{ route('purchasing.shop-invoices.index', ['date' => $invoiceDate]) }}" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-black text-slate-700 hover:bg-slate-50">
                            Back to Bills
                        </a>

                        <div class="flex flex-col gap-2 sm:flex-row">
                            <a href="{{ route('purchasing.shop-invoices.pdf', $invoice) }}" target="_blank" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-black text-slate-700 hover:bg-slate-50">
                                Print / PDF
                            </a>
                            @if ($isFinalized && auth()->user()?->hasRole('admin'))
                                <a href="{{ route('purchasing.bill-prices.show', $invoice) }}" class="inline-flex h-11 items-center justify-center rounded-lg border border-amber-300 bg-amber-50 px-4 text-sm font-black text-amber-800 hover:bg-amber-100">
                                    Edit Finalized Invoice
                                </a>
                                <button type="button" class="inline-flex h-11 items-center justify-center rounded-lg border border-rose-300 bg-rose-50 px-4 text-sm font-black text-rose-800 hover:bg-rose-100 cursor-pointer" data-open-revert-finalization>
                                    Revert Finalization
                                </button>
                            @endif

                            @if ($canMoveBackToTransit)
                                <button type="button" class="inline-flex h-11 items-center justify-center rounded-lg border border-sky-300 bg-sky-50 px-4 text-sm font-black text-sky-800 hover:bg-sky-100 cursor-pointer" data-open-move-back-to-transit>
                                    Move Back to In Transit
                                </button>
                            @endif

                            @if (! $isFinalized && ($canFinalize ?? false) && $reviewAction)
                                <button type="button" class="inline-flex h-11 w-full items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-black text-white hover:bg-slate-800 sm:w-auto" data-open-finalize>
                                    Finalize Invoice
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @if (auth()->user()?->hasRole('admin'))
            <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-1 border-b border-slate-100 pb-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-black uppercase tracking-[0.16em] text-slate-950">Admin Actions</h3>
                            <p class="text-xs font-semibold text-slate-500">Supervisory corrections and workflow state overrides</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider text-slate-700">Admin Only</span>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    @if ($isFinalized)
                        <a href="{{ route('purchasing.bill-prices.show', $invoice) }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-amber-300 bg-amber-50 px-4 text-xs font-black text-amber-800 hover:bg-amber-100">
                            Edit Finalized Invoice
                        </a>
                        <button type="button" class="inline-flex h-10 items-center justify-center rounded-lg border border-rose-300 bg-rose-50 px-4 text-xs font-black text-rose-800 hover:bg-rose-100 cursor-pointer" data-open-revert-finalization>
                            Revert Finalization
                        </button>
                    @endif

                    @if ($canMoveBackToTransit)
                        <button type="button" class="inline-flex h-10 items-center justify-center rounded-lg border border-sky-300 bg-sky-50 px-4 text-xs font-black text-sky-800 hover:bg-sky-100 cursor-pointer" data-open-move-back-to-transit>
                            Move Back to In Transit
                        </button>
                    @endif

                    @if (! $isFinalized && ! $canMoveBackToTransit && $invoice->order?->delivery_status === 'in_transit')
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold text-sky-700">
                            <span class="h-2 w-2 rounded-full bg-sky-500 animate-pulse"></span>
                            Order is currently in transit awaiting shop check-in.
                        </span>
                    @endif
                </div>
            </section>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-1 border-b border-slate-100 pb-3">
                <h3 class="text-sm font-black uppercase tracking-[0.16em] text-slate-950">Notes & Changes</h3>
                <p class="text-sm font-semibold text-slate-600">Overall Note: {{ $overallNote ?: 'No overall note recorded.' }}</p>
            </div>

            <div class="mt-3 space-y-2">
                @forelse ($latestActivities as $activity)
                    @php
                        $source = data_get($activity->properties, 'source');
                        $product = data_get($activity->properties, 'after.product_name');
                        $reason = data_get($activity->properties, 'reason');
                        $lines = $changeLines($activity);
                    @endphp
                    <div class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">
                        <span class="font-black text-slate-950">{{ optional($activity->created_at)->format('d M H:i') }}</span>
                        <span class="font-semibold"> - {{ $activity->causer?->name ?? 'System' }}</span>
                        <span class="block text-xs font-black text-slate-600">{{ $product ?: ($source === 'admin_discount' ? 'Discount' : $activity->description) }}</span>
                        @foreach ($lines as $line)
                            <span class="block text-xs font-semibold text-slate-500">{{ $line }}</span>
                        @endforeach
                        @if ($reason)
                            <span class="block text-xs font-semibold text-slate-500">Note: {{ $reason }}</span>
                        @endif
                    </div>
                @empty
                    <p class="text-sm font-semibold text-slate-500">No change history recorded.</p>
                @endforelse
            </div>

            @if ($remainingActivities > 0)
                <details class="mt-3">
                    <summary class="cursor-pointer text-sm font-black text-cyan-700">View Full History</summary>
                    <div class="mt-2 space-y-2">
                        @foreach ($activityChanges->skip(5) as $activity)
                            @php
                                $source = data_get($activity->properties, 'source');
                                $product = data_get($activity->properties, 'after.product_name');
                                $reason = data_get($activity->properties, 'reason');
                                $lines = $changeLines($activity);
                            @endphp
                            <div class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                <span class="font-black text-slate-950">{{ optional($activity->created_at)->format('d M H:i') }}</span>
                                <span class="font-semibold"> - {{ $activity->causer?->name ?? 'System' }}</span>
                                <span class="block text-xs font-black text-slate-600">{{ $product ?: ($source === 'admin_discount' ? 'Discount' : $activity->description) }}</span>
                                @foreach ($lines as $line)
                                    <span class="block text-xs font-semibold text-slate-500">{{ $line }}</span>
                                @endforeach
                                @if ($reason)
                                    <span class="block text-xs font-semibold text-slate-500">Note: {{ $reason }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif
        </section>
    </div>

    @if ($canReviewDelivery && $reviewAction)
        <form id="invoice-finalize-form" method="POST" action="{{ $reviewAction }}" class="hidden">
            @csrf
            @foreach ($invoice->items as $item)
                @php
                    $formQty = $finalQuantity($item);
                    $inventoryAction = 'none';
                @endphp
                <input type="hidden" name="approved_delivered_qty[{{ $item->shop_order_item_id }}]" id="approved-delivered-qty-{{ $item->shop_order_item_id }}" value="{{ $formQty }}">
                <input type="hidden" name="item_inventory_actions[{{ $item->shop_order_item_id }}]" value="{{ $inventoryAction }}">
                <input type="hidden" name="item_review_notes[{{ $item->shop_order_item_id }}]" value="">
            @endforeach
            <input type="hidden" name="review_note" id="invoice-finalize-review-note" value="">
            <input type="hidden" name="invoice_number" value="{{ $invoice->invoice_number }}">
        </form>
    @endif

    @if ($canEditPrices || $canReviewDelivery)
        <div class="fixed inset-0 z-50 hidden items-end bg-slate-950/50 p-0 sm:items-center sm:p-4" data-item-modal>
            <div class="w-full rounded-t-lg bg-white p-4 shadow-xl sm:mx-auto sm:max-w-md sm:rounded-lg sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Edit Item</p>
                        <h3 class="mt-1 text-lg font-black text-slate-950" data-item-modal-name>Product</h3>
                    </div>
                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-black text-slate-700" data-close-item-modal>Cancel</button>
                </div>

                <div class="mt-4 grid gap-3">
                    <div class="grid grid-cols-2 gap-3 rounded-lg bg-slate-50 p-3 text-sm">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Original Qty</p>
                            <p class="mt-1 font-black text-slate-950"><span data-item-original-qty>0</span> <span data-item-unit>KG</span></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Loaded</p>
                            <p class="mt-1 font-black text-slate-950"><span data-item-loaded-qty>0</span> <span data-item-unit-copy>KG</span></p>
                        </div>
                    </div>

                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Final Qty</span>
                        <input type="number" step="0.0001" min="0" data-item-final-qty class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-base font-black text-slate-950 focus:border-cyan-500 focus:ring-cyan-500">
                    </label>

                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Final Price / Special Price</span>
                        <input type="number" step="0.01" min="0.01" data-item-final-price class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-base font-black text-slate-950 focus:border-cyan-500 focus:ring-cyan-500" {{ $canEditPrices ? '' : 'disabled' }}>
                    </label>


                    <div class="rounded-lg border border-slate-200 p-3 text-sm font-semibold text-slate-700" data-item-difference>
                        Qty: 0. Amount: Rs. 0.00.
                    </div>

                    {{-- Read-only Inventory Impact indicator --}}
                    <div class="rounded-lg border p-3 text-xs font-semibold hidden" data-inventory-impact>
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] mb-1.5" data-impact-label>Inventory Impact</p>
                        <p class="font-semibold" data-impact-message></p>
                    </div>
                </div>

                <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-black text-slate-700" data-close-item-modal>
                        Cancel
                    </button>
                    <button type="button" class="inline-flex h-11 items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-black text-white" data-save-item>
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($canEditPrices)
        <form id="invoice-price-form" method="POST" action="{{ route('purchasing.bill-prices.invoice-prices.update', $invoice) }}" class="hidden">
            @csrf
            <input type="hidden" name="prices[0][product_id]" id="invoice-price-product-id">
            <input type="hidden" name="prices[0][selling_price]" id="invoice-price-selling-price">
            <input type="hidden" name="prices[0][price_unit]" id="invoice-price-price-unit">
            <input type="hidden" name="prices[0][reason]" value="Invoice item price edit">
        </form>
    @endif

    @if ($canEditDiscount)
        <div class="fixed inset-0 z-50 hidden items-end bg-slate-950/50 p-0 sm:items-center sm:p-4" data-discount-modal>
            <form method="POST" action="{{ route('admin.accounting.shop-invoices.discount', $invoice) }}" class="w-full rounded-t-lg bg-white p-4 shadow-xl sm:mx-auto sm:max-w-md sm:rounded-lg sm:p-5">
                @csrf
                @method('PATCH')
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Edit Discount</p>
                        <h3 class="mt-1 text-lg font-black text-slate-950">Invoice Discount</h3>
                    </div>
                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-black text-slate-700" data-close-discount>Cancel</button>
                </div>
                <label class="mt-4 block">
                    <span class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Discount</span>
                    <input type="number" step="0.01" min="0" name="discount_total" value="{{ $discountTotal }}" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-base font-black text-slate-950 focus:border-cyan-500 focus:ring-cyan-500">
                </label>
                <label class="mt-3 block">
                    <span class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Note</span>
                    <textarea name="discount_note" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-950 focus:border-cyan-500 focus:ring-cyan-500">{{ old('discount_note', $invoice->discount_note) }}</textarea>
                </label>
                <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-black text-slate-700" data-close-discount>Cancel</button>
                    <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-black text-white">Save Discount</button>
                </div>
            </form>
        </div>
    @endif

    @if (($canFinalize ?? false) && $reviewAction && ! $isFinalized)
        @php
            $discrepancyItems = $invoice->items->filter(function ($item) use ($finalQuantity) {
                $loaded = round((float) ($item->orderItem?->loaded_qty ?? $item->orderItem?->approved_qty ?? $item->approved_qty ?? 0), 4);
                $final = round((float) $finalQuantity($item), 4);
                return abs($final - $loaded) > 0.0001;
            });
            $hasQuantityDiscrepancies = $discrepancyItems->isNotEmpty();
        @endphp

        {{-- Standard Finalize Modal (When all quantities match) --}}
        <div class="fixed inset-0 z-50 hidden items-end bg-slate-950/50 p-0 sm:items-center sm:p-4" data-finalize-modal>
            <div class="w-full rounded-t-2xl bg-white p-4 shadow-xl sm:mx-auto sm:max-w-md sm:rounded-2xl sm:p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Finalize Invoice</p>
                        <h3 class="mt-0.5 text-lg font-black text-slate-950">{{ $invoice->invoice_number }}</h3>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black text-emerald-800">
                        Quantities Match
                    </span>
                </div>
                @if (! ($shopSubmitted ?? false))
                    <p class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900">Shop not submitted. Admin finalization needs a note.</p>
                @endif
                <label class="mt-4 block">
                    <span class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Overall Note / Reason</span>
                    <textarea rows="3" data-finalize-note class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-950 focus:border-cyan-500 focus:ring-cyan-500" placeholder="Shop quantity confirmed manually."></textarea>
                </label>
                <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-700" data-close-finalize>Cancel</button>
                    <button type="button" class="inline-flex h-11 items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-black text-white hover:bg-slate-800" data-confirm-finalize>Finalize Invoice</button>
                </div>
            </div>
        </div>

        {{-- Inventory Impact Modal (Shown when differences exist between loaded and final bill quantities) --}}
        <div class="fixed inset-0 z-50 hidden items-end bg-slate-950/50 p-0 sm:items-center sm:p-4 overflow-y-auto" data-inventory-impact-modal>
            <div class="w-full max-h-[90vh] flex flex-col rounded-t-2xl bg-white p-4 shadow-xl sm:mx-auto sm:max-w-2xl sm:rounded-2xl sm:p-6 my-auto">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-800">
                                INVENTORY RESOLUTION REQUIRED
                            </span>
                            <span class="text-xs font-mono text-slate-400">{{ $invoice->invoice_number }}</span>
                        </div>
                        <h3 class="mt-1 text-lg font-black text-slate-950">Inventory Impact Review</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Differences detected between warehouse loadout and final bill quantity. Select reason and resolution for each item.</p>
                    </div>
                    <button type="button" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-black text-slate-600 hover:bg-slate-50" data-close-impact-modal>
                        Cancel
                    </button>
                </div>

                {{-- Discrepancy Items List --}}
                <div class="mt-4 flex-1 overflow-y-auto space-y-4 pr-1" data-impact-items-container>
                    @foreach ($discrepancyItems as $dItem)
                        @php
                            $dLoaded = round((float) ($dItem->orderItem?->loaded_qty ?? $dItem->orderItem?->approved_qty ?? $dItem->approved_qty ?? 0), 4);
                            $dFinal = round((float) $finalQuantity($dItem), 4);
                            $dDiff = round($dFinal - $dLoaded, 4);
                            $dUnit = $formatUnit($dItem->unit);
                            $dOrderItemId = $dItem->shop_order_item_id;
                            $dProductName = $dItem->product?->name ?? $dItem->product_name;
                        @endphp
                        <div class="rounded-2xl border border-slate-200/90 bg-slate-50/70 p-4 space-y-3" data-impact-row data-order-item-id="{{ $dOrderItemId }}" data-diff="{{ $dDiff }}" data-product-name="{{ $dProductName }}">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-200/60 pb-2.5">
                                <div>
                                    <h4 class="text-sm font-black text-slate-900">{{ $dProductName }}</h4>
                                    <p class="text-xs font-semibold text-slate-500">Unit: {{ $dUnit }}</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2.5 text-xs">
                                    <div class="text-slate-600">Loaded: <span class="font-mono font-bold text-slate-800">{{ rtrim(rtrim(number_format($dLoaded, 4), '0'), '.') }} {{ $dUnit }}</span></div>
                                    <div class="text-slate-600">Final: <span class="font-mono font-bold text-slate-800">{{ rtrim(rtrim(number_format($dFinal, 4), '0'), '.') }} {{ $dUnit }}</span></div>
                                    <div class="px-2.5 py-0.5 rounded-full text-[11px] font-black {{ $dDiff < 0 ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800' }}">
                                        Diff: {{ $dDiff > 0 ? '+'.rtrim(rtrim(number_format($dDiff, 4), '0'), '.') : rtrim(rtrim(number_format($dDiff, 4), '0'), '.') }} {{ $dUnit }}
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                {{-- Reason --}}
                                <div>
                                    <label class="block text-xs font-black uppercase tracking-[0.12em] text-slate-600 mb-1">
                                        Reason <span class="text-rose-500">*</span>
                                    </label>
                                    <select class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-950 focus:border-cyan-500 focus:ring-cyan-500" data-impact-reason>
                                        <option value="">-- Select Reason --</option>
                                        <option value="wastage_damage">Wastage / Damage</option>
                                        <option value="loadout_mistake">Loadout Mistake</option>
                                        <option value="delivery_mistake">Shop Order / Delivery Mistake</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>

                                {{-- Resolution --}}
                                <div>
                                    <label class="block text-xs font-black uppercase tracking-[0.12em] text-slate-600 mb-1">
                                        Resolution <span class="text-rose-500">*</span>
                                    </label>
                                    <select class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-950 focus:border-cyan-500 focus:ring-cyan-500" data-impact-resolution>
                                        <option value="">-- Select Resolution --</option>
                                        @if ($dDiff < 0)
                                            <option value="return_to_warehouse">Return to Warehouse</option>
                                            <option value="wastage">Wastage</option>
                                            <option value="already_accounted">Already Accounted / No Stock Adjustment</option>
                                        @else
                                            <option value="deduct_extra">Deduct Extra From Warehouse</option>
                                        @endif
                                    </select>
                                </div>
                            </div>

                            {{-- Note --}}
                            <div>
                                <label class="block text-xs font-black uppercase tracking-[0.12em] text-slate-600 mb-1">
                                    Discrepancy Note <span class="text-slate-400 font-normal text-[11px]" data-impact-note-required-hint>(Required for 'Other' or 'Already Accounted')</span>
                                </label>
                                <input type="text"
                                       placeholder="Enter note or explanation..."
                                       class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-950 focus:border-cyan-500 focus:ring-cyan-500"
                                       data-impact-note>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Overall Note --}}
                <div class="mt-4 pt-3 border-t border-slate-100">
                    <label class="block text-xs font-black uppercase tracking-[0.12em] text-slate-600 mb-1">
                        Overall Review Note
                    </label>
                    <textarea rows="2" data-impact-overall-note class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-950 focus:border-cyan-500 focus:ring-cyan-500" placeholder="Optional overall note for finalization."></textarea>
                </div>

                {{-- Error Message Alert --}}
                <div class="mt-3 hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-800" data-impact-error-banner></div>

                {{-- Action Buttons --}}
                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 hover:bg-slate-50" data-close-impact-modal>
                        Cancel
                    </button>
                    <button type="button" class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-950 px-5 text-xs font-black text-white hover:bg-slate-800 transition-all shadow-xs" data-confirm-impact-finalize>
                        Confirm &amp; Finalize Invoice
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($isFinalized && auth()->user()?->hasRole('admin'))
        <div class="fixed inset-0 z-50 hidden items-end bg-slate-950/50 p-0 sm:items-center sm:p-4 backdrop-blur-xs transition-opacity" data-revert-finalization-modal>
            <form method="POST" action="{{ route('purchasing.shop-invoices.revert-finalization', $invoice) }}" class="w-full rounded-t-2xl bg-white p-5 shadow-2xl sm:mx-auto sm:max-w-lg sm:rounded-2xl sm:p-6" data-revert-finalization-form>
                @csrf
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider text-rose-800">
                            Admin Action
                        </span>
                        <h3 class="mt-2 text-lg font-black text-slate-950">Revert Invoice Finalization</h3>
                    </div>
                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-black text-slate-600 hover:bg-slate-50 cursor-pointer" data-close-revert-modal>✕</button>
                </div>

                <div class="mt-4 space-y-3">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs text-slate-700">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Invoice</span>
                                <p class="font-black text-slate-900">{{ $invoice->invoice_number }}</p>
                            </div>
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Shop</span>
                                <p class="font-black text-slate-900">{{ $invoice->shop?->name ?? 'Shop' }}</p>
                            </div>
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Business Date</span>
                                <p class="font-black text-slate-900">{{ $invoice->business_date->format('d M Y') }}</p>
                            </div>
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Current Total</span>
                                <p class="font-black text-slate-900">{{ $money($finalTotal) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-rose-200 bg-rose-50/70 p-3 text-xs text-rose-900">
                        <p class="font-bold">⚠️ Warning:</p>
                        <p class="mt-0.5 text-slate-600">Reverting finalization will restore the invoice and order to an open review state, void the ledger transaction projection, and allow price modifications or re-finalization. Recorded payments cannot exist.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase tracking-[0.12em] text-slate-700 mb-1">
                            Reason for Reversion <span class="text-rose-500">*</span>
                        </label>
                        <textarea name="reason" rows="3" required placeholder="Enter mandatory reason for reverting finalization..." class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-950 focus:border-rose-500 focus:ring-rose-500 focus:outline-hidden" data-revert-reason></textarea>
                    </div>
                </div>

                <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 hover:bg-slate-50 cursor-pointer" data-close-revert-modal>
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-rose-600 px-5 text-xs font-black text-white hover:bg-rose-700 transition-all shadow-xs cursor-pointer" data-confirm-revert>
                        Confirm Revert Finalization
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if ($canMoveBackToTransit)
        <div class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 p-4 backdrop-blur-xs" data-move-transit-modal>
            <form method="POST" action="{{ route('purchasing.shop-invoices.move-back-to-transit', $invoice) }}" class="relative w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl" data-move-transit-form>
                @csrf
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-100 px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider text-sky-800">
                            Admin Workflow Correction
                        </span>
                        <h3 class="mt-2 text-lg font-black text-slate-950">Move Back to In Transit</h3>
                    </div>
                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-black text-slate-600 hover:bg-slate-50 cursor-pointer" data-close-move-transit-modal>✕</button>
                </div>

                <div class="mt-4 space-y-3">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs text-slate-700">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Invoice</span>
                                <p class="font-black text-slate-900">{{ $invoice->invoice_number }}</p>
                            </div>
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Shop</span>
                                <p class="font-black text-slate-900">{{ $invoice->shop?->name ?? 'Shop' }}</p>
                            </div>
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Current Status</span>
                                <p class="font-black text-slate-900">Order: {{ strtoupper(str_replace('_', ' ', (string) $invoice->order?->delivery_status)) }} ({{ strtoupper(str_replace('_', ' ', (string) $invoice->order?->delivery_review_status)) }})</p>
                            </div>
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Destination Status</span>
                                <p class="font-black text-sky-700">IN TRANSIT (Pending Shop Check-in)</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-sky-200 bg-sky-50/70 p-3 text-xs text-sky-950">
                        <p class="font-bold">ℹ️ Note:</p>
                        <p class="mt-0.5 text-slate-600">This action will reset the shop check-in and delivery discrepancy resolution, allowing the shop and warehouse to receive and verify the delivery freshly. Previous verification details will be recorded in the audit history.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-black uppercase tracking-[0.12em] text-slate-700 mb-1">
                            Reason for Correction <span class="text-rose-500">*</span>
                        </label>
                        <textarea name="reason" rows="3" required placeholder="Enter mandatory reason for moving back to in transit..." class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-950 focus:border-sky-500 focus:ring-sky-500 focus:outline-hidden" data-move-transit-reason></textarea>
                    </div>
                </div>

                <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 hover:bg-slate-50 cursor-pointer" data-close-move-transit-modal>
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-sky-600 px-5 text-xs font-black text-white hover:bg-sky-700 transition-all shadow-xs cursor-pointer" data-confirm-move-transit>
                        Confirm Move to In Transit
                    </button>
                </div>
            </form>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const money = (value) => `Rs. ${Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            const trimNumber = (value) => {
                const number = Number(value || 0);

                return Number.isInteger(number) ? String(number) : number.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
            };

            const itemModal = document.querySelector('[data-item-modal]');
            let activeItemButton = null;

            const closeItemModal = () => {
                itemModal?.classList.add('hidden');
                itemModal?.classList.remove('flex');
            };

            const updatePreview = () => {
                if (!itemModal || !activeItemButton) {
                    return;
                }

                const qtyInput = itemModal.querySelector('[data-item-final-qty]');
                const priceInput = itemModal.querySelector('[data-item-final-price]');
                const originalQty = Number(activeItemButton.dataset.originalQty || 0);
                const originalPrice = Number(activeItemButton.dataset.originalPrice || 0);
                const loadedQty = Number(activeItemButton.dataset.loadedQty || 0);
                const finalQty = Number(qtyInput.value || 0);
                const finalPrice = Number(priceInput.value || 0);
                const qtyDiff = finalQty - originalQty;
                const amountDiff = (finalQty * finalPrice) - (originalQty * originalPrice);

                itemModal.querySelector('[data-item-difference]').textContent = `Qty: ${trimNumber(qtyDiff)} ${activeItemButton.dataset.unit || ''}. Amount: ${money(amountDiff)}.`;

                // Inventory Impact panel
                const impactPanel = itemModal.querySelector('[data-inventory-impact]');
                const impactMessage = itemModal.querySelector('[data-impact-message]');
                if (impactPanel && impactMessage) {
                    const delta = finalQty - loadedQty;
                    impactPanel.classList.remove(
                        'hidden',
                        'border-amber-200', 'bg-amber-50', 'text-amber-800',
                        'border-rose-200', 'bg-rose-50', 'text-rose-800',
                        'border-emerald-200', 'bg-emerald-50', 'text-emerald-800',
                    );
                    if (delta < -0.0001) {
                        impactPanel.classList.add('border-amber-200', 'bg-amber-50', 'text-amber-800');
                        impactMessage.textContent = `Shortage of ${trimNumber(Math.abs(delta))} ${activeItemButton.dataset.unit || ''}. Inventory add-back may be required.`;
                    } else if (delta > 0.0001) {
                        impactPanel.classList.add('border-rose-200', 'bg-rose-50', 'text-rose-800');
                        impactMessage.textContent = `Excess of ${trimNumber(delta)} ${activeItemButton.dataset.unit || ''}. Inventory deduction may be required.`;
                    } else {
                        impactPanel.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-800');
                        impactMessage.textContent = 'No inventory impact — final qty matches loaded qty.';
                    }
                }
            };

            document.querySelectorAll('[data-item-edit]').forEach((button) => {
                button.addEventListener('click', () => {
                    activeItemButton = button;
                    itemModal.querySelector('[data-item-modal-name]').textContent = button.dataset.productName || 'Product';
                    itemModal.querySelector('[data-item-original-qty]').textContent = trimNumber(button.dataset.originalQty);
                    itemModal.querySelector('[data-item-loaded-qty]').textContent = trimNumber(button.dataset.loadedQty);
                    itemModal.querySelector('[data-item-unit]').textContent = button.dataset.unit || '';
                    itemModal.querySelector('[data-item-unit-copy]').textContent = button.dataset.unit || '';
                    itemModal.querySelector('[data-item-final-qty]').value = button.dataset.finalQty || '0';
                    itemModal.querySelector('[data-item-final-price]').value = button.dataset.finalPrice || '0';
                    updatePreview();
                    itemModal.classList.remove('hidden');
                    itemModal.classList.add('flex');
                });
            });

            itemModal?.querySelectorAll('[data-close-item-modal]').forEach((button) => button.addEventListener('click', closeItemModal));
            itemModal?.querySelector('[data-item-final-qty]')?.addEventListener('input', updatePreview);
            itemModal?.querySelector('[data-item-final-price]')?.addEventListener('input', updatePreview);

            itemModal?.querySelector('[data-save-item]')?.addEventListener('click', async () => {
                if (!activeItemButton) {
                    closeItemModal();

                    return;
                }

                const finalQty = itemModal.querySelector('[data-item-final-qty]').value || '0';
                const finalPrice = itemModal.querySelector('[data-item-final-price]').value || '0';
                const qtyInput = document.getElementById(`approved-delivered-qty-${activeItemButton.dataset.orderItemId}`);

                if (qtyInput) {
                    qtyInput.value = finalQty;
                    activeItemButton.dataset.finalQty = finalQty;
                    activeItemButton.querySelectorAll('[data-row-qty]').forEach((node) => node.textContent = trimNumber(finalQty));
                }

                const response = await fetch(activeItemButton.dataset.action, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        final_qty: Number(finalQty || 0),
                        final_price: Number(finalPrice || 0),
                        note: 'Invoice item edit',
                    }),
                });

                window.location.reload();
            });

            const discountModal = document.querySelector('[data-discount-modal]');
            document.querySelector('[data-open-discount]')?.addEventListener('click', () => {
                discountModal?.classList.remove('hidden');
                discountModal?.classList.add('flex');
            });
            discountModal?.querySelectorAll('[data-close-discount]').forEach((button) => {
                button.addEventListener('click', () => {
                    discountModal.classList.add('hidden');
                    discountModal.classList.remove('flex');
                });
            });

            const finalizeModal = document.querySelector('[data-finalize-modal]');
            const impactModal = document.querySelector('[data-inventory-impact-modal]');
            const impactRows = document.querySelectorAll('[data-impact-row]');
            const impactErrorBanner = document.querySelector('[data-impact-error-banner]');

            // Dynamic note hint updater
            impactRows.forEach((row) => {
                const reasonSelect = row.querySelector('[data-impact-reason]');
                const resolutionSelect = row.querySelector('[data-impact-resolution]');
                const hint = row.querySelector('[data-impact-note-required-hint]');

                const updateNoteHint = () => {
                    const isRequired = reasonSelect?.value === 'other' || resolutionSelect?.value === 'already_accounted';
                    if (hint) {
                        if (isRequired) {
                            hint.textContent = '*(Required)';
                            hint.classList.add('text-rose-600', 'font-black');
                            hint.classList.remove('text-slate-400', 'font-normal');
                        } else {
                            hint.textContent = "(Required for 'Other' or 'Already Accounted')";
                            hint.classList.remove('text-rose-600', 'font-black');
                            hint.classList.add('text-slate-400', 'font-normal');
                        }
                    }
                };

                reasonSelect?.addEventListener('change', updateNoteHint);
                resolutionSelect?.addEventListener('change', updateNoteHint);
            });

            // Open appropriate modal on Finalize click
            document.querySelector('[data-open-finalize]')?.addEventListener('click', () => {
                if (impactRows.length > 0) {
                    impactModal?.classList.remove('hidden');
                    impactModal?.classList.add('flex');
                } else {
                    finalizeModal?.classList.remove('hidden');
                    finalizeModal?.classList.add('flex');
                }
            });

            // Standard finalize modal close & confirm
            finalizeModal?.querySelector('[data-close-finalize]')?.addEventListener('click', () => {
                finalizeModal.classList.add('hidden');
                finalizeModal.classList.remove('flex');
            });
            finalizeModal?.querySelector('[data-confirm-finalize]')?.addEventListener('click', () => {
                const note = finalizeModal.querySelector('[data-finalize-note]')?.value || '';
                const form = document.getElementById('invoice-finalize-form');
                const noteInput = document.getElementById('invoice-finalize-review-note');

                if (noteInput) {
                    noteInput.value = note;
                }

                form?.submit();
            });

            // Impact modal close
            impactModal?.querySelectorAll('[data-close-impact-modal]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    impactModal.classList.add('hidden');
                    impactModal.classList.remove('flex');
                    if (impactErrorBanner) {
                        impactErrorBanner.classList.add('hidden');
                    }
                });
            });

            // Impact modal confirm & finalize
            impactModal?.querySelector('[data-confirm-impact-finalize]')?.addEventListener('click', () => {
                let hasError = false;
                let errorMessage = '';

                impactRows.forEach((row) => {
                    if (hasError) return;
                    const productName = row.dataset.productName || 'Item';
                    const reason = row.querySelector('[data-impact-reason]')?.value || '';
                    const resolution = row.querySelector('[data-impact-resolution]')?.value || '';
                    const note = (row.querySelector('[data-impact-note]')?.value || '').trim();

                    if (!reason) {
                        hasError = true;
                        errorMessage = `Please select a Reason for ${productName}.`;
                    } else if (!resolution) {
                        hasError = true;
                        errorMessage = `Please select a Resolution for ${productName}.`;
                    } else if (reason === 'other' && !note) {
                        hasError = true;
                        errorMessage = `A Discrepancy Note is required for ${productName} when reason is 'Other'.`;
                    } else if (resolution === 'already_accounted' && !note) {
                        hasError = true;
                        errorMessage = `A Discrepancy Note is required for ${productName} when resolution is 'Already Accounted'.`;
                    }
                });

                if (hasError) {
                    if (impactErrorBanner) {
                        impactErrorBanner.textContent = errorMessage;
                        impactErrorBanner.classList.remove('hidden');
                    }
                    return;
                }

                if (impactErrorBanner) {
                    impactErrorBanner.classList.add('hidden');
                }

                // Populate hidden form inputs
                const form = document.getElementById('invoice-finalize-form');
                if (!form) return;

                impactRows.forEach((row) => {
                    const orderItemId = row.dataset.orderItemId;
                    const reason = row.querySelector('[data-impact-reason]')?.value || '';
                    const resolution = row.querySelector('[data-impact-resolution]')?.value || '';
                    const note = (row.querySelector('[data-impact-note]')?.value || '').trim();

                    let reasonInput = form.querySelector(`input[name="delivery_discrepancy_types[${orderItemId}]"]`);
                    if (!reasonInput) {
                        reasonInput = document.createElement('input');
                        reasonInput.type = 'hidden';
                        reasonInput.name = `delivery_discrepancy_types[${orderItemId}]`;
                        form.appendChild(reasonInput);
                    }
                    reasonInput.value = reason;

                    let resInput = form.querySelector(`input[name="item_inventory_actions[${orderItemId}]"]`);
                    if (!resInput) {
                        resInput = document.createElement('input');
                        resInput.type = 'hidden';
                        resInput.name = `item_inventory_actions[${orderItemId}]`;
                        form.appendChild(resInput);
                    }
                    resInput.value = resolution;

                    let noteInput = form.querySelector(`input[name="delivery_discrepancy_notes[${orderItemId}]"]`);
                    if (!noteInput) {
                        noteInput = document.createElement('input');
                        noteInput.type = 'hidden';
                        noteInput.name = `delivery_discrepancy_notes[${orderItemId}]`;
                        form.appendChild(noteInput);
                    }
                    noteInput.value = note;
                });

                const overallNote = impactModal.querySelector('[data-impact-overall-note]')?.value || '';
                const reviewNoteInput = document.getElementById('invoice-finalize-review-note');
                if (reviewNoteInput && overallNote) {
                    reviewNoteInput.value = overallNote;
                }

                form.submit();
            });

            // Revert Finalization Modal Handling
            const openRevertBtn = document.querySelectorAll('[data-open-revert-finalization]');
            const revertModal = document.querySelector('[data-revert-finalization-modal]');
            const closeRevertBtns = document.querySelectorAll('[data-close-revert-modal]');
            const revertForm = document.querySelector('[data-revert-finalization-form]');
            const revertReason = document.querySelector('[data-revert-reason]');
            const confirmRevertBtn = document.querySelector('[data-confirm-revert]');

            if (openRevertBtn.length > 0 && revertModal) {
                openRevertBtn.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        revertModal.classList.remove('hidden');
                        revertModal.classList.add('flex');
                        if (revertReason) {
                            revertReason.focus();
                        }
                    });
                });

                closeRevertBtns.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        revertModal.classList.add('hidden');
                        revertModal.classList.remove('flex');
                    });
                });

                if (revertForm) {
                    revertForm.addEventListener('submit', (e) => {
                        const reasonVal = (revertReason?.value || '').trim();
                        if (reasonVal.length < 3) {
                            e.preventDefault();
                            alert('Please provide a detailed reason (at least 3 characters) to revert finalization.');
                            revertReason?.focus();
                            return;
                        }

                        if (confirmRevertBtn) {
                            confirmRevertBtn.disabled = true;
                            confirmRevertBtn.textContent = 'Reverting...';
                        }
                    });
                }
            }

            // Move Back to In Transit Modal Handling
            const openMoveTransitBtns = document.querySelectorAll('[data-open-move-back-to-transit]');
            const moveTransitModal = document.querySelector('[data-move-transit-modal]');
            const closeMoveTransitBtns = document.querySelectorAll('[data-close-move-transit-modal]');
            const moveTransitForm = document.querySelector('[data-move-transit-form]');
            const moveTransitReason = document.querySelector('[data-move-transit-reason]');
            const confirmMoveTransitBtn = document.querySelector('[data-confirm-move-transit]');

            if (openMoveTransitBtns.length > 0 && moveTransitModal) {
                openMoveTransitBtns.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        moveTransitModal.classList.remove('hidden');
                        moveTransitModal.classList.add('flex');
                        if (moveTransitReason) {
                            moveTransitReason.focus();
                        }
                    });
                });

                closeMoveTransitBtns.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        moveTransitModal.classList.add('hidden');
                        moveTransitModal.classList.remove('flex');
                    });
                });

                if (moveTransitForm) {
                    moveTransitForm.addEventListener('submit', (e) => {
                        const reasonVal = (moveTransitReason?.value || '').trim();
                        if (reasonVal.length < 3) {
                            e.preventDefault();
                            alert('Please provide a detailed reason (at least 3 characters) to move back to in transit.');
                            moveTransitReason?.focus();
                            return;
                        }

                        if (confirmMoveTransitBtn) {
                            confirmMoveTransitBtn.disabled = true;
                            confirmMoveTransitBtn.textContent = 'Moving to In Transit...';
                        }
                    });
                }
            }
        });
    </script>
@endpush
