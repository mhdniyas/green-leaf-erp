@extends('shop-owner.layouts.app')

@section('title', 'Petty Cash')
@section('page_title', 'Petty Cash')
@section('page_description', 'Review petty cash credit, daily expenses, balance, and update timestamps day by day.')
@php
    $breadcrumbs = [['label' => 'Accounting', 'url' => route('shop-owner.accounting.index', ['tab' => 'cashbook'])], ['label' => 'Petty Cash']];
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
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Full Petty Cash Ledger</p>
                    <h2 class="mt-2 text-xl font-black text-slate-950">Day-by-day table</h2>
                    <p class="mt-2 text-sm font-semibold {{ $pettyCashBalance >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                        {{ $pettyCashBalance < 0 ? 'Petty cash pending' : 'Petty cash balance' }} Rs. {{ number_format(abs($pettyCashBalance), 2) }}
                    </p>
                </div>

                <form method="GET" action="{{ route('shop-owner.accounting.petty-cash.index') }}" class="grid gap-2 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-2 sm:grid-cols-3">
                    <label class="rounded-2xl bg-white px-4 py-2 text-slate-900 shadow-sm">
                        <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">From</span>
                        <input type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}" max="{{ today()->toDateString() }}" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black focus:outline-none focus:ring-0">
                    </label>
                    <label class="rounded-2xl bg-white px-4 py-2 text-slate-900 shadow-sm">
                        <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">To</span>
                        <input type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" max="{{ today()->toDateString() }}" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black focus:outline-none focus:ring-0">
                    </label>
                    <button type="submit" class="inline-flex h-14 items-center justify-center rounded-2xl bg-slate-950 px-4 text-sm font-black text-white transition hover:bg-slate-800">Update</button>
                </form>
            </div>

            <div class="mt-6 overflow-x-auto rounded-[1.5rem] border border-slate-200">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Credit</th>
                            <th class="px-4 py-3 text-right">EXP</th>
                            <th class="px-4 py-3 text-right">BAL</th>
                            <th class="px-4 py-3 text-right">Last Update</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($pettyCashRows as $pettyRow)
                            <tr>
                                <td class="px-4 py-3 font-black text-slate-950">{{ \Illuminate\Support\Carbon::parse($pettyRow['date'])->format('d M Y') }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-600">{{ $pettyRow['admin_cash_label'] ?: '—' }}</td>
                                <td class="px-4 py-3 text-right font-black text-rose-700">
                                    Rs. {{ number_format((float) $pettyRow['expense'], 2) }}
                                    @if ($pettyRow['expense_source'])
                                    <span class="ml-2 rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.12em] text-slate-500">{{ $pettyRow['expense_source'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-black {{ (float) $pettyRow['balance'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Rs. {{ number_format((float) $pettyRow['balance'], 2) }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-500">
                                {{ $pettyRow['expense_updated_at'] ? $pettyRow['expense_updated_at']->format('d M Y h:i A') : '—' }}
                                    @if ($pettyRow['amount_change_label'])
                                        <span class="mt-1 block text-xs font-bold text-amber-700">{{ $pettyRow['amount_change_label'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center font-bold text-slate-500">No petty cash rows found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
