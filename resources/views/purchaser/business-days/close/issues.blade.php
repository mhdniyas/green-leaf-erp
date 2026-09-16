@extends('purchaser.business-days.layouts.app')

@section('title', 'Close Day · Unit Issues — ' . $warehouse->name)
@section('page_title', 'Close Review · Unit Issues')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <!-- Top Navigation Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('purchaser.business-days.close.index', $day->uuid) }}" class="inline-flex items-center gap-1.5 text-xs font-black text-teal-700 hover:text-teal-900 mb-1">
                <span aria-hidden="true">&larr;</span> Back to Close Day
            </a>
            <h2 class="text-xl font-black text-slate-950">Unit Issues ({{ $unitIssues->count() }})</h2>
            <p class="text-xs font-semibold text-slate-500">Business Day: {{ $day->business_date->format('d M Y') }} · {{ $warehouse->name }}</p>
        </div>
    </div>

    <!-- Unit Issues Table / Cards -->
    <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        @if ($unitIssues->isEmpty())
            <div class="py-16 text-center">
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 text-xl font-black">
                    ✓
                </span>
                <h3 class="mt-3 text-base font-black text-slate-900">No Unit Mismatches Found</h3>
                <p class="mt-1 text-xs font-medium text-slate-500">All advance receipts and purchase bills use compatible units for this business day.</p>
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
                            <th class="py-3.5 px-6">Product</th>
                            <th class="py-3.5 px-4">Advance Unit</th>
                            <th class="py-3.5 px-4">Bill Unit</th>
                            <th class="py-3.5 px-4">Problem</th>
                            <th class="py-3.5 px-6 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                        @foreach ($unitIssues as $issue)
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-4 px-6 font-black text-slate-900">
                                    {{ $issue['product_name'] }}
                                </td>
                                <td class="py-4 px-4 font-bold text-slate-700">
                                    {{ !empty($issue['advance_units']) ? implode(', ', $issue['advance_units']) : $issue['unit'] }}
                                </td>
                                <td class="py-4 px-4 font-bold text-slate-700">
                                    {{ !empty($issue['bill_units']) ? implode(', ', $issue['bill_units']) : '—' }}
                                </td>
                                <td class="py-4 px-4 text-rose-700 font-bold">
                                    Unit conversion mismatch between warehouse intake and supplier bill.
                                </td>
                                <td class="py-4 px-6 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-[10px] font-black uppercase text-rose-700">
                                        Action Required
                                    </span>
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
