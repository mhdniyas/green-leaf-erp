@extends('shop-owner.layouts.app')

@section('title', 'Approval History')
@section('page_title', 'Approval History')
@section('page_description', 'Review previous cart submissions, approvals, and delivery outcomes.')
@php($breadcrumbs = [['label' => 'Approval History']])

@section('content')
    <div class="space-y-3 sm:space-y-4">
        @include('shop-owner.orders.partials.order-tabs')

        @include('shop-owner.partials.date-range-filter', [
            'action' => route('shop-owner.orders.history'),
            'startDate' => $filterStartDate,
            'endDate' => $filterEndDate,
            'clearUrl' => route('shop-owner.orders.history'),
        ])

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs sm:p-4">
            @include('shop-owner.orders.partials.order-history-table', ['orders' => $orders])
        </section>
    </div>
@endsection
