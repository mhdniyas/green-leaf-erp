@extends('admin.cashbook.layouts.app')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 p-6">
    <div class="flex items-center justify-between">
        <div><h1 class="text-2xl font-black text-slate-950">Other Income & Expense</h1><p class="text-sm text-slate-500">Pending movements stay outside Approved Transactions until reconciled.</p></div>
        <a href="{{ route('admin.cashbook.finance.reconciliation') }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white">Reconcile movements</a>
    </div>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>@endif

    <div class="flex gap-2">
        <a href="{{ route('admin.cashbook.finance.income-expense', ['type' => 'income']) }}" class="rounded-xl px-4 py-2 text-sm font-bold {{ $activeType === 'income' ? 'bg-emerald-600 text-white' : 'bg-white text-slate-700' }}">Income</a>
        <a href="{{ route('admin.cashbook.finance.income-expense', ['type' => 'expense']) }}" class="rounded-xl px-4 py-2 text-sm font-bold {{ $activeType === 'expense' ? 'bg-rose-600 text-white' : 'bg-white text-slate-700' }}">Expense</a>
    </div>

    @php($categories = $activeType === 'income' ? $incomeCategories : $expenseCategories)
    <form method="POST" action="{{ route('admin.cashbook.finance.income-expense.store') }}" class="grid gap-4 rounded-2xl bg-white p-5 shadow-sm md:grid-cols-3">
        @csrf
        <input type="hidden" name="type" value="{{ $activeType }}"><input type="hidden" name="request_uuid" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        <select name="company_accounting_category_id" required class="rounded-xl border-slate-300"><option value="">{{ ucfirst($activeType) }} category</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}{{ $category->account ? ' - '.$category->account->code : ' (configure account)' }}</option>@endforeach</select>
        <input name="amount" type="number" min="0.01" step="0.01" required placeholder="Amount" class="rounded-xl border-slate-300">
        <input name="business_date" type="date" value="{{ today()->toDateString() }}" required class="rounded-xl border-slate-300">
        <select name="company_account_uuid" required class="rounded-xl border-slate-300"><option value="">Company cash / bank</option>@foreach($companyAccounts as $account)<option value="{{ $account->public_uuid }}" @selected(App\Models\Cashbook\CompanyAccount::isSelected($account, old('company_account_uuid'), $companyAccounts, null, 'public_uuid'))>{{ $account->name }} ({{ strtoupper($account->account_type) }})</option>@endforeach</select>
        <input name="reference" placeholder="Reference" class="rounded-xl border-slate-300"><input name="description" placeholder="Notes / Description (required for Other)" class="rounded-xl border-slate-300">
        <button class="rounded-xl {{ $activeType === 'income' ? 'bg-emerald-600' : 'bg-rose-600' }} px-4 py-2 font-bold text-white">Record Other {{ ucfirst($activeType) }}</button>
    </form>

    <form method="GET" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm md:grid-cols-5"><input type="hidden" name="type" value="{{ $activeType }}"><input name="start_date" type="date" value="{{ request('start_date') }}" class="rounded-xl border-slate-300"><input name="end_date" type="date" value="{{ request('end_date') }}" class="rounded-xl border-slate-300"><select name="status" class="rounded-xl border-slate-300"><option value="">All status</option><option value="pending">Pending</option><option value="finalized">Finalized</option></select><input name="search" value="{{ request('search') }}" placeholder="Search" class="rounded-xl border-slate-300"><button class="rounded-xl bg-slate-900 px-4 py-2 font-bold text-white">Filter</button></form>

    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm"><table class="w-full text-sm"><thead class="bg-slate-50 text-left text-slate-500"><tr><th class="p-3">Date</th><th>Category</th><th>Amount</th><th>Company Account</th><th>Reference</th><th>Reconciliation</th><th></th></tr></thead><tbody>@forelse($entries as $entry)<tr class="border-t"><td class="p-3">{{ $entry->business_date->format('d M Y') }}</td><td>{{ $entry->category?->name }}</td><td class="font-bold">₹{{ number_format($entry->amount, 2) }}</td><td>{{ $entry->companyAccount?->name }}</td><td>{{ $entry->reference }}</td><td>{{ $entry->cashbookMovement?->is_finalized ? 'FINALIZED' : 'PENDING' }}</td><td><a class="font-bold text-emerald-700" href="{{ route('admin.cashbook.finance.income-expense.show', $entry) }}">View Details</a></td></tr>@empty<tr><td colspan="7" class="p-6 text-center text-slate-500">No entries.</td></tr>@endforelse</tbody></table></div>
    {{ $entries->links() }}
</div>
@endsection
