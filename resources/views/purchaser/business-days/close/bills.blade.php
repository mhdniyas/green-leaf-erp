@extends('purchaser.business-days.layouts.app')

@section('title', 'Close Day · Purchase Bills — ' . $warehouse->name)
@section('page_title', 'Close Review · Purchase Bills')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <!-- Top Navigation Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('purchaser.business-days.close.index', $day->uuid) }}" class="inline-flex items-center gap-1.5 text-xs font-black text-teal-700 hover:text-teal-900 mb-1">
                <span aria-hidden="true">&larr;</span> Back to Close Day
            </a>
            <h2 class="text-xl font-black text-slate-950">Purchase Bills ({{ $bills->count() }})</h2>
            <p class="text-xs font-semibold text-slate-500">Business Day: {{ $day->business_date->format('d M Y') }} · {{ $warehouse->name }} (Read-only)</p>
        </div>

        <div class="flex items-center gap-2">
            <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-black text-blue-800">
                Total Bills: {{ $bills->count() }}
            </span>
        </div>
    </div>

    <!-- Bills Table / Cards -->
    <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500">
                        <th class="py-3.5 px-5">Invoice / Bill #</th>
                        <th class="py-3.5 px-4">Vendor</th>
                        <th class="py-3.5 px-4">Products</th>
                        <th class="py-3.5 px-4 text-right">Amount</th>
                        <th class="py-3.5 px-5 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                    @forelse ($bills as $bill)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-4 px-5 font-black text-slate-900 whitespace-nowrap">
                                <div>
                                    <span>{{ $bill->bill_number ?? $bill->grn_number }}</span>
                                    <span class="block text-[10px] font-bold text-slate-400">{{ $bill->received_at?->format('h:i A') }}</span>
                                </div>
                            </td>
                            <td class="py-4 px-4 font-bold text-slate-800">
                                {{ $bill->purchaseOrder?->supplier?->name ?? 'Direct Supplier' }}
                            </td>
                            <td class="py-4 px-4">
                                <div class="space-y-1">
                                    @foreach ($bill->items as $item)
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-slate-900">{{ $item->product?->name }}</span>
                                            <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">
                                                {{ (float) $item->received_qty }} {{ $item->received_unit ?? $item->product?->unit ?? 'kg' }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="py-4 px-4 text-right font-black text-slate-900">
                                @php
                                    $invTotal = (float) ($bill->purchaseInvoices?->sum('amount') ?? 0);
                                    if ($invTotal <= 0 && $bill->purchaseOrder) {
                                        $invTotal = (float) ($bill->purchaseOrder->total_amount ?? $bill->purchaseOrder->amount ?? 0);
                                    }
                                    if ($invTotal <= 0) {
                                        $invTotal = (float) $bill->items->sum(function ($item) {
                                            return (float) ($item->purchaseOrderItem?->unit_price ? $item->received_qty * $item->purchaseOrderItem->unit_price : 0);
                                        });
                                    }
                                @endphp
                                {{ $invTotal > 0 ? '₹' . number_format($invTotal, 2) : '—' }}
                            </td>
                            <td class="py-4 px-5 text-center">
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-emerald-800">
                                    {{ $bill->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-xs font-semibold text-slate-400">
                                No purchase bills recorded for this business day.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
