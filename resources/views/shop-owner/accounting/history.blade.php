@extends('shop-owner.layouts.app')

@section('title', 'Accounting History')
@section('page_title', 'Accounting History')
@section('page_description', 'Check previous daily accounting requests, approval status, and any admin recheck notes by date.')
@php
    $breadcrumbs = [['label' => 'Accounting', 'url' => route('shop-owner.accounting.index')], ['label' => 'History']];
@endphp

@section('page_actions')
    @include('shop-owner.components.action-button', ['href' => route('shop-owner.accounting.index'), 'label' => 'Daily', 'classes' => 'border border-slate-200 bg-white text-slate-800'])
@endsection

@section('content')
    <div class="space-y-6">
        @include('shop-owner.accounting.partials.tabs')

        <section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            @include('shop-owner.accounting.partials.history-table', ['entries' => $entries])
        </section>
    </div>
@endsection
