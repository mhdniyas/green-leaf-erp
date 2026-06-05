<div class="rounded-3xl border border-slate-200 bg-slate-50 px-4 py-4">
    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Custom Lists</p>

    @if ($presets->isNotEmpty())
        <div class="mt-3 flex flex-col gap-3 md:flex-row md:items-center">
            <select
                data-preset-select
                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none"
            >
                <option value="">Load saved custom list</option>
                @foreach ($presets as $preset)
                    <option value="{{ $preset->id }}">{{ $preset->name }}</option>
                @endforeach
            </select>
            <button type="button" data-apply-preset class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-800">
                Load List
            </button>
        </div>
    @else
        <p class="mt-3 text-sm text-slate-600">No custom lists saved yet. Build one below and save it for repeat daily ordering.</p>
    @endif

    <form method="POST" action="{{ route('requisitions.presets.store') }}" data-save-preset-form class="mt-4 flex flex-col gap-3 md:flex-row md:items-center">
        @csrf
        <input type="hidden" name="redirect_to" value="shop-owner-orders-create">
        <input
            type="text"
            name="name"
            value="{{ old('name') }}"
            placeholder="Save current quantities as custom list"
            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none"
        >
        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">
            Save List
        </button>
    </form>

    <p class="mt-3 max-w-sm text-xs text-slate-500">Load a saved list into the form, adjust the final quantities, and submit the order. Fulfillment type is finalized during approval.</p>

    <script id="shop-owner-presets-data" type="application/json">
        {!! $presets->map(fn ($preset) => [
            'id' => $preset->id,
            'name' => $preset->name,
            'items' => $preset->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => (float) $item->quantity,
            ])->values()->all(),
        ])->values()->toJson() !!}
    </script>
</div>
