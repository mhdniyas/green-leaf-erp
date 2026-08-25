@extends('admin.cashbook.layouts.app')

@section('content')
<div class="mx-auto max-w-3xl space-y-5 p-6">
    <a href="{{ route('admin.cashbook.finance.income-expense', ['type' => $entry->type]) }}" class="text-sm font-bold text-emerald-700">Back</a>
    <div class="rounded-2xl bg-white p-6 shadow-sm"><h1 class="text-2xl font-black">{{ ucfirst($entry->type) }} details</h1><dl class="mt-5 grid gap-4 sm:grid-cols-2"><div><dt>Category</dt><dd class="font-bold">{{ $entry->category?->name }}</dd></div><div><dt>Amount</dt><dd class="font-bold">₹{{ number_format($entry->amount, 2) }}</dd></div><div><dt>Company account</dt><dd class="font-bold">{{ $entry->companyAccount?->name }}</dd></div><div><dt>Status</dt><dd class="font-bold">{{ $entry->cashbookMovement?->is_finalized ? 'FINALIZED' : 'PENDING RECONCILIATION' }}</dd></div><div><dt>Reference</dt><dd class="font-bold">{{ $entry->reference ?: '—' }}</dd></div><div><dt>Journal</dt><dd class="font-bold">{{ $entry->journalEntry?->formatted_reference }}</dd></div></dl><p class="mt-5 text-sm text-slate-600">{{ $entry->description }}</p></div>
</div>
@endsection
