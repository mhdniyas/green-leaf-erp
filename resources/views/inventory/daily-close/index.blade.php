<x-layouts.inventory title="Daily Inventory Close">
    <x-slot:actions>
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('inventory.daily-close.index') }}" class="flex items-center gap-2">
                <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-900">
            </form>
            <a href="{{ route('inventory.stock.index', ['date' => $date]) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-50">Stock</a>
        </div>
    </x-slot:actions>

    @php
        $negativeRows = $stockRows->filter(fn ($row) => (float) $row->current_stock < -0.001);
        $positiveRows = $stockRows->filter(fn ($row) => (float) $row->current_stock > 0.001);
        $carryoverRows = $stockRows->filter(fn ($row) => (bool) ($row->carryover_enabled ?? false));
    @endphp

    <div class="space-y-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Inventory Close</p>
                    <h1 class="mt-1 text-2xl font-black text-slate-950">{{ \Carbon\Carbon::parse($date)->format('d F Y') }}</h1>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Split every positive leftover into wastage or carryover. Add notes for negative stock before closing.</p>
                </div>
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-2xl bg-slate-50 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Positive</p>
                        <p class="mt-1 text-xl font-black text-slate-950">{{ $positiveRows->count() }}</p>
                    </div>
                    <div class="rounded-2xl bg-cyan-50 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-cyan-700">Carryover</p>
                        <p class="mt-1 text-xl font-black text-cyan-900">{{ $carryoverRows->count() }}</p>
                    </div>
                    <div class="rounded-2xl bg-rose-50 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-rose-700">Negative</p>
                        <p class="mt-1 text-xl font-black text-rose-900">{{ $negativeRows->count() }}</p>
                    </div>
                </div>
            </div>
        </section>

        <form method="POST" action="{{ route('inventory.daily-close.store') }}" class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            @csrf
            <input type="hidden" name="date" value="{{ $date }}">

            <div class="hidden border-b border-slate-100 bg-slate-50 px-4 py-3 text-[10px] font-black uppercase tracking-[0.14em] text-slate-500 lg:grid lg:grid-cols-[minmax(0,1fr)_5rem_7rem_7rem_7rem_minmax(12rem,1fr)] lg:gap-3">
                <span>Product</span>
                <span class="text-right">Grade</span>
                <span class="text-right">Closing</span>
                <span class="text-right">Wastage</span>
                <span class="text-right">Carryover</span>
                <span>Negative Note</span>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($stockRows as $index => $row)
                    @php
                        $line = $closedLines->get($row->product_id.'|'.$row->grade);
                        $closingQty = (float) $row->current_stock;
                        $isNegative = $closingQty < -0.001;
                        $canCarryover = (bool) ($row->carryover_enabled ?? false);
                        $defaultCarryover = $line ? (float) $line->carryover_qty : ($canCarryover && $closingQty > 0 ? $closingQty : 0);
                        $defaultWastage = $line ? (float) $line->wastage_qty : (! $canCarryover && $closingQty > 0 ? $closingQty : 0);
                    @endphp
                    <article class="grid gap-3 px-4 py-4 lg:grid-cols-[minmax(0,1fr)_5rem_7rem_7rem_7rem_minmax(12rem,1fr)] lg:items-center">
                        <div class="min-w-0">
                            <input type="hidden" name="lines[{{ $index }}][product_id]" value="{{ $row->product_id }}">
                            <input type="hidden" name="lines[{{ $index }}][grade]" value="{{ $row->grade }}">
                            <input type="hidden" name="lines[{{ $index }}][closing_qty]" value="{{ number_format($closingQty, 3, '.', '') }}">
                            <input type="hidden" name="lines[{{ $index }}][carryover_enabled]" value="{{ $canCarryover ? 1 : 0 }}">
                            <p class="truncate text-sm font-black text-slate-950">{{ $row->product_name }}</p>
                            <p class="mt-1 text-[10px] font-black uppercase tracking-[0.12em] {{ $canCarryover ? 'text-cyan-700' : 'text-slate-400' }}">
                                {{ $canCarryover ? 'Carryover enabled' : 'Same-day close' }}
                            </p>
                        </div>
                        <p class="text-right text-xs font-black text-slate-600">{{ $row->grade }}</p>
                        <p class="text-right text-sm font-black tabular-nums {{ $isNegative ? 'text-rose-700' : 'text-slate-950' }}">{{ number_format($closingQty, 3) }}</p>
                        <input type="number" step="0.001" min="0" name="lines[{{ $index }}][wastage_qty]" value="{{ old("lines.{$index}.wastage_qty", number_format(max(0, $defaultWastage), 3, '.', '')) }}" class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-right text-sm font-black text-slate-900">
                        <input type="number" step="0.001" min="0" name="lines[{{ $index }}][carryover_qty]" value="{{ old("lines.{$index}.carryover_qty", number_format(max(0, $defaultCarryover), 3, '.', '')) }}" @disabled(! $canCarryover) class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-right text-sm font-black text-slate-900 disabled:bg-slate-100 disabled:text-slate-400">
                        <div>
                            <input name="lines[{{ $index }}][negative_note]" value="{{ old("lines.{$index}.negative_note", $line?->negative_note) }}" placeholder="{{ $isNegative ? 'Required for negative stock' : 'Optional note' }}" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-900">
                            @error("lines.{$index}.wastage_qty")
                                <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                            @enderror
                            @error("lines.{$index}.carryover_qty")
                                <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                            @enderror
                            @error("lines.{$index}.negative_note")
                                <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </article>
                @empty
                    <div class="px-5 py-16 text-center text-sm font-bold text-slate-500">No stock movement to close for this date.</div>
                @endforelse
            </div>

            <div class="border-t border-slate-100 bg-slate-50 px-4 py-4">
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-950 px-5 py-3 text-sm font-black text-white hover:bg-slate-800 sm:w-auto">
                    Save Daily Close
                </button>
            </div>
        </form>
    </div>
</x-layouts.inventory>
