<section id="bank-accounts" class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-6">
    <div class="mb-4 flex flex-col gap-3 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="flex items-center gap-2 text-base font-extrabold text-slate-900">
                <i data-lucide="landmark" class="h-5 w-5 text-emerald-600"></i> Green Leaf — Bank & Cash Accounts
            </h3>
            <p class="mt-0.5 text-xs font-medium text-slate-500">Company bank accounts, cash vault, and merchant QR accounts.</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row">
            <a href="{{ route('admin.cashbook.finance') }}" class="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-3.5 text-xs font-bold text-white shadow-sm transition-all hover:bg-slate-800 sm:w-auto">
                <i data-lucide="badge-dollar-sign" class="h-3.5 w-3.5"></i> Company Finance
            </a>
            <a href="{{ route('admin.cashbook.bank-accounts.create') }}" class="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 text-xs font-bold text-white shadow-sm transition-all hover:bg-emerald-500 sm:w-auto">
                <i data-lucide="plus-circle" class="h-3.5 w-3.5"></i> Add Bank Account
            </a>
        </div>
    </div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($companyAccounts as $acc)
            <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-1 text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                        <i data-lucide="{{ $acc->account_type === 'bank' ? 'landmark' : ($acc->account_type === 'cash' ? 'wallet' : 'smartphone') }}" class="h-3 w-3 text-slate-400"></i>
                        {{ strtoupper($acc->account_type) }}
                    </span>
                    @if($acc->is_default)
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-extrabold text-emerald-800">Default</span>
                    @endif
                </div>
                <h4 class="break-words text-sm font-extrabold leading-tight text-slate-900">{{ $acc->name }}</h4>
                <span class="block break-all text-[10px] font-mono text-slate-500">{{ $acc->bank_name ?: strtoupper($acc->account_type) }} • {{ $acc->account_number ?: 'MAIN' }}</span>
                <div class="flex items-baseline justify-between border-t border-slate-200 pt-1">
                    <span class="text-[10px] font-bold text-slate-400">Current Balance:</span>
                    <strong class="font-mono text-base font-extrabold text-emerald-600">₹{{ number_format($acc->current_balance, 2) }}</strong>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <a href="{{ route('admin.cashbook.bank-accounts.show', $acc) }}" class="inline-flex min-h-9 items-center justify-center gap-1 rounded-lg bg-white px-2 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-100">
                        <i data-lucide="eye" class="h-3.5 w-3.5"></i> Details
                    </a>
                    <a href="{{ route('admin.cashbook.bank-accounts.statement', $acc) }}" class="inline-flex min-h-9 items-center justify-center gap-1 rounded-lg bg-emerald-50 px-2 text-xs font-bold text-emerald-700 hover:bg-emerald-100">
                        <i data-lucide="list-checks" class="h-3.5 w-3.5"></i> Statement
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</section>
