@extends('admin.cashbook.layouts.app')

@section('title', 'Price Report - Cashbook')

@section('header_title')
    <i data-lucide="badge-indian-rupee" class="h-5 w-5 text-emerald-600"></i> Price Report
@endsection

@section('header_subtitle')
    Compare approved purchaser price against authoritative selling prices.
@endsection

@section('content')
    @php($primaryPriceGroup = $activePriceGroups->first())
    @php($primaryPriceColumn = $primaryPriceGroup ? 'price_'.strtolower($primaryPriceGroup->name) : null)
    @php($columnCount = 5 + $activePriceGroups->count() + ($primaryPriceGroup ? 2 : 0))

    <div class="mx-auto max-w-[96rem] space-y-5">
        @include('admin.cashbook.finance.purchase.reports._header', ['reportName' => 'Price Report', 'reportDescription' => 'Approved purchase price, actual weighted purchase price, and approved selling price matrix.'])
        @include('admin.cashbook.finance.purchase.reports._price-filters', ['filterRoute' => 'admin.cashbook.finance.purchase.reports.prices'])

        <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-900">
            Approved Purchase Price comes from the daily approval. Actual Purchase Price is quantity-weighted from invoiced cart items for the selected business date. Only active price categories are shown.
        </div>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[56rem] text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase text-slate-500">
                        <tr>
                            <th class="p-3">Product</th>
                            <th class="p-3">Unit</th>
                            <th class="p-3 text-right">Actual Purchase</th>
                            <th class="p-3 text-right">Approved Purchase</th>
                            @foreach($activePriceGroups as $priceGroup)
                                <th class="p-3 text-right">Group {{ $priceGroup->name }}</th>
                            @endforeach
                            @if($primaryPriceGroup)
                                <th class="p-3 text-right">{{ $primaryPriceGroup->name }} Difference</th>
                                <th class="p-3 text-right">{{ $primaryPriceGroup->name }} Margin</th>
                            @endif
                            <th class="p-3 text-right">Special</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($rows as $row)
                            @php($difference = $primaryPriceColumn ? (float) $row->{$primaryPriceColumn} - (float) $row->approved_purchase_price : null)
                            @php($margin = $difference !== null && (float) $row->approved_purchase_price > 0 ? $difference / (float) $row->approved_purchase_price * 100 : null)
                            <tr class="hover:bg-slate-50">
                                <td class="p-3">
                                    <div class="flex items-center gap-1.5">
                                        @if(!empty($row->product_sku))
                                            <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] font-bold text-slate-600">{{ $row->product_sku }}</span>
                                        @endif
                                        <a href="{{ route('admin.cashbook.finance.purchase.reports.prices.product', array_merge(request()->query(), ['product' => $row->product_id])) }}" class="font-black text-emerald-700 hover:underline">{{ $row->product_name }}</a>
                                    </div>
                                    <div class="text-[10px] text-slate-400">{{ $row->category_name }}</div>
                                </td>
                                <td class="p-3 font-bold text-slate-500">{{ strtoupper($row->price_unit ?: $row->product_unit) }}</td>
                                <td class="p-3 text-right font-mono">{{ $row->actual_purchase_price !== null ? '₹'.number_format((float) $row->actual_purchase_price, 2) : '—' }}</td>
                                <td class="p-3 text-right font-mono font-bold">₹{{ number_format((float) $row->approved_purchase_price, 2) }}</td>
                                @foreach($activePriceGroups as $priceGroup)
                                    @php($priceColumn = 'price_'.strtolower($priceGroup->name))
                                    <td class="p-3 text-right font-mono">₹{{ number_format((float) $row->{$priceColumn}, 2) }}</td>
                                @endforeach
                                @if($primaryPriceGroup)
                                    <td class="p-3 text-right font-mono {{ $difference < 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $difference >= 0 ? '+' : '' }}₹{{ number_format($difference, 2) }}</td>
                                    <td class="p-3 text-right font-mono">{{ $margin !== null ? number_format($margin, 2).'%' : '—' }}</td>
                                @endif
                                <td class="p-3 text-right">{{ (int) $row->special_price_count > 0 ? $row->special_price_count.' shop(s)' : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $columnCount }}" class="p-8 text-center text-slate-400">
                                    @if(!empty($filters['search']))
                                        No products match your search.
                                    @else
                                        No approved prices match the selected filters.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($rows->hasPages())
                <div class="border-t border-slate-200 p-4">{{ $rows->links() }}</div>
            @endif
        </section>
    </div>
@endsection
