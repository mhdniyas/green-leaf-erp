@extends('purchaser.business-days.layouts.app')

@section('title', 'Close Business Day · ' . $day->business_date->format('d M Y') . ' — ' . $warehouse->name)
@section('page_title', 'Close Business Day')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <!-- Status Header -->
    @include('purchaser.business-days.close.partials.status-header')

    <!-- Summary Counts -->
    @include('purchaser.business-days.close.partials.summary-cards')

    <!-- Compact Checklist with Drilldown Links -->
    @include('purchaser.business-days.close.partials.checklist')

    <!-- Pending Items Preview if any -->
    @include('purchaser.business-days.close.partials.pending-list')

    <!-- Close Form Action -->
    @include('purchaser.business-days.close.partials.close-form')
</div>
@endsection
