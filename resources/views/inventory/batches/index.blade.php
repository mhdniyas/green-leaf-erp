<x-layouts.app title="Batches">

    <x-slot:actions>
        <a href="{{ route('inventory.batches.create') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm">
            + Receive Batch
        </a>
    </x-slot:actions>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">All Batches</h2>
            <span class="text-xs text-gray-500">{{ $batches->total() }} total</span>
        </div>

        @if($batches->isEmpty())
        <div class="py-16 text-center">
            <p class="text-sm text-gray-500">No batches yet. Receive your first batch to start sorting.</p>
            <a href="{{ route('inventory.batches.create') }}" class="mt-3 inline-flex text-sm text-brand-600 font-medium hover:underline">+ Receive Batch</a>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Reference</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Product</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">Received</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Qty (kg)</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($batches as $batch)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <a href="{{ route('inventory.batches.show', $batch) }}" class="font-mono text-xs text-brand-700 font-semibold hover:underline">
                                {{ $batch->reference }}
                            </a>
                        </td>
                        <td class="px-6 py-4 hidden md:table-cell text-gray-700">
                            <div class="flex items-center gap-2">
                                @if($batch->product?->image)
                                    <img src="{{ $batch->product->getImageUrl() }}" class="w-6 h-6 rounded object-cover shrink-0" alt="{{ $batch->product->name }}">
                                @endif
                                <span>{{ $batch->product?->name ?? '—' }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 hidden sm:table-cell text-gray-500">{{ $batch->received_at?->format('d M Y') }}</td>
                        <td class="px-6 py-4 text-right font-semibold text-gray-900">{{ number_format($batch->total_kg, 1) }}</td>
                        <td class="px-6 py-4">
                            @php
                                $statusColors = ['pending' => 'bg-amber-100 text-amber-700', 'sorted' => 'bg-green-100 text-green-700', 'closed' => 'bg-gray-100 text-gray-600'];
                                $color = $statusColors[$batch->status->value] ?? 'bg-gray-100 text-gray-600';
                            @endphp
                            <span class="inline-flex text-xs font-medium px-2.5 py-1 rounded-full {{ $color }}">
                                {{ $batch->status->label() }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if($batch->canBeSorted())
                                <a href="{{ route('inventory.batches.sort', $batch) }}"
                                   class="inline-flex items-center gap-1 text-xs font-medium text-brand-700 bg-brand-50 border border-brand-200 px-2.5 py-1 rounded-lg hover:bg-brand-100 transition-colors">
                                    Sort →
                                </a>
                                @endif
                                <a href="{{ route('inventory.batches.show', $batch) }}"
                                   class="p-1.5 text-gray-400 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors" title="View">
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
        <div class="px-6 py-4 border-t border-gray-100">{{ $batches->links() }}</div>
        @endif
        @endif
    </div>

</x-layouts.app>
