<x-layouts.app title="Purchaser Daily">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')

        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-[0_16px_36px_rgba(15,23,42,0.18)] lg:rounded-[2rem] lg:shadow-[0_24px_60px_rgba(15,23,42,0.24)]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(45,212,191,0.28),_transparent_36%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#134e4a_100%)] px-4 py-4 sm:px-5 lg:px-4 lg:py-5">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-teal-200 sm:text-[11px] sm:tracking-[0.22em]">Purchaser Flow</p>
                        <h1 class="mt-1 text-xl font-black tracking-tight sm:mt-2 sm:text-2xl">Daily demand</h1>
                        <p class="mt-2 max-w-2xl text-sm font-medium text-slate-200">Select today&apos;s products, add them into carts, and move fast from market demand to purchase.</p>
                    </div>
                    <form action="{{ route('purchaser.daily') }}" method="GET" class="w-full md:w-auto">
                        <label for="business-date" class="text-[11px] font-black uppercase tracking-[0.16em] text-teal-100">Business Date</label>
                        <input id="business-date" type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="mt-2 w-full rounded-xl border border-white/10 bg-white/10 px-3 py-3 text-sm font-bold text-white outline-none ring-0 md:w-52 lg:rounded-2xl lg:px-4">
                    </form>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2 sm:gap-3 lg:mt-5 lg:grid-cols-4">
                    <div class="rounded-xl bg-white/10 px-3 py-3 lg:rounded-2xl lg:px-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">Need</p>
                        <p class="mt-2 text-2xl font-black">{{ number_format($dailyFulfillment['approved_qty'], 0) }}</p>
                    </div>
                    <div class="rounded-xl bg-amber-400/15 px-3 py-3 lg:rounded-2xl lg:px-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-100">Bought</p>
                        <p class="mt-2 text-2xl font-black text-amber-200">{{ number_format($dailyFulfillment['bought_qty'], 0) }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-400/15 px-3 py-3 lg:rounded-2xl lg:px-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-100">Left</p>
                        <p class="mt-2 text-2xl font-black text-emerald-200">{{ number_format($dailyFulfillment['remaining_qty'], 0) }}</p>
                    </div>
                    <div class="rounded-xl bg-cyan-400/15 px-3 py-3 lg:rounded-2xl lg:px-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-cyan-100">Carts</p>
                        <p class="mt-2 text-2xl font-black text-cyan-200">{{ $dailyFulfillment['draft_carts'] }}</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Two-column layout: product list + aside --}}
        <div class="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-start">

            {{-- LEFT: product list --}}
            <div class="min-w-0 flex-1 space-y-4">
                <form action="{{ route('purchaser.daily') }}" method="GET" class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
                    <input type="hidden" name="date" value="{{ $date }}">
                    <div class="flex flex-col gap-3">
                        <input type="search" name="search" value="{{ $search }}" placeholder="Search product..." class="w-full min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none lg:rounded-2xl lg:px-4">
                        <div class="flex gap-2 overflow-x-auto overscroll-x-contain pb-1">
                            @foreach ($quickFilters as $filter)
                                <button type="submit" name="chip" value="{{ $filter }}" class="{{ $selectedChip === $filter ? 'bg-teal-600 text-white' : 'bg-slate-100 text-slate-600' }} shrink-0 rounded-full px-4 py-2.5 text-xs font-black">
                                    {{ $filter }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </form>

                <div class="flex min-w-0 flex-col gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between lg:rounded-[2rem] lg:px-4 lg:py-4">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Daily queue</p>
                        <p class="mt-1 text-sm font-semibold text-slate-600">{{ $dailySummary->count() }} products for {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</p>
                    </div>
                    <a href="{{ $dailySummaryShareUrl }}" target="_blank" rel="noopener" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-emerald-600 px-4 text-sm font-black text-white sm:w-auto lg:min-h-12 lg:rounded-2xl">
                        Share Summary
                    </a>
                </div>

                <div class="space-y-4">
                    @forelse ($dailySummary as $summary)
                        @php
                            $step = $summary['unit'] === 'kg' ? '0.5' : '1';
                        @endphp
                        <article class="relative min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
                            <div class="flex flex-col gap-4">
                                {{-- Product header --}}
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="min-w-0 break-words text-lg font-black text-slate-950">{{ $summary['product_name'] }}</h2>
                                        <span class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-black uppercase tracking-[0.14em] text-slate-600">{{ $summary['category_name'] ?: 'Other' }}</span>
                                        @if ($summary['draft_qty'] > 0)
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-amber-800">
                                                {{ number_format($summary['draft_qty'], 2) }} {{ $summary['unit'] }} in cart
                                                @if(!empty($summary['draft_purchasers']))
                                                    (by {{ implode(', ', $summary['draft_purchasers']) }})
                                                @endif
                                            </span>
                                        @endif
                                        @if ($summary['remaining_qty'] <= 0 && $summary['bought_qty'] > 0)
                                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-black uppercase tracking-[0.14em] text-emerald-700">Purchased ✓</span>
                                        @endif
                                    </div>
                                    {{-- Stats: single grid row --}}
                                    <div class="mt-3 grid grid-cols-3 gap-2 text-center text-xs font-semibold text-slate-600">
                                        <div class="min-w-0 rounded-xl bg-slate-50 p-2">
                                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-500">Need</span>
                                            <span class="mt-0.5 block truncate font-black text-slate-955">{{ number_format($summary['total_approved_qty'], 2) }} {{ $summary['unit'] }}</span>
                                        </div>
                                        <div class="min-w-0 rounded-xl bg-amber-50 p-2">
                                            <span class="block text-[10px] font-bold uppercase tracking-wider text-amber-600">Bought</span>
                                            <span class="mt-0.5 block truncate font-black text-amber-700">{{ number_format($summary['bought_qty'], 2) }} {{ $summary['unit'] }}</span>
                                        </div>
                                        <div class="min-w-0 rounded-xl bg-emerald-50 p-2">
                                            <span class="block text-[10px] font-bold uppercase tracking-wider text-emerald-600">Left</span>
                                            <span class="mt-0.5 block truncate font-black text-emerald-700">{{ number_format($summary['remaining_qty'], 2) }} {{ $summary['unit'] }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 lg:rounded-2xl lg:px-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        @foreach ($summary['quantity_buckets'] as $bucket)
                                            <span class="inline-flex rounded-full bg-cyan-100 px-3 py-1 text-[11px] font-black uppercase tracking-[0.14em] text-cyan-700">{{ $bucket['formatted'] }} x {{ $bucket['count'] }}</span>
                                        @endforeach
                                    </div>
                                    <button type="button" onclick="document.getElementById('info-product-{{ $summary['product_id'] }}').classList.remove('hidden'); document.body.classList.add('overflow-hidden')" class="mt-3 flex w-full items-center justify-between text-left text-[11px] font-black text-slate-500">
                                        <span>Demand split</span>
                                        <span class="rounded-full bg-white px-2.5 py-1 text-[10px] text-slate-700 shadow-sm">{{ count($summary['shop_details']) }} shops</span>
                                    </button>
                                </div>

                                @if ($summary['remaining_qty'] > 0)
                                    <div class="mt-2.5">
                                        <button type="button" onclick="openAddToCartModal({{ $summary['product_id'] }}, '{{ addslashes($summary['product_name']) }}', '{{ $summary['unit'] }}', {{ $summary['remaining_qty'] }}, {{ $summary['draft_qty'] }}, '{{ $step }}', '{{ addslashes(implode(', ', $summary['draft_purchasers'])) }}')" class="inline-flex h-9 w-full items-center justify-center gap-1.5 rounded-lg bg-teal-600 px-3 text-xs font-black text-white shadow-sm transition-all hover:bg-teal-500">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                            </svg>
                                            <span>Add to Cart</span>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </article>

                        <div id="info-product-{{ $summary['product_id'] }}" class="fixed inset-0 z-[90] hidden p-4" onclick="if (event.target === this) { this.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); }">
                            <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>
                            <div class="relative mx-auto flex min-h-full max-w-lg items-center justify-center">
                                <div class="w-full rounded-2xl border border-slate-200 bg-white p-4 shadow-xl lg:rounded-[2rem] lg:p-5">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Demand Details</p>
                                            <h3 class="mt-1 text-lg font-black text-slate-950">{{ $summary['product_name'] }}</h3>
                                            <p class="mt-1 text-sm font-semibold text-slate-600">{{ number_format($summary['total_approved_qty'], 2) }} {{ $summary['unit'] }} total needed</p>
                                        </div>
                                        <button type="button" onclick="document.getElementById('info-product-{{ $summary['product_id'] }}').classList.add('hidden'); document.body.classList.remove('overflow-hidden')" class="rounded-xl bg-slate-100 px-3 py-2 text-[11px] font-black text-slate-700">Close</button>
                                    </div>
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        @foreach ($summary['quantity_buckets'] as $bucket)
                                            <span class="inline-flex rounded-full bg-cyan-100 px-3 py-1 text-[11px] font-black uppercase tracking-[0.14em] text-cyan-700">{{ $bucket['formatted'] }} x {{ $bucket['count'] }}</span>
                                        @endforeach
                                    </div>
                                    <div class="mt-4 space-y-2">
                                        @foreach ($summary['shop_details'] as $detail)
                                            <div class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-semibold text-slate-700 lg:rounded-2xl">
                                                <div class="min-w-0">
                                                    <p class="truncate font-black text-slate-900">{{ $detail['shop_name'] }}</p>
                                                    <p class="truncate text-xs text-slate-500">{{ $detail['order_number'] }}</p>
                                                </div>
                                                <span class="shrink-0">{{ number_format($detail['approved_qty'], 2) }} {{ $detail['unit'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-3 py-10 text-center text-sm font-bold text-slate-500 lg:rounded-[2rem] lg:px-4 lg:py-12">
                            No demand for this date. Try a different date or use Buy Other.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- RIGHT: aside (hidden on mobile, shown on lg+) --}}
            <aside class="w-full space-y-4 lg:w-88 lg:shrink-0">
                <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Buy Other</p>
                            <p class="mt-1 text-sm font-semibold text-slate-600">Add off-list products into a draft cart.</p>
                        </div>
                        <a href="{{ route('purchaser.vendors', ['date' => $date]) }}" class="text-sm font-black text-teal-700">Open Carts</a>
                    </div>

                    <form action="{{ route('purchaser.cart-items.store') }}" method="POST" class="mt-4 space-y-2">
                        @csrf
                        <input type="hidden" name="business_date" value="{{ $date }}">
                        <input type="hidden" name="return_to" value="daily">
                        <input type="hidden" name="chip" value="{{ $selectedChip }}">
                        <input type="hidden" name="search" value="{{ $search }}">
                        <div class="relative custom-select-container w-full min-w-0">
                            <button type="button" class="custom-select-trigger flex h-9 w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 text-left text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                                <span class="custom-select-label truncate">New cart</span>
                                <svg class="h-4 w-4 shrink-0 text-slate-500 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <input type="hidden" name="cart_id" value="" class="custom-select-input">
                            <div class="custom-select-options hidden absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                                <button type="button" data-value="" class="custom-select-option flex w-full items-center justify-between px-3 py-2.5 text-left text-sm font-bold text-slate-900 hover:bg-slate-100">
                                    <span>New cart</span>
                                    <span class="checkmark text-teal-600">✓</span>
                                </button>
                                @foreach ($draftCarts as $cart)
                                    <button type="button" data-value="{{ $cart->id }}" class="custom-select-option flex w-full items-center justify-between px-3 py-2 text-left text-sm font-semibold text-slate-700 hover:bg-slate-100">
                                        <span>{{ $cart->cart_number }}</span>
                                        <span class="checkmark hidden text-teal-600">✓</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        @if($buyOtherProducts->isNotEmpty())
                        <div class="relative custom-select-container w-full min-w-0">
                            <button type="button" class="custom-select-trigger flex h-9 w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 text-left text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                                <span class="custom-select-label truncate">
                                    {{ $buyOtherProducts->first()->name }}{{ $buyOtherProducts->first()->category?->name ? ' - '.$buyOtherProducts->first()->category->name : '' }}
                                </span>
                                <svg class="h-4 w-4 shrink-0 text-slate-500 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <input type="hidden" name="product_id" value="{{ $buyOtherProducts->first()->id }}" class="custom-select-input">
                            <div class="custom-select-options hidden absolute left-0 right-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                                @foreach ($buyOtherProducts as $index => $product)
                                    <button type="button" data-value="{{ $product->id }}" class="custom-select-option flex w-full items-center justify-between px-3 py-2.5 text-left text-sm {{ $index === 0 ? 'font-bold text-slate-900' : 'font-semibold text-slate-700' }} hover:bg-slate-100">
                                        <span>{{ $product->name }}{{ $product->category?->name ? ' - '.$product->category->name : '' }}</span>
                                        <span class="checkmark {{ $index === 0 ? '' : 'hidden' }} text-teal-600">✓</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        @endif
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" step="0.5" min="0.5" name="quantity" value="1" class="min-w-0 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                            <input type="number" step="0.01" min="0" name="unit_price" value="0" class="min-w-0 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none" placeholder="Price">
                        </div>
                        <button type="submit" class="flex h-9 w-full items-center justify-center rounded-lg bg-slate-950 px-4 text-xs font-black text-white">
                            + Buy Other Product
                        </button>
                    </form>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Active carts</p>
                    <div class="mt-3 space-y-3">
                        @forelse ($draftCarts as $cart)
                            <a href="{{ route('purchaser.vendors', ['date' => $date]) }}" class="block rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 lg:rounded-2xl lg:px-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-black text-slate-900">{{ $cart->cart_number }}</p>
                                    <span class="rounded-full {{ $cart->whatsapp_sent_at ? 'bg-blue-100 text-blue-700' : 'bg-slate-200 text-slate-700' }} px-3 py-1 text-[11px] font-black uppercase tracking-[0.14em]">
                                        {{ $cart->whatsapp_sent_at ? 'WhatsApp Sent' : 'Draft' }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm font-semibold text-slate-600">{{ $cart->items->count() }} items</p>
                            </a>
                        @empty
                            <p class="rounded-xl bg-slate-50 px-3 py-4 text-sm font-semibold text-slate-500 lg:rounded-2xl lg:px-4">No draft carts yet.</p>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Toggle dropdown on click of trigger
            document.addEventListener('click', (e) => {
                const trigger = e.target.closest('.custom-select-trigger');
                if (trigger) {
                    const container = trigger.closest('.custom-select-container');
                    const optionsList = container.querySelector('.custom-select-options');
                    
                    // Close all other dropdowns first
                    document.querySelectorAll('.custom-select-options').forEach(el => {
                        if (el !== optionsList) el.classList.add('hidden');
                    });
                    
                    optionsList.classList.toggle('hidden');
                    
                    // Rotate arrow
                    const arrow = trigger.querySelector('svg');
                    if (arrow) {
                        if (optionsList.classList.contains('hidden')) {
                            arrow.classList.remove('rotate-180');
                        } else {
                            arrow.classList.add('rotate-180');
                        }
                    }
                    return;
                }

                const option = e.target.closest('.custom-select-option');
                if (option) {
                    const container = option.closest('.custom-select-container');
                    const input = container.querySelector('.custom-select-input');
                    const label = container.querySelector('.custom-select-label');
                    const optionsList = container.querySelector('.custom-select-options');
                    
                    const val = option.getAttribute('data-value');
                    const text = option.querySelector('span').textContent;
                    
                    // Update value & label
                    input.value = val;
                    label.textContent = text;
                    
                    // Update checkmarks
                    container.querySelectorAll('.custom-select-option').forEach(opt => {
                        const check = opt.querySelector('.checkmark');
                        if (opt === option) {
                            check.classList.remove('hidden');
                            opt.classList.add('font-bold');
                            opt.classList.remove('font-semibold', 'text-slate-700');
                        } else {
                            check.classList.add('hidden');
                            opt.classList.remove('font-bold');
                            opt.classList.add('font-semibold', 'text-slate-700');
                        }
                    });
                    
                    optionsList.classList.add('hidden');
                    
                    // Reset arrow rotation
                    const triggerBtn = container.querySelector('.custom-select-trigger');
                    const arrow = triggerBtn ? triggerBtn.querySelector('svg') : null;
                    if (arrow) arrow.classList.remove('rotate-180');
                    return;
                }

                // Clicked outside, close all dropdowns & reset arrows
                if (!e.target.closest('.custom-select-container')) {
                    document.querySelectorAll('.custom-select-options').forEach(el => {
                        el.classList.add('hidden');
                        const container = el.closest('.custom-select-container');
                        const triggerBtn = container.querySelector('.custom-select-trigger');
                        const arrow = triggerBtn ? triggerBtn.querySelector('svg') : null;
                        if (arrow) arrow.classList.remove('rotate-180');
                    });
                }
            });
        });
    </script>

    {{-- Add to Cart Modal --}}
    <div id="add-to-cart-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden" onclick="if(event.target === this) closeAddToCartModal()">
        <div class="relative w-full max-w-xs rounded-2xl border border-slate-200 bg-white p-4 shadow-xl flex flex-col">
            {{-- Header --}}
            <div class="flex items-center justify-between pb-1.5 border-b border-slate-100 mb-3">
                <div class="min-w-0 flex-1">
                    <h3 class="text-xs font-black text-slate-950 truncate" id="add-to-cart-product-name">Product Name</h3>
                    <p class="text-[9px] font-semibold text-slate-500 mt-0.5 flex flex-wrap items-center gap-1">
                        <span>Add to draft cart</span>
                        <span id="add-to-cart-in-cart-label" class="text-amber-600 font-bold hidden"></span>
                    </p>
                </div>
                <button type="button" onclick="closeAddToCartModal()" class="text-slate-400 hover:text-slate-600 focus:outline-none transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <form action="{{ route('purchaser.cart-items.store') }}" method="POST" class="space-y-3">
                @csrf
                <input type="hidden" name="business_date" value="{{ $date }}">
                <input type="hidden" id="add-to-cart-product-id" name="product_id" value="">
                <input type="hidden" name="return_to" value="daily">
                <input type="hidden" name="chip" value="{{ $selectedChip }}">
                <input type="hidden" name="search" value="{{ $search }}">
                
                {{-- Cart Selector --}}
                <div class="space-y-1">
                    <label class="block text-[9px] font-black uppercase tracking-wider text-slate-500">Select Cart</label>
                    <div class="relative custom-select-container w-full min-w-0">
                        <button type="button" class="custom-select-trigger flex h-8 w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-left text-[10px] font-semibold text-slate-955 focus:outline-none">
                            <span class="custom-select-label truncate">New cart</span>
                            <svg class="h-3.5 w-3.5 shrink-0 text-slate-500 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <input type="hidden" name="cart_id" value="" class="custom-select-input">
                        <div class="custom-select-options hidden absolute left-0 right-0 z-50 mt-1 max-h-48 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                            <button type="button" data-value="" class="custom-select-option flex w-full items-center justify-between px-3 py-1.5 text-left text-xs font-bold text-slate-950 hover:bg-slate-50">
                                <span>New cart</span>
                                <span class="checkmark text-teal-600">✓</span>
                            </button>
                            @foreach ($draftCarts as $cart)
                                <button type="button" data-value="{{ $cart->id }}" class="custom-select-option flex w-full items-center justify-between px-3 py-1.5 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    <span>{{ $cart->cart_number }}</span>
                                    <span class="checkmark hidden text-teal-600">✓</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Quantity input --}}
                <div class="space-y-1">
                    <label for="add-to-cart-quantity" class="block text-[9px] font-black uppercase tracking-wider text-slate-500">
                        Quantity (<span id="add-to-cart-unit-label">kg</span>) <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" id="add-to-cart-quantity" name="quantity" required class="w-full h-8 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-955 focus:border-teal-500 focus:bg-white focus:outline-none">
                </div>

                {{-- Price input --}}
                <div class="space-y-1">
                    <label for="add-to-cart-price" class="block text-[9px] font-black uppercase tracking-wider text-slate-500">
                        Price (Per <span id="add-to-cart-price-unit-label">unit</span>) <span class="text-slate-400 font-normal">(Optional)</span>
                    </label>
                    <input type="number" step="0.01" min="0" id="add-to-cart-price" name="unit_price" class="w-full h-8 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-955 focus:border-teal-500 focus:bg-white focus:outline-none" placeholder="0.00">
                </div>

                {{-- Calculated Total --}}
                <div class="space-y-1">
                    <label class="block text-[9px] font-black uppercase tracking-wider text-slate-500">
                        Calculated Total
                    </label>
                    <div id="add-to-cart-total-display" class="flex h-8 w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-100 px-2.5 text-xs font-bold text-slate-700">
                        ₹ 0.00
                    </div>
                </div>

                <div class="pt-1">
                    <button type="submit" class="w-full h-8 rounded-lg bg-teal-600 text-xs font-black text-white hover:bg-teal-500 shadow-sm transition-colors">
                        Add to Cart
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateModalTotalPrice() {
            const quantity = parseFloat(document.getElementById('add-to-cart-quantity').value) || 0;
            const price = parseFloat(document.getElementById('add-to-cart-price').value) || 0;
            const total = quantity * price;
            document.getElementById('add-to-cart-total-display').textContent = '₹ ' + total.toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        document.getElementById('add-to-cart-quantity').addEventListener('input', updateModalTotalPrice);
        document.getElementById('add-to-cart-price').addEventListener('input', updateModalTotalPrice);

        function openAddToCartModal(productId, productName, productUnit, remainingQty, draftQty, step, draftPurchasers) {
            document.getElementById('add-to-cart-product-id').value = productId;
            document.getElementById('add-to-cart-product-name').textContent = productName;
            document.getElementById('add-to-cart-unit-label').textContent = productUnit;
            document.getElementById('add-to-cart-price-unit-label').textContent = productUnit;
            
            const inCartLabel = document.getElementById('add-to-cart-in-cart-label');
            if (parseFloat(draftQty) > 0) {
                let labelText = `${parseFloat(draftQty).toFixed(2)} ${productUnit} in cart`;
                if (draftPurchasers) {
                    labelText += ` (by ${draftPurchasers})`;
                }
                inCartLabel.textContent = `(${labelText})`;
                inCartLabel.classList.remove('hidden');
            } else {
                inCartLabel.classList.add('hidden');
            }
            
            const defaultQty = Math.max(0, parseFloat(remainingQty) - parseFloat(draftQty));
            
            const qtyInput = document.getElementById('add-to-cart-quantity');
            qtyInput.value = defaultQty > 0 ? defaultQty.toFixed(2) : parseFloat(step).toFixed(2);
            qtyInput.step = step;
            qtyInput.min = step;
            
            document.getElementById('add-to-cart-price').value = '';
            updateModalTotalPrice();
            
            document.getElementById('add-to-cart-modal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => qtyInput.focus(), 50);
        }

        function closeAddToCartModal() {
            document.getElementById('add-to-cart-modal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
    </script>
</x-layouts.app>
