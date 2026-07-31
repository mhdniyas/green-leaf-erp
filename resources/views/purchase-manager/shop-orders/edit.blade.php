@extends('purchase-manager.layouts.app')

@section('title', $shop->name.' — Shop Order')
@section('page_title', $shop->name)
@section('page_description', 'Marketplace editor for '.$businessDate->format('d F Y').'. Add products and update quantities, then save the shop order.')

@section('page_actions')
    <a href="{{ route('purchasing.shop-orders.index', ['date' => $businessDate->format('Y-m-d')]) }}"
        class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50">
        Back to shop list
    </a>
@endsection

@section('content')
    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-xs font-semibold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3.5 text-xs font-semibold text-red-800">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3.5 text-xs font-semibold text-red-800" data-items-error-banner>
                {{ $errors->first() }}
            </div>
        @endif

        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Shop</p>
                    <h3 class="mt-1 text-xl font-black text-slate-950">{{ $shop->name }}</h3>
                    <p class="mt-1 text-sm font-semibold text-slate-600">
                        {{ $shop->code }}
                        @if ($tomorrowOrder)
                            · <span class="font-mono text-teal-700">{{ $tomorrowOrder->order_number }}</span>
                            · {{ $tomorrowOrder->displayStateLabel() }}
                        @else
                            · No order yet for this date
                        @endif
                    </p>
                </div>
                <form method="GET" action="{{ route('purchasing.shop-orders.edit', $shop) }}" class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2">
                    <label for="edit-date" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Date</label>
                    <input type="date" id="edit-date" name="date" value="{{ $businessDate->format('Y-m-d') }}" onchange="this.form.submit()" class="border-0 bg-transparent p-0 text-xs font-bold text-slate-700 focus:outline-none focus:ring-0">
                </form>
            </div>
        </section>

        @if (! $canEdit)
            <div class="rounded-[2rem] border border-rose-200 bg-rose-50 p-5">
                <h3 class="text-base font-black text-rose-900">Order locked</h3>
                <p class="mt-2 text-sm text-rose-800">{{ $lockReason ?? 'This shop order can no longer be edited.' }}</p>
            </div>

            @if ($tomorrowOrder)
                <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 bg-slate-50/50 px-5 py-4">
                        <h3 class="text-sm font-black uppercase tracking-wider text-slate-800">Current items</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                    <th class="px-5 py-3">Product</th>
                                    <th class="px-5 py-3 text-right">Requested</th>
                                    <th class="px-5 py-3 text-right">Approved</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($tomorrowOrder->items as $item)
                                    <tr>
                                        <td class="px-5 py-3 font-bold text-slate-900">{{ $item->product?->name ?? 'Product' }}</td>
                                        <td class="px-5 py-3 text-right font-semibold text-slate-700">{{ number_format((float) $item->requested_qty, 2) }}</td>
                                        <td class="px-5 py-3 text-right font-semibold text-slate-700">{{ number_format((float) ($item->approved_qty ?? 0), 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-5 py-6 text-center text-sm font-semibold text-slate-500">No items on this order.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        @else
            @include('shop-owner.orders.partials.order-form', [
                'productsByCategory' => $productsByCategory,
                'frequentProducts' => $frequentProducts,
                'tomorrowOrder' => $tomorrowOrder,
                'yesterdayOrder' => $yesterdayOrder,
                'presets' => $presets,
                'tomorrowDate' => $tomorrowDate,
                'cutoffPassed' => false,
                'isUpdateRequest' => false,
                'purchaseOrdersLockedForTomorrow' => false,
                'orderFormAction' => $orderFormAction,
                'orderFormMode' => $orderFormMode,
                'allowPresetSave' => $allowPresetSave,
                'directPurchaseTitle' => $directPurchaseTitle,
                'directPurchaseDescription' => $directPurchaseDescription,
                'adminSubmitLabel' => $tomorrowOrder ? 'Update Order' : 'Create Order',
            ])
        @endif
    </div>
@endsection
