@extends('shop-owner.layouts.app')

@section('title', 'Deliveries')
@section('page_title', 'Deliveries')
@section('page_description', 'Track allocated orders, verify received quantities, and review delivery history.')
@php($breadcrumbs = [['label' => 'Deliveries']])

@section('content')
    <div class="space-y-6">
        @include('shop-owner.partials.date-range-filter', [
            'action' => route('shop-owner.deliveries.index'),
            'startDate' => $filterStartDate,
            'endDate' => $filterEndDate,
            'clearUrl' => route('shop-owner.deliveries.index'),
        ])

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            @include('shop-owner.deliveries.partials.delivery-history-table', ['deliveries' => $deliveries])
        </section>
    </div>
@endsection
