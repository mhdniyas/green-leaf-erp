@extends('shop-owner.layouts.app')

@section('title', 'Order History')
@section('page_title', 'Order History')
@section('page_description', 'Review previous submissions, approvals, and delivery outcomes.')
@php($breadcrumbs = [['label' => 'Order History']])

@section('content')
    <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        @include('shop-owner.orders.partials.order-history-table', ['orders' => $orders])
    </section>
@endsection
