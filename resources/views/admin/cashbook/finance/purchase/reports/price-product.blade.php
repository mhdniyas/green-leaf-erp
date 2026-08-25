@extends('admin.cashbook.layouts.app')
@section('title', $product->name.' Price Detail - Cashbook')
@section('header_title')
    <i data-lucide="scan-search" class="h-5 w-5 text-emerald-600"></i> Price Detail
@endsection
@section('header_subtitle')
    {{ $product->name }} approved and actual price history.
@endsection
@section('content')
<div class="mx-auto max-w-[96rem] space-y-5">
    @include('admin.cashbook.finance.purchase.reports._header', ['reportName' => $product->name, 'reportDescription' => 'Approved pricing and purchase details for '.\Illuminate\Support\Carbon::parse($filters['date'])->format('d M Y').'.'])
    <div class="flex justify-end"><a href="{{ route('admin.cashbook.finance.purchase.reports.prices', request()->except('product')) }}" class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-slate-300 px-3 text-xs font-black text-slate-700"><i data-lucide="arrow-left" class="h-3.5 w-3.5"></i> Price Report</a></div>
    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4"><h2 class="text-sm font-black text-slate-950">Approved Price Details</h2></div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[40rem] text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase text-slate-500">
                    <tr>
                        <th class="p-3 text-left">Date</th>
                        <th class="p-3 text-right">Purchase</th>
                        @foreach($activePriceGroups as $priceGroup)
                            <th class="p-3 text-right">Group {{ $priceGroup->name }}</th>
                        @endforeach
                        <th class="p-3 text-left">Unit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($detail['approvals'] as $row)
                        <tr>
                            <td class="p-3 font-bold">{{ $row->business_date }}</td>
                            <td class="p-3 text-right font-mono">₹{{ number_format((float) $row->purchase_price, 2) }}</td>
                            @foreach($activePriceGroups as $priceGroup)
                                @php($priceColumn = 'price_'.strtolower($priceGroup->name))
                                <td class="p-3 text-right font-mono">₹{{ number_format((float) $row->{$priceColumn}, 2) }}</td>
                            @endforeach
                            <td class="p-3 font-bold">{{ strtoupper($row->price_unit ?: $product->unit) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 3 + $activePriceGroups->count() }}" class="p-6 text-center text-slate-400">No approved price for this date.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="rounded-lg border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-200 p-4"><h2 class="text-sm font-black text-slate-950">Actual Purchases</h2><p class="mt-0.5 text-xs font-semibold text-slate-500">Invoiced purchase records for the selected business date.</p></div><div class="overflow-x-auto"><table class="w-full min-w-[56rem] text-xs"><thead class="bg-slate-50 text-[10px] uppercase text-slate-500"><tr><th class="p-3 text-left">Date</th><th class="p-3 text-left">Invoice</th><th class="p-3 text-left">Vendor</th><th class="p-3 text-left">Purchaser</th><th class="p-3 text-left">Grade</th><th class="p-3 text-right">Quantity</th><th class="p-3 text-right">Unit Price</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($detail['history'] as $row)<tr><td class="p-3">{{ $row->business_date }}</td><td class="p-3"><a href="{{ route('purchasing.invoices.show', $row->invoice_public_uuid) }}" class="font-bold text-emerald-700 hover:underline">{{ $row->invoice_number }}</a></td><td class="p-3">{{ $row->vendor_name ?: '—' }}</td><td class="p-3">{{ $row->purchaser_name ?: '—' }}</td><td class="p-3">{{ $row->grade }}</td><td class="p-3 text-right font-mono">{{ number_format((float) $row->quantity, 3) }}</td><td class="p-3 text-right font-mono font-bold">₹{{ number_format((float) $row->unit_price, 2) }}</td></tr>@empty<tr><td colspan="7" class="p-6 text-center text-slate-400">No actual purchases on this date.</td></tr>@endforelse</tbody></table></div></section>
    @if($detail['specialPrices']->isNotEmpty())<section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><h2 class="text-sm font-black text-slate-950">Approved Shop Overrides</h2><div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">@foreach($detail['specialPrices'] as $row)<div class="rounded-lg border border-slate-200 p-3 text-xs"><strong class="block text-slate-900">{{ $row->shop_name }}</strong><span class="mt-1 block font-mono text-emerald-700">₹{{ number_format((float) $row->selling_price, 2) }} / {{ strtoupper($row->price_unit ?: $product->unit) }}</span><span class="text-slate-400">{{ $row->business_date }}</span></div>@endforeach</div></section>@endif
</div>
@endsection
