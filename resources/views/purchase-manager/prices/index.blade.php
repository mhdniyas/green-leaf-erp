@extends('purchase-manager.layouts.app')

@section('title', 'Daily Price Board')
@section('page_title', 'Daily Price Board')
@section('page_description', 'Set the effective product prices used by shop owners for a selected business date.')

@section('content')
    <form method="GET" action="{{ route('purchasing.prices.index') }}" class="mb-6 flex flex-col gap-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm md:flex-row md:items-end">
        <div class="w-full md:max-w-xs">
            <label for="date" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Business Date</label>
            <input id="date" type="date" name="date" value="{{ $priceDate->toDateString() }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
        </div>
        <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-black text-white">
            Load Board
        </button>
    </form>

    <form method="POST" action="{{ route('purchasing.prices.update') }}" class="purchase-manager-panel overflow-hidden">
        @csrf
        <input type="hidden" name="date" value="{{ $priceDate->toDateString() }}">

        <div class="border-b border-slate-200 px-5 py-5">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-black text-slate-950">Effective prices for {{ $priceDate->format('d M Y') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">Leave the daily price blank to fall back to the base price.</p>
                </div>
                <div class="rounded-2xl border border-cyan-100 bg-cyan-50 px-4 py-3 text-xs font-bold text-cyan-900">
                    Base price is the default. Daily price overrides it only for the selected date.
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left">
                <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                    <tr>
                        <th class="px-5 py-4">Product</th>
                        <th class="px-5 py-4">SKU</th>
                        <th class="px-5 py-4 text-right">Base Price</th>
                        <th class="px-5 py-4 text-right">Daily Price</th>
                        <th class="px-5 py-4 text-right">Effective</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @foreach ($products as $product)
                        @php
                            $dailyPrice = $dailyPrices[$product->id] ?? null;
                            $effectivePrice = $dailyPrice !== null ? (float) $dailyPrice : (float) $product->base_price;
                        @endphp
                        <tr>
                            <td class="px-5 py-4">
                                <p class="font-bold text-slate-950">{{ $product->name }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-400">{{ $product->category?->name }} · {{ strtoupper($product->unit) }}</p>
                            </td>
                            <td class="px-5 py-4 font-semibold text-slate-500">{{ $product->sku }}</td>
                            <td class="px-5 py-4">
                                <input type="number" step="0.01" min="0" name="base_prices[{{ $product->id }}]" value="{{ old("base_prices.{$product->id}", number_format((float) $product->base_price, 2, '.', '')) }}" class="w-28 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-right text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                            </td>
                            <td class="px-5 py-4">
                                <input type="number" step="0.01" min="0" name="daily_prices[{{ $product->id }}]" value="{{ old("daily_prices.{$product->id}", $dailyPrice !== null ? number_format((float) $dailyPrice, 2, '.', '') : '') }}" placeholder="Use base" class="w-28 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-right text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                            </td>
                            <td class="px-5 py-4 text-right font-black text-slate-950">INR {{ number_format($effectivePrice, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex justify-end border-t border-slate-200 px-5 py-5">
            <x-purchase-manager.components.action-button type="submit" variant="primary">Save Daily Prices</x-purchase-manager.components.action-button>
        </div>
    </form>
@endsection
