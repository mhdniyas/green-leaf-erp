@extends('admin.cashbook.layouts.app')

@section('title', 'Accept Shop Payments - Cashbook')

@section('header_title')
    <i data-lucide="wallet-cards" class="h-5 w-5 text-emerald-600"></i> Accept Shop Payments
@endsection

@section('header_subtitle')
    Monthly shop payment control: received, floating, pending, payable, and after-approved balance.
@endsection

@section('content')
    <div class="mx-auto max-w-[96rem] space-y-5">
        <section class="white-card rounded-lg border border-slate-200 p-4 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-extrabold text-slate-950">{{ \Carbon\Carbon::parse($startDate)->format('F Y') }}</h2>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Showing monthly payment position only. No daily or weekly switching on this page.</p>
                </div>
                <form method="GET" action="{{ route('admin.cashbook.accept-payment') }}" class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                    <input type="month" name="month" value="{{ $month }}" class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 text-xs font-bold text-white hover:bg-slate-800">
                        <i data-lucide="search" class="h-4 w-4"></i> Load
                    </button>
                </form>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3 xl:grid-cols-6">
            @foreach([
                ['label' => 'Total Received', 'value' => $totals['received'], 'tone' => 'text-slate-950'],
                ['label' => 'Approved', 'value' => $totals['approved'], 'tone' => 'text-emerald-700'],
                ['label' => 'Floating', 'value' => $totals['floating'], 'tone' => 'text-amber-700'],
                ['label' => 'Pending', 'value' => $totals['pending'], 'tone' => 'text-orange-700'],
                ['label' => 'Payable', 'value' => $totals['payable'], 'tone' => 'text-sky-700'],
                ['label' => 'After Balance', 'value' => $totals['after_balance'], 'tone' => 'text-rose-700'],
            ] as $total)
                <div class="white-card rounded-lg border border-slate-200 p-3 shadow-sm">
                    <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">{{ $total['label'] }}</span>
                    <strong class="mt-2 block break-words font-mono text-lg font-extrabold {{ $total['tone'] }}">₹{{ number_format($total['value'], 2) }}</strong>
                </div>
            @endforeach
        </section>

        <section class="grid grid-cols-1 gap-3 md:grid-cols-2 2xl:grid-cols-3">
            @forelse($shopCards as $card)
                @php($shop = $card['shop'])
                <a href="{{ route('admin.cashbook.shop.accept-payment', ['shop' => $shop->slug ?: $shop->shop_id, 'month' => $month]) }}" class="white-card white-card-hover block rounded-lg border border-slate-200 p-4 shadow-sm transition">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate text-base font-extrabold text-slate-950">{{ $shop->name ?: 'Shop #'.$shop->shop_id }}</h3>
                            <p class="mt-1 truncate text-xs font-semibold text-slate-500">{{ $shop->client?->name ?? 'Direct shop' }} / {{ $shop->code ?: 'SHOP-'.$shop->shop_id }}</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-600">{{ $card['entry_count'] }} rows</span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-lg bg-slate-50 p-2">
                            <span class="block font-bold text-slate-400">Received</span>
                            <strong class="font-mono text-slate-900">₹{{ number_format($card['received_amount'], 2) }}</strong>
                        </div>
                        <div class="rounded-lg bg-emerald-50 p-2">
                            <span class="block font-bold text-emerald-600">Approved</span>
                            <strong class="font-mono text-emerald-700">₹{{ number_format($card['approved_amount'], 2) }}</strong>
                        </div>
                        <div class="rounded-lg bg-amber-50 p-2">
                            <span class="block font-bold text-amber-600">Floating</span>
                            <strong class="font-mono text-amber-700">₹{{ number_format($card['floating_amount'], 2) }}</strong>
                        </div>
                        <div class="rounded-lg bg-orange-50 p-2">
                            <span class="block font-bold text-orange-600">Pending</span>
                            <strong class="font-mono text-orange-700">₹{{ number_format($card['pending_amount'], 2) }}</strong>
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 border-t border-slate-100 pt-3 text-xs">
                        <div>
                            <span class="block font-bold text-slate-400">Payable Balance</span>
                            <strong class="font-mono text-sky-700">₹{{ number_format($card['payable_balance'], 2) }}</strong>
                        </div>
                        <div>
                            <span class="block font-bold text-slate-400">After Approved</span>
                            <strong class="font-mono text-rose-700">₹{{ number_format($card['after_balance'], 2) }}</strong>
                        </div>
                    </div>
                </a>
            @empty
                <div class="white-card rounded-lg border border-dashed border-slate-200 p-8 text-center text-sm font-bold text-slate-400 md:col-span-2 2xl:col-span-3">
                    No shops found.
                </div>
            @endforelse
        </section>
    </div>
@endsection
