@php
    $invoiceDate = $invoice->business_date->toDateString();
    $isFinalized = $isFinalized ?? $invoice->isFinalized();
    $canEditBill = $canEditBill ?? false;
    $canEditDiscount = $canEditDiscount ?? false;
    $isPdf = $isPdf ?? false;

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
    $items = $items ?? $invoice->items;
    $discountTotal = $discountTotal ?? round((float) $invoice->discount_total, 2);
    $subtotal = $subtotal ?? round((float) $invoice->subtotal, 2);
    $finalTotal = $finalTotal ?? round((float) $invoice->final_total, 2);
    $invoiceStatus = $isFinalized ? 'FINALIZED' : strtoupper(str_replace('_', ' ', (string) $invoice->status));

    $itemChanges = $itemChanges ?? collect();
    $changeLines = $changeLines ?? function ($activity) use ($money): array {
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
@endphp

<section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm print:border-slate-300 print:shadow-none">
    <div class="mx-auto max-w-[46rem] px-3 py-5 sm:px-6 sm:py-7">
        <div class="border-b-2 border-dashed border-slate-200 pb-4 print:border-slate-300">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Shop Bill</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950 sm:text-2xl">{{ $invoice->shop?->name ?? 'Shop' }}</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-600">{{ $invoice->shop?->code ?? $invoice->shop?->warehouse_tag }}</p>
                </div>
                <div class="flex flex-col gap-2 sm:items-end">
                    @if ($isFinalized)
                        <span class="w-fit rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-black uppercase tracking-[0.14em] text-emerald-700 print:border-emerald-300 print:bg-emerald-50">
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
                <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm text-emerald-950 print:border-emerald-300">
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

        <div class="mt-4 overflow-hidden rounded-lg border border-slate-200 print:border-slate-300">
            <div class="hidden grid-cols-[2.5rem_minmax(0,1fr)_5rem_4rem_6rem_7rem] gap-3 border-b border-slate-200 bg-slate-50 px-3 py-2 text-[10px] font-black uppercase tracking-[0.14em] text-slate-500 sm:grid print:grid print:bg-slate-100">
                <div>Sl No</div>
                <div>Product</div>
                <div class="text-right">Qty</div>
                <div>Unit</div>
                <div class="text-right">Price</div>
                <div class="text-right">Amount</div>
            </div>

            <div class="divide-y divide-slate-100 print:divide-slate-200">
                @foreach ($items as $item)
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

                    @if ($canEditBill && ! $isPdf)
                        <button
                            type="button"
                            class="grid w-full grid-cols-[minmax(0,1fr)_auto] gap-2 px-3 py-3 text-left transition hover:bg-cyan-50 focus:bg-cyan-50 focus:outline-hidden sm:grid-cols-[2.5rem_minmax(0,1fr)_5rem_4rem_6rem_7rem] sm:gap-3"
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
                            <span class="hidden text-xs font-bold text-slate-500 sm:block">{{ $loop->iteration }}</span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-black text-slate-950">{{ $productName }}</span>
                                <span class="mt-1 block text-xs font-semibold text-slate-500 sm:hidden">#{{ $loop->iteration }} · {{ $displayQty }} {{ $formatUnit($item->unit) }} at {{ $money($price) }}</span>
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
                        <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-2 px-3 py-3 text-left sm:grid-cols-[2.5rem_minmax(0,1fr)_5rem_4rem_6rem_7rem] sm:gap-3 print:grid-cols-[2.5rem_minmax(0,1fr)_5rem_4rem_6rem_7rem] print:gap-3">
                            <span class="hidden text-xs font-bold text-slate-500 sm:block print:block">{{ $loop->iteration }}</span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-black text-slate-950">{{ $productName }}</span>
                                <span class="mt-1 block text-xs font-semibold text-slate-500 sm:hidden print:hidden">#{{ $loop->iteration }} · {{ $displayQty }} {{ $formatUnit($item->unit) }} at {{ $money($price) }}</span>
                                @if ($itemChangeLines !== [])
                                    <span class="mt-1 block text-xs font-bold text-amber-700" data-change-product="{{ $productName }}">Changed: {{ implode(' | ', $itemChangeLines) }}</span>
                                @endif
                            </span>
                            <span class="text-right text-sm font-black text-slate-950 sm:hidden print:hidden">{{ $money($lineTotal) }}</span>
                            <span class="hidden text-right text-sm font-bold text-slate-800 sm:block print:block">{{ $displayQty }}</span>
                            <span class="hidden text-sm font-bold text-slate-600 sm:block print:block">{{ $formatUnit($item->unit) }}</span>
                            <span class="hidden text-right text-sm font-bold text-slate-800 sm:block print:block">{{ $money($price) }}</span>
                            <span class="hidden text-right text-sm font-black text-slate-950 sm:block print:block">{{ $money($lineTotal) }}</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="mt-5 border-t-2 border-dashed border-slate-200 pt-4 print:border-slate-300">
            <div class="ml-auto max-w-sm space-y-2">
                <div class="flex items-center justify-between text-sm font-bold text-slate-700">
                    <span>Subtotal</span>
                    <span>{{ $money($subtotal) }}</span>
                </div>
                <div class="flex items-center justify-between text-sm font-bold text-slate-700">
                    <span class="flex items-center gap-2">
                        Discount
                        @if ($canEditDiscount && ! $isPdf)
                            <button type="button" class="text-xs font-black text-cyan-700 hover:text-cyan-900" data-open-discount>
                                Edit Discount
                            </button>
                        @endif
                    </span>
                    <span>{{ $money($discountTotal) }}</span>
                </div>
                <div class="border-t border-slate-200 pt-3 print:border-slate-300">
                    <div class="flex items-center justify-between text-lg font-black text-slate-950">
                        <span>Final Total</span>
                        <span>{{ $money($finalTotal) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
