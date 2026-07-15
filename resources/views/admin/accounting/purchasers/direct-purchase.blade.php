<x-layouts.accounting title="Green Leaf Direct Purchase">
    <div class="mx-auto w-full max-w-5xl space-y-5 px-4 py-5 lg:px-6">
        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-emerald-700">Green Leaf Direct Purchase</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Admin purchase order</h1>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Create approved demand directly for the purchaser flow. No shop approval is required.</p>
                </div>
                <form method="GET" action="{{ route('admin.accounting.purchasers.direct-purchase.create') }}" class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-2">
                    <label class="rounded-xl bg-white px-3 py-2 text-slate-900 shadow-sm">
                        <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Business Date</span>
                        <input type="date" name="date" value="{{ $businessDate->format('Y-m-d') }}" onchange="this.form.submit()" class="mt-1 border-0 bg-transparent p-0 text-sm font-black focus:outline-none focus:ring-0">
                    </label>
                    <a href="{{ route('admin.accounting.purchasers.index') }}" class="inline-flex h-11 items-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black uppercase tracking-[0.14em] text-slate-700 transition hover:bg-slate-50">
                        Purchasers
                    </a>
                </form>
            </div>
        </section>

        <section class="grid gap-3 md:grid-cols-3">
            <a href="{{ route('purchaser.daily', ['date' => $businessDate->format('Y-m-d')]) }}" class="rounded-[1.5rem] border border-cyan-200 bg-cyan-50 p-4 text-cyan-900 transition hover:border-cyan-300 hover:bg-cyan-100">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-cyan-700">Next</p>
                <p class="mt-2 text-sm font-black">Open Purchaser Daily</p>
                <p class="mt-1 text-xs font-semibold leading-5 text-cyan-800/80">Add the direct demand into a vendor cart.</p>
            </a>
            <a href="{{ route('purchaser.vendors', ['date' => $businessDate->format('Y-m-d')]) }}" class="rounded-[1.5rem] border border-emerald-200 bg-emerald-50 p-4 text-emerald-900 transition hover:border-emerald-300 hover:bg-emerald-100">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Cart</p>
                <p class="mt-2 text-sm font-black">Vendor Carts</p>
                <p class="mt-1 text-xs font-semibold leading-5 text-emerald-800/80">Submit the bill and payment details.</p>
            </a>
            <a href="{{ route('purchasing.invoices.index', ['date' => $businessDate->format('Y-m-d')]) }}" class="rounded-[1.5rem] border border-slate-200 bg-white p-4 text-slate-900 transition hover:border-slate-300 hover:bg-slate-50">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Invoice</p>
                <p class="mt-2 text-sm font-black">Purchase Invoices</p>
                <p class="mt-1 text-xs font-semibold leading-5 text-slate-500">Paid direct invoices appear in cash flow.</p>
            </a>
        </section>

        @include('shop-owner.orders.partials.order-form', [
            'productsByCategory' => $productsByCategory,
            'frequentProducts' => $frequentProducts,
            'tomorrowOrder' => $tomorrowOrder,
            'yesterdayOrder' => $yesterdayOrder,
            'presets' => $presets,
            'tomorrowDate' => $tomorrowDate,
            'cutoffPassed' => $cutoffPassed,
            'isUpdateRequest' => false,
            'purchaseOrdersLockedForTomorrow' => $purchaseOrdersLockedForTomorrow,
            'orderFormAction' => $orderFormAction,
            'orderFormMode' => $orderFormMode,
            'allowPresetSave' => $allowPresetSave,
        ])
    </div>
</x-layouts.accounting>
