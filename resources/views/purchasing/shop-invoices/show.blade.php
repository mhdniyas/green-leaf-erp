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
        $canEditBill = ! $isFinalized && (auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('purchase') || auth()->user()?->hasRole('purchaser'));
        $canEditPrices = $canEditBill && (auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('purchase') || auth()->user()?->hasRole('purchaser'));
        $canEditDiscount = $canManageInvoiceMoney && ! $isFinalized && ! $isDeliveryReviewPending;
        $formatUnit = fn (?string $unit): string => \App\Models\ProductUnit::normalizeUnit($unit) === 'piece'
            ? 'PCE'
            : strtoupper(str_replace('_', ' ', \App\Models\ProductUnit::normalizeUnit($unit)));
        $money = fn (float|int|string|null $amount): string => 'Rs. '.number_format((float) $amount, 2);
        $lineQuantity = function ($item): float {
            return round((float) ($item->delivered_price_quantity ?: $item->price_quantity ?: $item->delivered_qty ?: $item->approved_qty), 4);
        };
        $finalQuantity = function ($item) use ($lineQuantity): float {
            return round((float) ($item->orderItem?->shop_reported_received_qty ?: $item->delivered_qty ?: $item->orderItem?->delivered_qty ?: $lineQuantity($item)), 4);
        };
        $lineAmount = fn ($item): float => round((float) ($item->final_line_total ?: $item->line_subtotal), 2);
        $discountTotal = round((float) $invoice->discount_total, 2);
        $subtotal = round((float) $invoice->subtotal, 2);
        $finalTotal = round((float) $invoice->final_total, 2);
        $invoiceStatus = $isFinalized ? 'FINALIZED' : strtoupper(str_replace('_', ' ', (string) $invoice->status));
        $reviewAction = $invoice->order
            ? ($isDeliveryReviewPending
                ? route('requisitions.delivery.approve', $invoice->order->order_number)
                : route('purchasing.shop-invoices.finalize-on-behalf', $invoice))
            : null;
        $latestActivities = ($activities ?? collect())->take(5);
        $remainingActivities = max(0, ($activities ?? collect())->count() - $latestActivities->count());
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
                            <span class="w-fit rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-black uppercase tracking-[0.14em] text-slate-700">
                                {{ $invoiceStatus }}
                            </span>
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
                        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm font-semibold text-emerald-900">
                            FINALIZED
                            @if ($invoice->finalizedBy)
                                by {{ $invoice->finalizedBy->name }}
                            @endif
                            @if ($invoice->finalized_at)
                                at {{ $invoice->finalized_at->format('d M Y, h:i A') }}
                            @endif
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
                            @endphp

                            @if ($canEditBill)
                                <button
                                    type="button"
                                    class="grid w-full grid-cols-[minmax(0,1fr)_auto] gap-2 px-3 py-3 text-left transition hover:bg-cyan-50 focus:bg-cyan-50 focus:outline-hidden sm:grid-cols-[minmax(0,1fr)_5rem_4rem_6rem_7rem] sm:gap-3"
                                    data-item-edit
                                    data-item-id="{{ $item->shop_order_item_id }}"
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
                            @elseif (($canFinalize ?? false) && $reviewAction)
                                <button type="button" class="inline-flex h-11 w-full items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-black text-white hover:bg-slate-800 sm:w-auto" data-open-finalize>
                                    Finalize Invoice
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-1 border-b border-slate-100 pb-3">
                <h3 class="text-sm font-black uppercase tracking-[0.16em] text-slate-950">Notes & Changes</h3>
                <p class="text-sm font-semibold text-slate-600">Overall Note: {{ $overallNote ?: 'No overall note recorded.' }}</p>
            </div>

            <div class="mt-3 space-y-2">
                @forelse ($latestActivities as $activity)
                    <div class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">
                        <span class="font-black text-slate-950">{{ optional($activity->created_at)->format('d M H:i') }}</span>
                        <span class="font-semibold"> - {{ $activity->causer?->name ?? 'System' }}</span>
                        <span class="block text-xs font-semibold text-slate-500">{{ $activity->description }}</span>
                    </div>
                @empty
                    <p class="text-sm font-semibold text-slate-500">No change history recorded.</p>
                @endforelse
            </div>

            @if ($remainingActivities > 0)
                <details class="mt-3">
                    <summary class="cursor-pointer text-sm font-black text-cyan-700">View Full History</summary>
                    <div class="mt-2 space-y-2">
                        @foreach (($activities ?? collect())->skip(5) as $activity)
                            <div class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                <span class="font-black text-slate-950">{{ optional($activity->created_at)->format('d M H:i') }}</span>
                                <span class="font-semibold"> - {{ $activity->causer?->name ?? 'System' }}</span>
                                <span class="block text-xs font-semibold text-slate-500">{{ $activity->description }}</span>
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
                    $inventoryAction = $formQty < (float) ($item->orderItem?->loaded_qty ?? $item->delivered_qty ?? $formQty) ? 'add_back' : 'none';
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
        <div class="fixed inset-0 z-50 hidden items-end bg-slate-950/50 p-0 sm:items-center sm:p-4" data-finalize-modal>
            <div class="w-full rounded-t-lg bg-white p-4 shadow-xl sm:mx-auto sm:max-w-md sm:rounded-lg sm:p-5">
                <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Finalize Invoice</p>
                <h3 class="mt-1 text-lg font-black text-slate-950">{{ $invoice->invoice_number }}</h3>
                @if (! ($shopSubmitted ?? false))
                    <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-900">Shop not submitted. Admin finalization needs a note.</p>
                @endif
                <label class="mt-4 block">
                    <span class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Overall Note / Reason</span>
                    <textarea rows="3" data-finalize-note class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-950 focus:border-cyan-500 focus:ring-cyan-500" placeholder="Shop quantity confirmed manually."></textarea>
                </label>
                <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-black text-slate-700" data-close-finalize>Cancel</button>
                    <button type="button" class="inline-flex h-11 items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-black text-white" data-confirm-finalize>Finalize Invoice</button>
                </div>
            </div>
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
                const finalQty = Number(qtyInput.value || 0);
                const finalPrice = Number(priceInput.value || 0);
                const qtyDiff = finalQty - originalQty;
                const amountDiff = (finalQty * finalPrice) - (originalQty * originalPrice);

                itemModal.querySelector('[data-item-difference]').textContent = `Qty: ${trimNumber(qtyDiff)} ${activeItemButton.dataset.unit || ''}. Amount: ${money(amountDiff)}.`;
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
                const qtyInput = document.getElementById(`approved-delivered-qty-${activeItemButton.dataset.itemId}`);

                if (qtyInput) {
                    qtyInput.value = finalQty;
                    activeItemButton.dataset.finalQty = finalQty;
                    activeItemButton.querySelectorAll('[data-row-qty]').forEach((node) => node.textContent = trimNumber(finalQty));
                }

                const priceForm = document.getElementById('invoice-price-form');
                const originalPrice = Number(activeItemButton.dataset.originalPrice || 0);
                const newPrice = Number(finalPrice || 0);

                if (priceForm && activeItemButton.dataset.productId && newPrice > 0 && Math.abs(newPrice - originalPrice) > 0.0001) {
                    document.getElementById('invoice-price-product-id').value = activeItemButton.dataset.productId;
                    document.getElementById('invoice-price-selling-price').value = finalPrice;
                    document.getElementById('invoice-price-price-unit').value = activeItemButton.dataset.priceUnit || activeItemButton.dataset.unit || '';

                    const response = await fetch(priceForm.action, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            prices: [{
                                product_id: Number(activeItemButton.dataset.productId),
                                selling_price: newPrice,
                                price_unit: activeItemButton.dataset.priceUnit || activeItemButton.dataset.unit || '',
                                reason: 'Invoice item price edit',
                            }],
                        }),
                    });

                    if (!response.ok) {
                        window.location.reload();

                        return;
                    }

                    activeItemButton.dataset.finalPrice = finalPrice;
                    activeItemButton.querySelectorAll('[data-row-price]').forEach((node) => node.textContent = money(newPrice));
                }

                const amount = Number(finalQty || 0) * Number(finalPrice || 0);
                activeItemButton.querySelectorAll('[data-row-amount]').forEach((node) => node.textContent = money(amount));
                closeItemModal();
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
            document.querySelector('[data-open-finalize]')?.addEventListener('click', () => {
                finalizeModal?.classList.remove('hidden');
                finalizeModal?.classList.add('flex');
            });
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
        });
    </script>
@endpush
