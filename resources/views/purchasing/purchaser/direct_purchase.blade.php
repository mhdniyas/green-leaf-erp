<x-layouts.app title="Purchaser Add-ons">
    <div class="mx-auto w-full max-w-5xl space-y-4 px-3 py-4 sm:px-4 lg:px-6">
        @include('purchasing.purchaser.partials.feedback')

        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-sm lg:rounded-[2rem]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.24),_transparent_34%),linear-gradient(135deg,_#0f172a_0%,_#111827_58%,_#115e59_100%)] px-4 py-4 sm:px-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-200">Purchaser Flow</p>
                        <h1 class="mt-1 text-xl font-black tracking-tight sm:text-2xl">Add-on purchase demand</h1>
                        <p class="mt-1.5 max-w-2xl text-sm font-semibold leading-6 text-slate-200">Create approved demand outside normal shop orders. It will appear in daily demand for vendor cart assignment.</p>
                    </div>
                    <form method="GET" action="{{ route('purchaser.add-ons.create') }}" class="w-full sm:w-auto">
                        <label class="block text-[10px] font-black uppercase tracking-[0.16em] text-emerald-100">Business Date</label>
                        <input type="date" name="date" value="{{ $businessDate->format('Y-m-d') }}" onchange="this.form.submit()" class="mt-1.5 h-11 w-full rounded-xl border border-white/10 bg-white/10 px-3 text-sm font-bold text-white outline-none sm:w-48">
                    </form>
                </div>

                <div class="mt-4 grid gap-2 sm:grid-cols-3">
                    <a href="{{ route('purchaser.daily', ['date' => $businessDate->format('Y-m-d')]) }}" class="rounded-xl bg-white/10 px-4 py-3 transition hover:bg-white/15">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-200">Next</p>
                        <p class="mt-1 text-sm font-black">Open Daily Demand</p>
                    </a>
                    <a href="{{ route('purchaser.vendors', ['date' => $businessDate->format('Y-m-d')]) }}" class="rounded-xl bg-white/10 px-4 py-3 transition hover:bg-white/15">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-200">Cart</p>
                        <p class="mt-1 text-sm font-black">Vendor Carts</p>
                    </a>
                    <a href="{{ route('purchaser.shop-orders.index', ['date' => $businessDate->format('Y-m-d'), 'source' => 'admin_direct_purchase']) }}" class="rounded-xl bg-white/10 px-4 py-3 transition hover:bg-white/15">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-200">Reference</p>
                        <p class="mt-1 text-sm font-black">Direct Orders</p>
                    </a>
                </div>
            </div>
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
            'directPurchaseTitle' => 'Purchaser Add-on',
            'directPurchaseDescription' => 'Select products and quantities. The add-on will be approved immediately and shown in purchaser daily demand.',
        ])
    </div>
</x-layouts.app>
