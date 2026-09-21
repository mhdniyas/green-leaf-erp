<x-layouts.purchaser-v2 title="Purchaser V2 &bull; Draft Carts">
    <div class="space-y-4 sm:space-y-6">

        <!-- Top Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('purchaser-v2.buy', ['date' => $date, 'grade' => $grade]) }}"
                   class="flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-slate-900 active:scale-95">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-black tracking-tight text-slate-900 sm:text-2xl">Draft Carts</h1>
                        <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-black text-emerald-700 border border-emerald-200">
                            Grade {{ $grade }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 font-medium">Review unsubmitted draft carts for {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}.</p>
                </div>
            </div>

            <a href="{{ route('purchaser-v2.buy', ['date' => $date, 'grade' => $grade]) }}"
               class="inline-flex items-center gap-2 rounded-2xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700 active:scale-95">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Add More Products</span>
            </a>
        </div>

        <!-- Flash Message Banner -->
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 text-xs font-bold text-emerald-900 flex items-center gap-3">
                <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <!-- Draft Carts Grid -->
        <div class="space-y-4">
            @forelse ($draftCarts as $cart)
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <span class="rounded-xl bg-slate-100 px-2.5 py-1 text-xs font-black font-mono text-slate-800">
                                {{ $cart->cart_number }}
                            </span>
                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 border border-amber-200">
                                Draft
                            </span>
                        </div>
                        <span class="text-xs font-mono font-bold text-slate-500">
                            {{ $cart->items->count() }} {{ $cart->items->count() === 1 ? 'item' : 'items' }} &bull; Total: ₹{{ number_format((float) $cart->items->sum('line_total'), 2) }}
                        </span>
                    </div>

                    <!-- Cart Item Rows -->
                    <div class="divide-y divide-slate-100">
                        @foreach ($cart->items as $item)
                            <div class="py-2.5 flex items-center justify-between text-xs">
                                <div class="min-w-0">
                                    <p class="font-bold text-slate-900 truncate">{{ $item->product?->name ?? 'Unknown Product' }}</p>
                                    <p class="text-[10px] text-slate-400 font-mono">{{ $item->product?->category?->name ?? 'Produce' }} &bull; Grade {{ $item->grade }}</p>
                                </div>
                                <div class="text-right font-mono">
                                    <span class="font-bold text-slate-800">{{ number_format((float) $item->quantity, 2) }} {{ $item->product?->unit ?? 'kg' }}</span>
                                    <span class="text-slate-400"> &times; ₹{{ number_format((float) $item->unit_price, 2) }}</span>
                                    <span class="font-black text-slate-900 ml-2">= ₹{{ number_format((float) $item->line_total, 2) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Phase C Action Hint -->
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400">
                        <span>Cart saved in draft state. (Phase C Cart Hub will introduce supplier assignment and checkout).</span>
                        <a href="{{ route('purchaser-v2.daily', ['date' => $date, 'purchase_grade' => $grade]) }}" class="font-bold text-emerald-600 hover:text-emerald-700">
                            Back to Daily Board &rarr;
                        </a>
                    </div>
                </div>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-200 bg-white p-12 text-center text-slate-400 space-y-2">
                    <p class="text-xs font-bold text-slate-600">No draft carts found for this date.</p>
                    <a href="{{ route('purchaser-v2.buy', ['date' => $date, 'grade' => $grade]) }}" class="inline-block text-xs font-bold text-emerald-600 hover:text-emerald-700">
                        Go to Buy Workspace &rarr;
                    </a>
                </div>
            @endforelse
        </div>

    </div>
</x-layouts.purchaser-v2>
