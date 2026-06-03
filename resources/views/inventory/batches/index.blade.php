<x-layouts.app title="Batches">

    <x-slot:actions>
        <div class="flex items-center gap-2">
            @if($date)
                <a href="{{ route('inventory.batches.index', ['date' => \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d')]) }}" class="p-2 bg-white rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors shadow-xs" title="Previous Day">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                </a>
            @endif
            <form id="date-form" method="GET" action="{{ route('inventory.batches.index') }}" class="flex items-center gap-2">
                <input id="date-select" type="date" name="date" value="{{ $date ?? '' }}" onchange="document.getElementById('date-form').submit();"
                       class="border border-gray-200 rounded-xl px-3 py-1.5 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white shadow-xs">
            </form>
            @if($date)
                <a href="{{ route('inventory.batches.index', ['date' => \Carbon\Carbon::parse($date)->addDay()->format('Y-m-d')]) }}" class="p-2 bg-white rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors shadow-xs" title="Next Day">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                </a>
                <a href="{{ route('inventory.batches.index') }}" class="px-3 py-1.5 bg-slate-100 text-slate-700 border border-slate-200 rounded-xl text-xs font-bold hover:bg-slate-200 transition-colors shadow-xs">
                    Clear Filter
                </a>
            @else
                <a href="{{ route('inventory.batches.index', ['date' => \Carbon\Carbon::today()->format('Y-m-d')]) }}" class="px-3 py-1.5 bg-brand-50 text-brand-700 border border-brand-200 rounded-xl text-xs font-bold hover:bg-brand-100 transition-colors shadow-xs">
                    Today
                </a>
            @endif
            <a href="{{ route('inventory.batches.create') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-700 transition-colors shadow-sm">
                + Receive Batch
            </a>
        </div>
    </x-slot:actions>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-slate-50/50">
            <div>
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Stock Batches Inventory</h2>
                @if($date)
                    <p class="text-[10px] text-brand-600 font-bold mt-0.5">Filtering for received date: {{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</p>
                @else
                    <p class="text-[10px] text-slate-400 mt-0.5">Showing all received stock batches in the system</p>
                @endif
            </div>
            <span class="text-xs font-bold text-gray-500">{{ $batches->total() }} total</span>
        </div>

        @if($batches->isEmpty())
        <div class="py-16 text-center">
            <p class="text-sm text-gray-500">No batches found for the selected query. Receive your first batch or adjust your filters.</p>
            <a href="{{ route('inventory.batches.create') }}" class="mt-3 inline-flex text-sm text-brand-600 font-medium hover:underline">+ Receive Batch</a>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-slate-50/20">
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Reference</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Product</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">Received Date</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Total Qty (kg)</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Allocated (kg)</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Wasted (kg)</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Remaining (kg)</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($batches as $batch)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <a href="{{ route('inventory.batches.show', $batch) }}" class="font-mono text-xs text-brand-700 font-bold hover:underline">
                                {{ $batch->reference }}
                            </a>
                        </td>
                        <td class="px-6 py-4 hidden md:table-cell text-gray-700">
                            <div class="flex items-center gap-2">
                                @if($batch->product?->image)
                                    <img src="{{ $batch->product->getImageUrl() }}" class="w-6 h-6 rounded object-cover shrink-0" alt="{{ $batch->product->name }}">
                                @endif
                                <span class="font-bold">{{ $batch->product?->name ?? '—' }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 hidden sm:table-cell text-slate-500 font-semibold">{{ $batch->received_at?->format('d M Y') }}</td>
                        <td class="px-6 py-4 text-right font-mono font-bold text-gray-900">{{ number_format((float)$batch->total_kg, 2) }}</td>
                        <td class="px-6 py-4 text-right font-mono font-bold text-emerald-600">
                            {{ $batch->allocated_qty > 0 ? number_format($batch->allocated_qty, 2) : '—' }}
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-bold text-red-500">
                            @php $wasted = (float)$batch->wastageEntries->sum('quantity'); @endphp
                            {{ $wasted > 0 ? number_format($wasted, 2) : '—' }}
                        </td>
                        <td class="px-6 py-4 text-right font-mono">
                            @php $remaining = (float)$batch->remaining_qty; @endphp
                            @if($remaining <= 0)
                                <span class="inline-flex items-center gap-1 rounded-sm bg-slate-100 px-1.5 py-0.5 text-[9px] font-black uppercase text-slate-500 border border-slate-200">
                                    ✓ Fully Allocated
                                </span>
                            @else
                                <span class="font-black text-brand-600">{{ number_format($remaining, 2) }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $statusColors = ['pending' => 'bg-amber-50 text-amber-700 border border-amber-200', 'sorted' => 'bg-green-50 text-green-700 border border-green-200', 'closed' => 'bg-gray-50 text-gray-600 border border-gray-200'];
                                $color = $statusColors[$batch->status->value] ?? 'bg-gray-50 text-gray-600 border border-gray-200';
                            @endphp
                            <span class="inline-flex text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full {{ $color }}">
                                {{ $batch->status->label() }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if($batch->canBeSorted())
                                <a href="{{ route('inventory.batches.sort', $batch) }}"
                                   class="inline-flex items-center gap-1 text-xs font-bold text-brand-700 bg-brand-50 border border-brand-200 px-2.5 py-1 rounded-xl hover:bg-brand-100 transition-colors">
                                    Sort →
                                </a>
                                @endif
                                <a href="{{ route('inventory.batches.show', $batch) }}"
                                   class="p-1.5 text-gray-400 hover:text-brand-600 hover:bg-brand-50 rounded-xl transition-colors" title="View Details">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($batches->hasPages())
        <div class="px-6 py-4 border-t border-gray-100 bg-slate-50/50">{{ $batches->links() }}</div>
        @endif
        @endif
    </div>

</x-layouts.app>
