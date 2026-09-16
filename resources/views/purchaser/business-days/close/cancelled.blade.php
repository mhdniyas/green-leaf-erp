@extends('purchaser.business-days.layouts.app')

@section('title', 'Close Day · Cancelled Purchases — ' . $warehouse->name)
@section('page_title', 'Close Review · Cancelled Purchases')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <!-- Top Navigation Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('purchaser.business-days.close.index', $day->uuid) }}" class="inline-flex items-center gap-1.5 text-xs font-black text-teal-700 hover:text-teal-900 mb-1">
                <span aria-hidden="true">&larr;</span> Back to Close Day
            </a>
            <h2 class="text-xl font-black text-slate-950">Cancelled Purchases ({{ $cancelledPurchases->count() }})</h2>
            <p class="text-xs font-semibold text-slate-500">Business Day: {{ $day->business_date->format('d M Y') }} · {{ $warehouse->name }}</p>
        </div>
    </div>

    <!-- Cancelled Purchases Table / Cards -->
    <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        @if ($cancelledPurchases->isEmpty())
            <div class="py-16 text-center">
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 text-xl font-black">
                    ✓
                </span>
                <h3 class="mt-3 text-base font-black text-slate-900">No Cancelled Purchases</h3>
                <p class="mt-1 text-xs font-medium text-slate-500">There are no cancelled purchase orders or receipts recorded for this business day.</p>
                <div class="mt-4">
                    <a href="{{ route('purchaser.business-days.close.index', $day->uuid) }}" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-xs font-black text-white hover:bg-slate-800">
                        Back to Close Day Checklist &rarr;
                    </a>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500">
                            <th class="py-3.5 px-5">Vendor</th>
                            <th class="py-3.5 px-4">PO / Bill #</th>
                            <th class="py-3.5 px-4">Products</th>
                            <th class="py-3.5 px-4">Reason / Notes</th>
                            <th class="py-3.5 px-4">Cancelled By</th>
                            <th class="py-3.5 px-5 text-right">Cancelled At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                        @foreach ($cancelledPurchases as $cancelled)
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-4 px-5 font-black text-slate-900">
                                    {{ $cancelled->purchaseOrder?->supplier?->name ?? 'Supplier' }}
                                </td>
                                <td class="py-4 px-4 font-bold text-slate-800">
                                    {{ $cancelled->bill_number ?? $cancelled->grn_number }}
                                </td>
                                <td class="py-4 px-4 font-semibold text-slate-700">
                                    @if ($cancelled->items->isNotEmpty())
                                        <div class="space-y-0.5">
                                            @foreach ($cancelled->items as $item)
                                                <div>{{ $item->product?->name }} ({{ (float) $item->received_qty }} {{ $item->received_unit ?? 'kg' }})</div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="py-4 px-4 text-slate-600">
                                    {{ $cancelled->rejection_remarks ?? $cancelled->notes ?? '—' }}
                                </td>
                                <td class="py-4 px-4 text-slate-600">
                                    {{ $cancelled->updatedBy?->name ?? $cancelled->receivedBy?->name ?? '—' }}
                                </td>
                                <td class="py-4 px-5 text-right text-slate-500 whitespace-nowrap">
                                    {{ $cancelled->updated_at?->format('d M Y, h:i A') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
