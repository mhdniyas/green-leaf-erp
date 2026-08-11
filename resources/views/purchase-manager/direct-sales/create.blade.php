@extends('purchase-manager.layouts.app')

@section('title', 'Direct Cash Sale')
@section('page_title', 'Direct Cash Sale')
@section('page_description', 'Create a direct sale order and send it through warehouse loadout.')

@section('page_actions')
    <a href="{{ route('warehouse.loadout.index', ['source' => 'direct', 'date' => $businessDate->format('Y-m-d')]) }}"
        class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50">
        View direct loadouts
    </a>
@endsection

@section('content')
    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-xs font-semibold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3.5 text-xs font-semibold text-red-800" data-items-error-banner>
                {{ $errors->first() }}
            </div>
        @endif

        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-600">Internal sales shop</p>
                    <h3 class="mt-1 text-xl font-black text-slate-950">{{ $shop->name }}</h3>
                    <p class="mt-1 text-sm font-semibold text-slate-600">
                        {{ $shop->code }} · Orders created here use <span class="font-mono text-emerald-700">direct_sale</span> source and continue to warehouse loadout.
                    </p>
                </div>
                <form method="GET" action="{{ route('purchasing.direct-sales.create') }}" class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2">
                    <label for="direct-sale-date" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Date</label>
                    <input type="date" id="direct-sale-date" name="date" value="{{ $businessDate->format('Y-m-d') }}" onchange="this.form.submit()" class="border-0 bg-transparent p-0 text-xs font-bold text-slate-700 focus:outline-none focus:ring-0">
                </form>
            </div>
        </section>

        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-4 md:grid-cols-3">
                <label class="grid gap-2 text-xs font-black uppercase tracking-[0.14em] text-slate-500">
                    Customer Name
                    <input form="shop-owner-order-form" type="text" name="customer_name" value="{{ old('customer_name') }}" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold normal-case tracking-normal text-slate-950 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" placeholder="Walk-in customer">
                </label>
                <label class="grid gap-2 text-xs font-black uppercase tracking-[0.14em] text-slate-500">
                    Phone
                    <input form="shop-owner-order-form" type="text" name="customer_phone" value="{{ old('customer_phone') }}" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold normal-case tracking-normal text-slate-950 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" placeholder="Optional">
                </label>
                <label class="grid gap-2 text-xs font-black uppercase tracking-[0.14em] text-slate-500">
                    Payment Method
                    <select form="shop-owner-order-form" name="payment_method" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold normal-case tracking-normal text-slate-950 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100">
                        <option value="cash" @selected(old('payment_method', 'cash') === 'cash')>Cash</option>
                        <option value="online_upi" @selected(old('payment_method') === 'online_upi')>Online / UPI</option>
                        <option value="card" @selected(old('payment_method') === 'card')>Card</option>
                    </select>
                </label>
            </div>
        </section>

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
            'adminSubmitLabel' => $adminSubmitLabel,
        ])
    </div>
@endsection
