<x-layouts.app title="B Grade Prices">
    @php
        $pageParams = array_filter(['date' => $date, 'search' => $searchQuery]);
    @endphp

    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-2 lg:max-w-4xl lg:px-4 lg:py-3">
        @include('purchasing.purchaser.partials.feedback')

        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-md">
            <div class="bg-[linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#1e40af_100%)] px-4 py-3 sm:px-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-[0.2em] text-blue-300">Purchaser Pricing</p>
                        <h1 class="text-base font-black tracking-tight text-white sm:text-lg">B Grade Prices</h1>
                        <p class="mt-0.5 text-[10px] font-semibold text-slate-300">Buying prices only. Shop selling prices remain unchanged.</p>
                    </div>
                    <form action="{{ route('purchaser.purchase-grade-prices.index') }}" method="GET">
                        @if ($searchQuery !== '')
                            <input type="hidden" name="search" value="{{ $searchQuery }}">
                        @endif
                        @if ($selectedCategory !== '')
                            <input type="hidden" name="category_id" value="{{ $selectedCategory }}">
                        @endif
                        <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="h-8 cursor-pointer rounded-full border border-white/20 bg-white/10 px-3 text-[11px] font-bold text-blue-100 focus:bg-white/20 focus:outline-none focus:ring-2 focus:ring-blue-400/30">
                    </form>
                </div>
            </div>
        </section>

        <form action="{{ route('purchaser.purchase-grade-prices.index') }}" method="GET" class="flex flex-col gap-2 rounded-xl border border-slate-200/80 bg-white p-2 shadow-2xs">
            <input type="hidden" name="date" value="{{ $date }}">
            <div class="flex min-w-0 gap-2">
                <div class="relative flex-1">
                    <svg class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                    <input type="search" name="search" value="{{ $searchQuery }}" placeholder="Search product name or SKU..." class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50/80 pl-9 pr-3 text-xs font-bold text-slate-900 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-3 focus:ring-blue-500/10">
                </div>
                @if ($categories->isNotEmpty())
                    <select name="category_id" onchange="this.form.submit()" class="h-9 max-w-[130px] shrink-0 cursor-pointer rounded-lg border border-slate-200 bg-slate-50/80 px-2.5 text-xs font-bold text-slate-700 focus:border-blue-500 focus:outline-none sm:max-w-[170px]">
                        <option value="all">All Categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" {{ (string) $selectedCategory === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                @endif
                <button class="h-9 shrink-0 rounded-xl bg-slate-950 px-4 text-xs font-black text-white hover:bg-slate-800">Search</button>
            </div>

            @if ($categories->isNotEmpty())
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs">
                    <a href="{{ route('purchaser.purchase-grade-prices.index', $pageParams + ['category_id' => 'all']) }}" class="shrink-0 rounded-lg px-2.5 py-1 text-[11px] font-bold {{ $selectedCategory === '' || $selectedCategory === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">All</a>
                    @foreach ($categories as $category)
                        <a href="{{ route('purchaser.purchase-grade-prices.index', $pageParams + ['category_id' => $category->id]) }}" class="shrink-0 rounded-lg px-2.5 py-1 text-[11px] font-bold {{ (string) $selectedCategory === (string) $category->id ? 'bg-blue-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">{{ $category->name }}</a>
                    @endforeach
                </div>
            @endif
        </form>

        <form method="POST" action="{{ route('purchaser.purchase-grade-prices.copy-a-to-b') }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-blue-200 bg-blue-50 p-3 shadow-2xs">
            @csrf
            <input type="hidden" name="business_date" value="{{ $date }}">
            <div>
                <p class="text-xs font-black text-blue-950">Fetch today’s Grade A prices</p>
                <p class="text-[10px] font-semibold text-blue-700">Load approved Grade A values from Daily Prices into this date’s Grade B prices.</p>
            </div>
            <button class="h-9 rounded-xl bg-blue-700 px-4 text-xs font-black text-white hover:bg-blue-800">Fetch A → B</button>
        </form>

        <form method="POST" action="{{ route('purchaser.purchase-grade-prices.update') }}" class="flex flex-col gap-2">
            @csrf
            <input type="hidden" name="business_date" value="{{ $date }}">

            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2">
                <div>
                    <p class="text-xs font-black text-blue-950">Grade B purchase prices for {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</p>
                    <p class="text-[10px] font-semibold text-blue-700">Enter prices and save. Blank rows remain unchanged.</p>
                </div>
                <button class="h-9 rounded-xl bg-blue-700 px-4 text-xs font-black text-white hover:bg-blue-800">Save Prices</button>
            </div>

            @if ($products->isEmpty())
                <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-xs font-bold text-slate-500">No products found. Try adjusting the search, category, or date.</div>
            @else
                <div class="grid grid-cols-[minmax(90px,1fr)_40px_48px_72px] items-center gap-1 px-2 pb-1 text-[9px] font-black uppercase tracking-[0.1em] text-slate-400 sm:grid-cols-[minmax(105px,1.25fr)_64px_86px_88px] sm:gap-1.5 sm:px-2.5 sm:text-[10px] sm:tracking-[0.14em]">
                    <div>Product</div>
                    <div class="text-right">Prev</div>
                    <div class="text-center">Change</div>
                    <div class="text-right">{{ strtoupper(\Illuminate\Support\Carbon::parse($date)->format('d M')) }}</div>
                </div>

                <div class="flex flex-col gap-1.5">
                    @foreach ($products as $product)
                        <div class="grid min-h-14 grid-cols-[minmax(90px,1fr)_40px_48px_72px] items-center gap-1 overflow-hidden rounded-xl border border-slate-200 bg-white px-2 py-2 transition hover:border-slate-300 sm:grid-cols-[minmax(105px,1.25fr)_64px_86px_88px] sm:gap-1.5 sm:px-2.5">
                            <div class="min-w-0">
                                <div class="flex min-w-0 items-center gap-1">
                                    <p class="truncate text-xs font-bold text-slate-900 sm:text-[13px]">{{ $product['name'] }}</p>
                                    <span class="shrink-0 text-[9px] font-semibold text-slate-400 sm:text-[10px]">· {{ $product['unit'] }}</span>
                                </div>
                                <p class="truncate text-[10px] font-semibold text-slate-400">{{ $product['sku'] ?: 'No SKU' }}</p>
                                @if ($product['updated_by'])
                                    <p class="truncate text-[10px] font-semibold text-blue-700">{{ $product['updated_by'] }} · {{ $product['updated_time'] }}</p>
                                @endif
                            </div>
                            <div class="truncate text-right text-[11px] font-semibold text-slate-500 sm:text-xs">
                                {{ $product['previous_price'] ? '₹'.rtrim(rtrim(number_format($product['previous_price'], 2, '.', ''), '0'), '.') : '—' }}
                            </div>
                            <div class="text-center">
                                @if ($product['today_price'] === null)
                                    <span class="inline-flex rounded-md border border-amber-200 bg-amber-50 px-1 py-1 text-[9px] font-bold text-amber-700 sm:px-1.5 sm:text-[10px]">Unset</span>
                                @elseif ($product['difference'] === null)
                                    <span class="inline-flex rounded-md border border-emerald-200 bg-emerald-50 px-1 py-1 text-[9px] font-bold text-emerald-700 sm:px-1.5 sm:text-[10px]">New</span>
                                @elseif ($product['difference'] > 0)
                                    <span class="inline-flex rounded-md border border-emerald-200 bg-emerald-50 px-1 py-1 text-[9px] font-bold text-emerald-700 sm:px-1.5 sm:text-[10px]">▲ {{ $product['difference_percentage'] }}%</span>
                                @elseif ($product['difference'] < 0)
                                    <span class="inline-flex rounded-md border border-rose-200 bg-rose-50 px-1 py-1 text-[9px] font-bold text-rose-700 sm:px-1.5 sm:text-[10px]">▼ {{ abs($product['difference_percentage']) }}%</span>
                                @else
                                    <span class="inline-flex rounded-md border border-slate-200 bg-slate-50 px-1 py-1 text-[9px] font-bold text-slate-500 sm:px-1.5 sm:text-[10px]">— 0%</span>
                                @endif
                            </div>
                            <label class="flex h-9 items-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50 focus-within:border-blue-600 focus-within:bg-white focus-within:ring-3 focus-within:ring-blue-500/10">
                                <span class="pl-1.5 text-[11px] font-bold text-slate-400 sm:pl-2 sm:text-xs">₹</span>
                                <input type="number" step="0.0001" min="0.0001" name="prices[{{ $product['id'] }}][B]" value="{{ old('prices.'.$product['id'].'.B', $product['today_price']) }}" placeholder="—" oninput="queueGradeBPriceSave(this, {{ $product['id'] }})" class="min-w-0 flex-1 border-0 bg-transparent px-1 text-right text-xs font-bold text-slate-900 outline-none sm:px-1.5 sm:text-[13px]">
                            </label>
                        </div>
                    @endforeach
                </div>
            @endif
        </form>

    </div>

    @push('scripts')
        <script>
            const gradeBPriceTimers = {};

            function queueGradeBPriceSave(input, productId) {
                window.clearTimeout(gradeBPriceTimers[productId]);
                const price = Number.parseFloat(input.value);
                if (!Number.isFinite(price) || price <= 0) return;

                gradeBPriceTimers[productId] = window.setTimeout(async () => {
                    const wrapper = input.closest('label');
                    wrapper?.classList.add('opacity-60');

                    try {
                        const response = await fetch(@js(route('purchaser.purchase-grade-prices.update')), {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': @js(csrf_token()),
                            },
                            body: JSON.stringify({
                                business_date: @js($date),
                                prices: { [productId]: { B: price } },
                            }),
                        });

                        if (!response.ok) throw new Error('Unable to save price');
                        wrapper?.classList.add('border-emerald-500', 'bg-emerald-50');
                        window.setTimeout(() => wrapper?.classList.remove('border-emerald-500', 'bg-emerald-50'), 1200);
                    } catch (error) {
                        wrapper?.classList.add('border-rose-500', 'bg-rose-50');
                    } finally {
                        wrapper?.classList.remove('opacity-60');
                    }
                }, 600);
            }
        </script>
    @endpush
</x-layouts.app>
