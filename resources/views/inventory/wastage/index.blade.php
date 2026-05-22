<x-layouts.app title="Wastage Log">

    <x-slot:actions>
        <a href="{{ route('inventory.wastage.create') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700 transition-colors shadow-sm">
            + Record Wastage
        </a>
    </x-slot:actions>

    {{-- Today's cost stat --}}
    @if($todayCost > 0)
    <div class="bg-red-50 border border-red-200 rounded-2xl px-5 py-4 mb-5 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
            </svg>
        </div>
        <div>
            <p class="text-xs text-red-700">Today's Wastage Cost</p>
            <p class="text-lg font-bold text-red-800 mt-0.5">RM {{ number_format($todayCost, 2) }}</p>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Wastage Entries</h2>
            <span class="text-xs text-gray-500">{{ $entries->total() }} total</span>
        </div>

        @if($entries->isEmpty())
        <div class="py-16 text-center">
            <p class="text-sm text-gray-500">No wastage entries yet — that's great!</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Product</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">Grade</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Reason</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Qty (kg)</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($entries as $entry)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4 text-gray-600">{{ $entry->wastage_date?->format('d M Y') }}</td>
                        <td class="px-6 py-4 hidden md:table-cell text-gray-900 font-medium">
                            <div class="flex items-center gap-2">
                                @if($entry->product?->image)
                                    <img src="{{ $entry->product->getImageUrl() }}" class="w-6 h-6 rounded object-cover shrink-0" alt="{{ $entry->product->name }}">
                                @endif
                                <span>{{ $entry->product?->name ?? '—' }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 hidden sm:table-cell">
                            @php
                                $gc = ['A' => 'bg-green-100 text-green-700', 'B' => 'bg-blue-100 text-blue-700', 'C' => 'bg-amber-100 text-amber-700', 'D' => 'bg-red-100 text-red-600'];
                                $c  = $gc[$entry->grade?->value] ?? 'bg-gray-100 text-gray-600';
                            @endphp
                            <span class="text-xs font-bold px-2 py-0.5 rounded-lg {{ $c }}">Grade {{ $entry->grade?->value }}</span>
                        </td>
                        <td class="px-6 py-4 hidden md:table-cell text-gray-600">{{ $entry->reason?->label() }}</td>
                        <td class="px-6 py-4 text-right font-semibold text-gray-900">{{ number_format($entry->quantity, 3) }}</td>
                        <td class="px-6 py-4 text-right font-semibold text-red-700">RM {{ number_format($entry->total_cost, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($entries->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $entries->links() }}</div>
        @endif
        @endif
    </div>

</x-layouts.app>
