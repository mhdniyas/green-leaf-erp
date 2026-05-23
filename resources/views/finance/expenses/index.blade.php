<x-layouts.app title="Expenses">

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Expenses Log</h1>
            <p class="text-sm text-gray-500 mt-0.5">Record and monitor operational non-purchase overheads like labour, utilities, rent, etc.</p>
        </div>
        @can('accounting.entry.create')
        <a href="{{ route('finance.expenses.create') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-brand-700 transition-colors shadow-sm shadow-brand-100">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.0" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Record Expense
        </a>
        @endcan
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6">
        <form method="GET" action="{{ route('finance.expenses.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
            <div>
                <label for="start_date" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Start Date</label>
                <input type="date" name="start_date" id="start_date" value="{{ request('start_date') }}"
                       class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500" />
            </div>
            <div>
                <label for="end_date" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">End Date</label>
                <input type="date" name="end_date" id="end_date" value="{{ request('end_date') }}"
                       class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500" />
            </div>
            <div>
                <label for="account_id" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Expense Category</label>
                <select name="account_id" id="account_id"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 bg-white">
                    <option value="">All Categories</option>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}" {{ request('account_id') == $acc->id ? 'selected' : '' }}>
                            {{ $acc->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-brand-700 transition-colors shadow-sm shadow-brand-100">
                    Apply Filters
                </button>
                @if(request()->anyFilled(['start_date', 'end_date', 'account_id']))
                <a href="{{ route('finance.expenses.index') }}" class="rounded-xl border border-gray-200 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 transition-colors text-center flex items-center justify-center">
                    Clear
                </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Expenses List --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Recorded Expenses</h2>
            <span class="text-xs text-gray-500">{{ $expenses->total() }} records</span>
        </div>

        @if($expenses->isEmpty())
        <div class="py-16 text-center">
            <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-900">No expenses recorded yet</p>
            <p class="text-xs text-gray-500 mt-1">Log expenses to track operational overheads in real time.</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/50">
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Category</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Method</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Ref #</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Recorded By</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($expenses as $expense)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4 text-gray-600 whitespace-nowrap">
                            {{ $expense->expense_date->format('d M Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="font-medium text-gray-900">{{ $expense->account->name }}</span>
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-semibold text-red-600 whitespace-nowrap">
                            INR {{ number_format((float) $expense->amount, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center text-xs font-semibold border px-2.5 py-0.5 rounded-full {{ strtolower($expense->payment_method) === 'cash' ? 'bg-amber-50 text-amber-700 border-amber-100' : 'bg-blue-50 text-blue-700 border-blue-100' }}">
                                {{ ucfirst($expense->payment_method) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 font-mono text-xs text-gray-500 whitespace-nowrap">
                            {{ $expense->reference ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-gray-600 max-w-xs truncate" title="{{ $expense->description }}">
                            {{ $expense->description ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-gray-500 whitespace-nowrap">
                            {{ $expense->recordedBy->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-medium">
                            @can('accounting.entry.create')
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('finance.expenses.edit', $expense) }}" class="text-brand-600 hover:text-brand-900 font-bold">Edit</a>
                                <form action="{{ route('finance.expenses.destroy', $expense) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this expense and reverse its journal entries?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900 font-bold">Delete</button>
                                </form>
                            </div>
                            @endcan
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($expenses->hasPages())
        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/50">
            {{ $expenses->links() }}
        </div>
        @endif
        @endif
    </div>

</x-layouts.app>
