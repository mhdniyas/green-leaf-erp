@extends('shop-owner.layouts.app')

@section('title', 'Deliveries')
@section('page_title', 'Deliveries')
@section('page_description', 'Track priced invoices, warehouse dispatch, delivery verification, and admin review.')
@php($breadcrumbs = [['label' => 'Deliveries']])

@section('content')
    <div class="space-y-3 sm:space-y-4">
        {{-- Top Summary Cards (2x1 Grid) --}}
        <div class="grid grid-cols-2 gap-2 sm:gap-3">
            {{-- Card 1: Total Pending Bill Till Today --}}
            <div class="relative flex flex-col justify-between overflow-hidden rounded-xl bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-950 p-2.5 sm:p-3.5 text-white shadow-xs">
                <div>
                    <div class="flex items-center justify-between gap-1">
                        <p class="text-[8px] font-black uppercase tracking-wider text-emerald-400 sm:text-[10px]">Pending Bill Till Today</p>
                        <span class="inline-flex rounded-full bg-emerald-500/20 px-1.5 py-0.5 text-[8px] font-bold text-emerald-300 sm:text-[10px]">
                            {{ $pendingBillCountTillToday ?? 0 }} Unpaid
                        </span>
                    </div>
                    <p class="mt-1 whitespace-nowrap truncate text-xs font-black text-white sm:text-lg">
                        Rs. {{ number_format((float) ($pendingBillTillToday ?? 0), 2) }}
                    </p>
                </div>
                <div class="mt-2 border-t border-slate-700/50 pt-1 text-[9px] text-slate-300 sm:text-xs">
                    <a href="{{ route('shop-owner.finance.index') }}" class="font-bold text-emerald-400 hover:text-emerald-300 hover:underline">
                        View Finance &rarr;
                    </a>
                </div>
            </div>

            {{-- Card 2: Total Deliveries --}}
            <div class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-2.5 sm:p-3.5 shadow-xs">
                <div>
                    <p class="text-[8px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">Total Deliveries</p>
                    <p class="mt-1 whitespace-nowrap truncate text-xs font-black text-slate-900 sm:text-lg">
                        {{ $deliveries->total() }} Deliveries
                    </p>
                </div>
                <p class="mt-2 border-t border-slate-100 pt-1 text-[9px] font-medium text-slate-500 sm:text-xs">
                    Page {{ $deliveries->currentPage() }} of {{ $deliveries->lastPage() }}
                </p>
            </div>
        </div>

        {{-- Date Filter --}}
        @include('shop-owner.partials.date-range-filter', [
            'action' => route('shop-owner.deliveries.index'),
            'startDate' => $filterStartDate,
            'endDate' => $filterEndDate,
            'clearUrl' => route('shop-owner.deliveries.index'),
        ])

        {{-- Deliveries List Section --}}
        <section class="rounded-xl border border-slate-200 bg-white p-2.5 sm:rounded-2xl sm:p-4 shadow-xs">
            @include('shop-owner.deliveries.partials.delivery-history-table', ['deliveries' => $deliveries])
        </section>
    </div>
@endsection
