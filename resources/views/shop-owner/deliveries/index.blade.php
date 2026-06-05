@extends('shop-owner.layouts.app')

@section('title', 'Deliveries')
@section('page_title', 'Deliveries')
@section('page_description', 'Track allocated orders, verify received quantities, and review delivery history.')
@php($breadcrumbs = [['label' => 'Deliveries']])

@section('content')
    <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        @include('shop-owner.deliveries.partials.delivery-history-table', ['deliveries' => $deliveries])
    </section>
@endsection
