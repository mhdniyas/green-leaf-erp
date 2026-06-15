@extends('shop-owner.layouts.app')

@section('title', 'Approval History')
@section('page_title', 'Approval History')
@section('page_description', 'Review previous cart submissions, approvals, and delivery outcomes.')
@php($breadcrumbs = [['label' => 'Approval History']])

@section('content')
    <div class="space-y-6">
        @include('shop-owner.orders.partials.order-tabs')

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            @include('shop-owner.orders.partials.order-history-table', ['orders' => $orders])
        </section>
    </div>
@endsection
