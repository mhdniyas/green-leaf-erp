@extends('shop-owner.layouts.app')

@section('title', 'Finance Details')
@section('page_title', 'Finance Details')
@section('page_description', 'Review payment status, balance, delivery shortages, and finance notes for this order.')
@php($breadcrumbs = [['label' => 'Finance', 'url' => route('shop-owner.finance.index')], ['label' => $order->order_number]])

@section('content')
    <div class="space-y-6">
        @include('shop-owner.finance.partials.invoice-card', ['order' => $order])
        @include('shop-owner.finance.partials.finance-note-form', ['order' => $order])
    </div>
@endsection
