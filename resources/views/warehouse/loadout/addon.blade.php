<x-layouts.app title="Add Product — {{ $shopOrder->loadoutDisplayName() }}">
    <div class="mx-auto flex w-full max-w-xl min-w-0 flex-col gap-4 py-3 pb-20 lg:px-4 lg:py-4">
        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-[0_12px_28px_rgba(15,23,42,0.16)]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(99,102,241,0.25),_transparent_36%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#312e81_100%)] px-4 py-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <a href="{{ route('warehouse.loadout.show', $shopOrder) }}"
                           class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white hover:bg-white/20 transition-all border border-white/10 text-decoration-none">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                        </a>
                        <div>
                            <h1 class="text-base font-black tracking-tight text-white">Add Product</h1>
                            <p class="text-[9px] font-semibold text-indigo-300">
                                {{ $shopOrder->loadoutDisplayName() }} &middot; {{ $shopOrder->business_date->format('d M Y') }}
                            </p>
                        </div>
                    </div>
                    <span class="rounded-full border border-indigo-300/30 bg-indigo-400/20 px-2.5 py-1 text-[9px] font-black uppercase tracking-wider text-indigo-200">
                        Addon
                    </span>
                </div>
            </div>
        </section>

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 space-y-1">
                @foreach($errors->all() as $error)
                    <p class="text-xs font-semibold text-rose-700">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST"
              action="{{ route('warehouse.loadout.addon.store', $shopOrder) }}"
              class="space-y-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            @csrf

            <div>
                <label for="product_id" class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Product</label>
                <select id="product_id"
                        name="product_id"
                        required
                        class="h-12 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-black text-slate-800 focus:border-indigo-500 focus:bg-white focus:outline-none">
                    <option value="">Select product</option>
                    @foreach($productsByCategory as $category)
                        <optgroup label="{{ $category->name }}">
                            @foreach($category->products as $product)
                                <option value="{{ $product->id }}" @selected((int) old('product_id') === (int) $product->id)>
                                    {{ $product->sku ? '#'.$product->sku.' - ' : '' }}{{ $product->name }} ({{ strtoupper($product->unit ?: 'KG') }})
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="quantity" class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Approved Quantity</label>
                <input id="quantity"
                       name="quantity"
                       type="number"
                       min="0.01"
                       step="any"
                       inputmode="decimal"
                       value="{{ old('quantity') }}"
                       required
                       class="h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-black text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none"
                       placeholder="Enter quantity">
            </div>

            <div class="flex items-center gap-2 pt-2">
                <a href="{{ route('warehouse.loadout.show', $shopOrder) }}"
                   class="inline-flex h-11 flex-1 items-center justify-center rounded-xl border border-slate-200 px-4 text-xs font-black uppercase tracking-wider text-slate-600 transition-colors hover:bg-slate-50 text-decoration-none">
                    Cancel
                </a>
                <button type="submit"
                        class="inline-flex h-11 flex-1 items-center justify-center rounded-xl bg-indigo-600 px-4 text-xs font-black uppercase tracking-wider text-white transition-colors hover:bg-indigo-700 border-none cursor-pointer">
                    Add to Order
                </button>
            </div>
        </form>
    </div>
</x-layouts.app>
