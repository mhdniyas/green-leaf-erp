<x-layouts.app title="Chart of Accounts">

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Chart of Accounts</h1>
            <p class="text-sm text-gray-500 mt-0.5">Primary ledger accounts representing assets, liabilities, equity, revenue, and expenses.</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">All Ledger Accounts</h2>
            <span class="text-xs text-gray-500">{{ count($accounts) }} accounts</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/50">
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Code</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Account Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Current Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($accounts as $account)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4 font-mono text-xs font-bold text-gray-900">
                            {{ $account->code }}
                        </td>
                        <td class="px-6 py-4">
                            <span class="font-medium text-gray-900">{{ $account->name }}</span>
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $typeColor = match(strtolower($account->type)) {
                                    'asset' => 'bg-blue-50 text-blue-700 border-blue-100',
                                    'liability' => 'bg-amber-50 text-amber-700 border-amber-100',
                                    'equity' => 'bg-purple-50 text-purple-700 border-purple-100',
                                    'revenue' => 'bg-green-50 text-green-700 border-green-100',
                                    'expense' => 'bg-red-50 text-red-700 border-red-100',
                                    default => 'bg-gray-50 text-gray-700 border-gray-100'
                                };
                            @endphp
                            <span class="inline-flex items-center text-xs font-semibold border px-2.5 py-0.5 rounded-full {{ $typeColor }}">
                                {{ ucfirst($account->type) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if($account->is_active)
                            <span class="inline-flex items-center gap-1.5 text-xs text-green-700 font-semibold">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-600"></span> Active
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1.5 text-xs text-gray-500 font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Inactive
                            </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-semibold text-gray-900">
                            INR {{ number_format((float) $account->balance, 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</x-layouts.app>
