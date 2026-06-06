@extends('purchase-manager.layouts.app')

@section('title', 'Suppliers')
@section('page_title', 'Suppliers')
@section('page_description', 'Maintain supplier records, payment terms, contact details, and quality performance in one clean vendor list.')

@section('page_actions')
    @can('purchasing.supplier.create')
        <x-purchase-manager.components.action-button :href="route('purchasing.suppliers.create')" variant="success">Add Supplier</x-purchase-manager.components.action-button>
    @endcan
@endsection

@section('content')
    <div class="purchase-manager-panel overflow-hidden">
        @if ($suppliers->isEmpty())
            <div class="p-5">
                <x-purchase-manager.components.empty-state
                    title="No suppliers found"
                    description="Add your first supplier to start purchase ordering and invoice matching."
                    :actionHref="route('purchasing.suppliers.create')"
                    actionLabel="Add Supplier"
                />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Supplier</th>
                            <th class="px-5 py-4">Type</th>
                            <th class="px-5 py-4">Category</th>
                            <th class="px-5 py-4">Contact</th>
                            <th class="px-5 py-4">Payment Terms</th>
                            <th class="px-5 py-4">Default</th>
                            <th class="px-5 py-4">Quality Score</th>
                            <th class="px-5 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @foreach ($suppliers as $supplier)
                            @php
                                $score = (float) $supplier->quality_score;
                                $tone = $score >= 90 ? 'emerald' : ($score >= 75 ? 'amber' : 'rose');
                            @endphp
                            <tr>
                                <td class="px-5 py-4">
                                    <p class="font-bold text-slate-950">{{ $supplier->name }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <x-purchase-manager.components.status-badge :label="$supplier->type" tone="slate" />
                                </td>
                                <td class="px-5 py-4">
                                    <x-purchase-manager.components.status-badge :label="$supplier->category === 'own_purchase' ? 'Own Purchase' : 'B2B'" :tone="$supplier->category === 'own_purchase' ? 'emerald' : 'cyan'" />
                                </td>
                                <td class="px-5 py-4 text-slate-600">{{ $supplier->contact }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $supplier->payment_terms }}</td>
                                <td class="px-5 py-4">
                                    <x-purchase-manager.components.status-badge :label="$supplier->is_default_purchase ? 'Default' : 'Standard'" :tone="$supplier->is_default_purchase ? 'emerald' : 'slate'" />
                                </td>
                                <td class="px-5 py-4">
                                    <x-purchase-manager.components.status-badge :label="number_format($score, 2)" :tone="$tone" />
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        @can('purchasing.supplier.update')
                                            <x-purchase-manager.components.action-button :href="route('purchasing.suppliers.edit', $supplier)" variant="secondary">Edit</x-purchase-manager.components.action-button>
                                        @endcan
                                        @can('purchasing.supplier.delete')
                                            <form method="POST" action="{{ route('purchasing.suppliers.destroy', $supplier) }}" onsubmit="return confirm('Delete supplier {{ $supplier->name }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <x-purchase-manager.components.action-button type="submit" variant="soft-danger">Delete</x-purchase-manager.components.action-button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($suppliers->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">
                    {{ $suppliers->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
