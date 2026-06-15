<x-layouts.app title="Suppliers">

    <x-slot:actions>
        @can('purchasing.supplier.create')
        <a href="{{ route('purchasing.suppliers.create') }}"
           id="add-supplier-btn"
           class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add Supplier
        </a>
        @endcan
    </x-slot:actions>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">All Suppliers</h2>
            <span class="text-xs text-gray-500">{{ $suppliers->total() }} suppliers</span>
        </div>

        @if($suppliers->isEmpty())
        <div class="py-16 text-center">
            <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124l-.318-5.085a1.5 1.5 0 0 0-1.496-1.408h-2.483c-.767 0-1.42.545-1.5 1.3L12.5 14.25m0 0v-4.5m0 4.5h6.75m-6.75-4.5H8.25M6.75 8.25h.008v.008H6.75V8.25Zm.375 0a.375 0 1 1-.75 0 .375 0 0 1 .75 0Z" />
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-900">No suppliers found</p>
            <p class="text-xs text-gray-500 mt-1">Add your first supplier to get started.</p>
            @can('purchasing.supplier.create')
            <a href="{{ route('purchasing.suppliers.create') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm text-brand-600 font-medium hover:underline">
                + Add Supplier
            </a>
            @endcan
        </div>
        @else
        <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
            <table class="min-w-[900px] text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Supplier</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Contact Details</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Payment Terms</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Quality Score</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($suppliers as $supplier)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center shrink-0">
                                    <span class="text-amber-700 text-xs font-bold">{{ strtoupper(substr($supplier->name, 0, 1)) }}</span>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">{{ $supplier->name }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center bg-gray-100 text-gray-700 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                {{ $supplier->type }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-600">
                            {{ $supplier->contact }}
                        </td>
                        <td class="px-6 py-4 text-gray-600">
                            {{ $supplier->payment_terms }}
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $score = (float) $supplier->quality_score;
                                if ($score >= 90) {
                                    $colorClass = 'text-green-700 bg-green-50 border-green-200';
                                    $dotClass = 'bg-green-500';
                                } elseif ($score >= 75) {
                                    $colorClass = 'text-amber-700 bg-amber-50 border-amber-200';
                                    $dotClass = 'bg-amber-500';
                                } else {
                                    $colorClass = 'text-red-700 bg-red-50 border-red-200';
                                    $dotClass = 'bg-red-500';
                                }
                            @endphp
                            <span class="inline-flex items-center gap-1 text-xs font-medium border px-2.5 py-0.5 rounded-full {{ $colorClass }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $dotClass }}"></span>
                                {{ number_format($score, 2) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @can('purchasing.supplier.update')
                                <a href="{{ route('purchasing.suppliers.edit', $supplier) }}"
                                   class="p-1.5 text-gray-400 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors"
                                   title="Edit">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                    </svg>
                                </a>
                                @endcan

                                @can('purchasing.supplier.delete')
                                <form method="POST" action="{{ route('purchasing.suppliers.destroy', $supplier) }}"
                                      onsubmit="return confirm('Delete supplier {{ $supplier->name }}? This will soft delete the supplier record.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($suppliers->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $suppliers->withQueryString()->links() }}
        </div>
        @endif
        @endif
    </div>

</x-layouts.app>
