@extends('shop-owner.layouts.app')

@section('title', 'Create Order')
@section('page_title', 'Create Tomorrow Order')
@section('page_description', 'Build tomorrow’s shop order with suggested quantities from the previous business day.')
@php($breadcrumbs = [['label' => 'Daily Orders', 'url' => route('shop-owner.orders.index')], ['label' => 'Create']])

@section('content')
    <div class="space-y-6">
        @include('shop-owner.orders.partials.order-tabs')
        @include('shop-owner.orders.partials.order-form', [
            'productsByCategory' => $productsByCategory,
            'frequentProducts' => $frequentProducts,
            'tomorrowOrder' => $tomorrowOrder,
            'yesterdayOrder' => $yesterdayOrder,
            'presets' => $presets,
            'tomorrowDate' => $tomorrowDate,
            'cutoffPassed' => $cutoffPassed,
        ])
    </div>
@endsection
