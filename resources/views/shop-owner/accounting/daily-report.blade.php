@extends('shop-owner.layouts.app')

@section('title', 'Daily Accounting Report')
@section('page_title', 'Daily Report')
@section('page_description', 'Daily opening and closing balance by month.')
@php
    $breadcrumbs = [['label' => 'Accounting', 'url' => route('shop-owner.accounting.index', ['tab' => 'cashbook'])], ['label' => 'Daily Report']];
    $previousMonth = $month->copy()->subMonth();
    $nextMonth = $month->copy()->addMonth();
    $todayRow = $dailyRows->getCollection()->first(
        fn (array $row): bool => $row['date']->isSameDay(today())
    );
    $displayDate = $todayRow['date'] ?? $dailyRows->getCollection()->first()['date'] ?? $month;
    $todayPage = (int) ceil(today()->day / $dailyRows->perPage());
@endphp

@section('page_actions')
    @include('shop-owner.components.action-button', ['href' => route('shop-owner.accounting.index', ['tab' => 'cashbook']), 'label' => 'Back', 'classes' => 'border border-slate-200 bg-white text-slate-800'])
@endsection

@section('content')
    <div class="space-y-6">
        @include('shop-owner.accounting.partials.tabs', ['shop' => $shop, 'tab' => $tab])

        <section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700">Daily Balance</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">{{ $displayDate->format('d M Y') }}</h2>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('shop-owner.accounting.daily-report', ['month' => today()->format('Y-m'), 'daily_page' => $todayPage]) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-xs font-black uppercase tracking-[0.14em] text-emerald-700 transition hover:bg-emerald-100">
                        Today
                    </a>
                    <a href="{{ route('shop-owner.accounting.daily-report', ['month' => $previousMonth->format('Y-m')]) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 text-xs font-black uppercase tracking-[0.14em] text-slate-700 transition hover:bg-white">
                        Previous
                    </a>
                    <form method="GET" action="{{ route('shop-owner.accounting.daily-report') }}" class="flex items-center gap-2">
                        <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-800">
                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-950 px-4 text-xs font-black uppercase tracking-[0.14em] text-white transition hover:bg-slate-800">
                            Go
                        </button>
                    </form>
                    <a href="{{ route('shop-owner.accounting.daily-report', ['month' => $nextMonth->format('Y-m')]) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 text-xs font-black uppercase tracking-[0.14em] text-slate-700 transition hover:bg-white">
                        Next
                    </a>
                </div>
            </div>

            <div class="mt-5 overflow-x-auto rounded-[1.5rem] border border-slate-200">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3 text-right">Opening Balance</th>
                            <th class="px-4 py-3 text-right">Income</th>
                            <th class="px-4 py-3 text-right">Daily Expense</th>
                            <th class="px-4 py-3 text-right">Loan Total</th>
                            <th class="px-4 py-3 text-right">Closing Balance</th>
                            <th class="px-4 py-3 text-right">Net Difference</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($dailyRows as $row)
                            <tr>
                                <td class="px-4 py-3 font-black text-slate-950">{{ $row['date']->format('d') }}</td>
                                <td class="px-4 py-3 text-right font-black text-slate-700">Rs. {{ number_format($row['opening_balance'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-black text-emerald-600">Rs. {{ number_format($row['daily_income'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-black text-rose-600">Rs. {{ number_format($row['daily_expenses'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-black text-amber-600">Rs. {{ number_format($row['loan_total'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['closing_balance'], 2) }}</td>
                                <td @class([
                                    'px-4 py-3 text-right font-black',
                                    'text-emerald-700' => $row['net_difference'] >= 0,
                                    'text-rose-700' => $row['net_difference'] < 0,
                                ])>
                                    {{ $row['net_difference'] >= 0 ? '+' : '-' }} Rs. {{ number_format(abs($row['net_difference']), 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($dailyRows->hasPages())
                <div class="mt-5">{{ $dailyRows->withQueryString()->links() }}</div>
            @endif
        </section>
    </div>
@endsection
