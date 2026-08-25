@extends('admin.cashbook.layouts.app')
@section('title', 'Purchaser Price - Cashbook')
@section('header_title')
    <i data-lucide="calendar-range" class="h-5 w-5 text-emerald-600"></i> Purchaser Price
@endsection
@section('header_subtitle')
    Compare approved purchaser buying prices between days.
@endsection
@section('content')
<div class="mx-auto max-w-[96rem] space-y-5">
    @include('admin.cashbook.finance.purchase.reports._header', ['reportName' => 'Purchaser Price', 'reportDescription' => 'Approved purchaser buying-price comparison. Actual invoice prices remain separately labelled in Price Report.'])
    @include('admin.cashbook.finance.purchase.reports._comparison-filters', ['filterRoute' => 'admin.cashbook.finance.purchase.reports.purchaser-prices'])
    <section class="rounded-lg border border-slate-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="w-full min-w-[48rem] text-left text-xs"><thead class="bg-slate-50 text-[10px] uppercase text-slate-500"><tr><th class="p-3">Product</th><th class="p-3">Unit</th><th class="p-3 text-right">{{ $filters['date_a'] }}</th><th class="p-3 text-right">{{ $filters['date_b'] }}</th><th class="p-3 text-right">Change</th><th class="p-3 text-right">Change %</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($rows as $row)@php($change = (float) $row->current_price - (float) $row->previous_price)@php($percent = (float) $row->previous_price !== 0.0 ? $change / (float) $row->previous_price * 100 : null)<tr><td class="p-3 font-black text-slate-900">{{ $row->product_name }}<div class="text-[10px] font-semibold text-slate-400">{{ $row->category_name }}</div></td><td class="p-3 font-bold text-slate-500">{{ strtoupper($row->price_unit ?: $row->product_unit) }}</td><td class="p-3 text-right font-mono">₹{{ number_format((float) $row->previous_price, 2) }}</td><td class="p-3 text-right font-mono font-bold">₹{{ number_format((float) $row->current_price, 2) }}</td><td class="p-3 text-right font-mono font-bold {{ $change > 0 ? 'text-rose-700' : ($change < 0 ? 'text-emerald-700' : 'text-slate-500') }}">{{ $change > 0 ? '+' : '' }}₹{{ number_format($change, 2) }}</td><td class="p-3 text-right font-mono">{{ $percent !== null ? ($percent > 0 ? '+' : '').number_format($percent, 2).'%' : '—' }}</td></tr>@empty<tr><td colspan="6" class="p-8 text-center text-slate-400">No approved purchaser prices exist for both dates.</td></tr>@endforelse</tbody></table></div>@if($rows->hasPages())<div class="border-t border-slate-200 p-4">{{ $rows->links() }}</div>@endif</section>
</div>
@endsection
