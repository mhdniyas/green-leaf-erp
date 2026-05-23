<x-layouts.app title="General Ledger">

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900">General Ledger</h1>
        <p class="text-sm text-gray-500 mt-0.5">Chronological transactions log of journal entries mapped to double-entry accounts.</p>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6">
        <form method="GET" action="{{ route('finance.ledger.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
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
                <label for="account_id" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Account Filter</label>
                <select name="account_id" id="account_id"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 bg-white">
                    <option value="">All Accounts</option>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}" {{ request('account_id') == $acc->id ? 'selected' : '' }}>
                            {{ $acc->code }} - {{ $acc->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-brand-700 transition-colors shadow-sm shadow-brand-100">
                    Apply Filters
                </button>
                @if(request()->anyFilled(['start_date', 'end_date', 'account_id']))
                <a href="{{ route('finance.ledger.index') }}" class="rounded-xl border border-gray-200 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 transition-colors text-center flex items-center justify-center">
                    Clear
                </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Transactions Log --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Journal Transactions</h2>
            <span class="text-xs text-gray-500">{{ count($transactions) }} postings</span>
        </div>

        @if($transactions->isEmpty())
        <div class="py-16 text-center">
            <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9z" />
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-900">No journal postings found</p>
            <p class="text-xs text-gray-500 mt-1">Adjust filters or record sales/purchases/expenses to populate the ledger.</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/50">
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Ref #</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Account</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Debit</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Credit</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Posted By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($transactions as $tx)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4 text-gray-600 whitespace-nowrap">
                            {{ $tx->journalEntry->entry_date->format('d M Y') }}
                        </td>
                        <td class="px-6 py-4 font-mono text-xs font-semibold text-brand-600 whitespace-nowrap">
                            {{ $tx->journalEntry->reference ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="font-mono text-xs font-semibold text-gray-700 bg-gray-100 px-1.5 py-0.5 rounded border border-gray-200">
                                {{ $tx->account->code }}
                            </span>
                            <span class="font-medium text-gray-900 ml-1.5">{{ $tx->account->name }}</span>
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-semibold text-gray-900 whitespace-nowrap">
                            @if($tx->type === 'debit')
                                INR {{ number_format((float) $tx->amount, 2) }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-semibold text-gray-900 whitespace-nowrap">
                            @if($tx->type === 'credit')
                                INR {{ number_format((float) $tx->amount, 2) }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-6 py-4 text-gray-600 max-w-xs truncate" title="{{ $tx->journalEntry->description }}">
                            {{ $tx->journalEntry->description ?? '-' }}
                        </td>
                        <td class="px-6 py-4 text-gray-500 whitespace-nowrap">
                            {{ $tx->journalEntry->createdBy->name ?? 'System' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

</x-layouts.app>
