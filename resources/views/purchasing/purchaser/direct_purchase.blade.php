<x-layouts.app title="Purchaser Add-ons">
    <div class="mx-auto w-full max-w-5xl space-y-4 px-3 py-4 sm:px-4 lg:px-6">
        @include('purchasing.purchaser.partials.feedback')

        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-sm">
            <div class="bg-[linear-gradient(135deg,_#0f172a_0%,_#111827_58%,_#115e59_100%)] px-3 py-2.5 sm:px-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-[9px] font-black uppercase tracking-[0.16em] text-emerald-200">Purchaser Flow</p>
                        <h1 class="truncate text-base font-black tracking-tight sm:text-lg">Add-on purchase demand</h1>
                    </div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-2 sm:flex sm:items-center">
                        <form method="GET" action="{{ route('purchaser.add-ons.create') }}" class="min-w-0">
                            <input type="date" name="date" value="{{ $businessDate->format('Y-m-d') }}" onchange="this.form.submit()" class="h-9 w-full rounded-lg border border-white/10 bg-white/10 px-3 text-xs font-bold text-white outline-none sm:w-40">
                        </form>
                        <a href="{{ route('purchaser.daily', ['date' => $businessDate->format('Y-m-d')]) }}" class="inline-flex h-9 items-center justify-center rounded-lg bg-white/10 px-3 text-xs font-black text-white transition hover:bg-white/15">
                            Daily
                        </a>
                    </div>
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
            'directPurchaseSubmitLabel' => 'Create Add-on Demand',
            'rowSelectionLabel' => 'Selected for add-on demand',
        ])
    </div>
</x-layouts.app>
