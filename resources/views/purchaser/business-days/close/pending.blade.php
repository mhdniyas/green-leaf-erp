@extends('purchaser.business-days.layouts.app')

@section('title', 'Close Day · Pending Products — ' . $warehouse->name)
@section('page_title', 'Close Review · Pending Products')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <!-- Top Navigation Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('purchaser.business-days.close.index', $day->uuid) }}" class="inline-flex items-center gap-1.5 text-xs font-black text-teal-700 hover:text-teal-900 mb-1">
                <span aria-hidden="true">&larr;</span> Back to Close Day
            </a>
            <h2 class="text-xl font-black text-slate-950">Pending Products ({{ $pendingList->count() }})</h2>
            <p class="text-xs font-semibold text-slate-500">Business Day: {{ $day->business_date->format('d M Y') }} · {{ $warehouse->name }}</p>
        </div>

        @if ($pendingList->isNotEmpty() && ($day->isOpen() || $day->isReopened()))
            <a href="{{ route('purchasing.business-days.bills.create', $day->uuid) }}" class="inline-flex h-10 items-center justify-center rounded-xl bg-teal-600 px-4 text-xs font-black text-white shadow-xs hover:bg-teal-500">
                + Record Bill to Clear Pending
            </a>
        @endif
    </div>

    <!-- Pending Products Table / Cards -->
    <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        @if ($pendingList->isEmpty())
            <div class="py-16 text-center">
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 text-xl font-black">
                    ✓
                </span>
                <h3 class="mt-3 text-base font-black text-slate-900">All Advance Goods Fully Matched</h3>
                <p class="mt-1 text-xs font-medium text-slate-500">There are no pending unbilled advance items for this business day.</p>
                <div class="mt-4">
                    <a href="{{ route('purchaser.business-days.close.index', $day->uuid) }}" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-xs font-black text-white hover:bg-slate-800">
                        Proceed to Close Day &rarr;
                    </a>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500">
                            <th class="py-3.5 px-6">Product</th>
                            <th class="py-3.5 px-4 text-right">Advance Qty</th>
                            <th class="py-3.5 px-4 text-right">Matched Qty</th>
                            <th class="py-3.5 px-6 text-right">Pending Qty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                        @foreach ($pendingList as $item)
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-4 px-6 font-black text-slate-900">
                                    <div>
                                        <span>{{ $item['product_name'] }}</span>
                                        @if ($item['unit_mismatch'])
                                            <span class="inline-block mt-0.5 rounded-sm bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-700">Unit Mismatch</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-4 px-4 text-right font-bold text-slate-700">
                                    {{ (float) $item['advance_qty'] }} {{ $item['unit'] }}
                                </td>
                                <td class="py-4 px-4 text-right font-bold text-emerald-600">
                                    {{ (float) $item['matched_qty'] }} {{ $item['unit'] }}
                                </td>
                                <td class="py-4 px-6 text-right font-black text-amber-700 text-sm whitespace-nowrap">
                                    {{ $item['formatted_pending'] }}
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
