@extends('admin.cashbook.layouts.app')

@section('content')
<div x-data="bankAccountsApp()" class="mx-auto max-w-[96rem] space-y-5">

    <!-- Top Banner & Navigation Header -->
    <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-start gap-3 sm:items-center sm:gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-700 shadow-sm sm:h-12 sm:w-12">
                    <i data-lucide="landmark" class="w-6 h-6"></i>
                </div>
                <div class="min-w-0">
                    <h2 class="break-words text-xl font-extrabold text-slate-900">Company Bank &amp; Cash In Hand Accounts</h2>
                    <p class="mt-0.5 text-xs font-medium leading-relaxed text-slate-500">Register and manage company bank accounts, cash in hand, or merchant QR accounts for ledger settlements.</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 lg:w-auto">
                <button type="button" @click="openCreate()" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 text-xs font-bold text-white shadow-sm transition-all hover:bg-emerald-500">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i> Add Account
                </button>
                <a href="{{ route('admin.cashbook.finance') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-700 shadow-sm transition-all hover:bg-slate-50">
                    <i data-lucide="badge-dollar-sign" class="w-4 h-4"></i> Company Finance
                </a>
                <a href="{{ route('admin.cashbook.reports') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-700 shadow-sm transition-all hover:bg-slate-50">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Reports
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700 font-bold">✕</button>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                <span>{{ session('error') }}</span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700 font-bold">✕</button>
        </div>
    @endif

    <!-- Create Bank Account Modal -->
    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-transition.opacity>
        <div class="white-card max-w-lg w-full p-6 rounded-3xl space-y-4 shadow-2xl mx-4" @click.away="showCreateModal = false">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-wider text-emerald-600">New Account Registration</p>
                    <h3 class="text-base font-extrabold text-slate-900">Add Bank or Cash Account</h3>
                </div>
                <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-lg">✕</button>
            </div>

            <form action="{{ route('admin.cashbook.bank-accounts.store') }}" method="POST" class="space-y-4 text-xs">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block font-bold text-slate-700 mb-1">Account Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" x-model="createForm.name" required placeholder="e.g. SBI Main Current Account" class="w-full bg-white text-xs font-semibold text-slate-800 px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Account Type <span class="text-rose-500">*</span></label>
                        <select name="account_type" x-model="createForm.account_type" required class="w-full bg-white text-xs font-semibold text-slate-800 px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="bank">Bank Account</option>
                            <option value="cash">Cash In Hand</option>
                            <option value="wallet">Merchant Wallet / QR Account</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Bank / Institution</label>
                        <input type="text" name="bank_name" x-model="createForm.bank_name" placeholder="e.g. State Bank of India" class="w-full bg-white text-xs font-semibold text-slate-800 px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Account Number / UPI ID</label>
                        <input type="text" name="account_number" x-model="createForm.account_number" placeholder="e.g. 50200084920194" class="w-full bg-white text-xs font-mono font-semibold text-slate-800 px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Opening Balance (₹)</label>
                        <input type="number" step="0.01" min="0" name="opening_balance" x-model="createForm.opening_balance" class="w-full bg-white text-xs font-mono font-semibold text-slate-800 px-3 py-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                    <div class="sm:col-span-2 flex items-center pt-1">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_default" value="1" x-model="createForm.is_default" class="w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span class="text-xs font-bold text-slate-700">Set as Primary / Default Account</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-200">
                    <button type="button" @click="showCreateModal = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-sm flex items-center gap-1.5 transition">
                        <i data-lucide="plus-circle" class="w-4 h-4"></i> Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Bank Account Modal -->
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-transition.opacity>
        <div class="white-card max-w-lg w-full p-6 rounded-3xl space-y-4 shadow-2xl mx-4" @click.away="showEditModal = false">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-wider text-emerald-600">Manage Account</p>
                    <h3 class="text-base font-extrabold text-slate-900">Edit Account Details</h3>
                </div>
                <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-lg">✕</button>
            </div>

            <form :action="editUrl" method="POST" class="space-y-4 text-xs">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block font-bold text-slate-700 mb-1">Account Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" x-model="editForm.name" required class="w-full bg-white text-xs font-semibold text-slate-800 px-3 py-2 rounded-xl border border-slate-300">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Account Type <span class="text-rose-500">*</span></label>
                        <select name="account_type" x-model="editForm.account_type" required class="w-full bg-white text-xs font-semibold text-slate-800 px-3 py-2 rounded-xl border border-slate-300">
                            <option value="bank">Bank Account</option>
                            <option value="cash">Cash In Hand</option>
                            <option value="wallet">Merchant Wallet / QR Account</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Bank / Institution</label>
                        <input type="text" name="bank_name" x-model="editForm.bank_name" class="w-full bg-white text-xs font-semibold text-slate-800 px-3 py-2 rounded-xl border border-slate-300">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Account Number / UPI ID</label>
                        <input type="text" name="account_number" x-model="editForm.account_number" class="w-full bg-white text-xs font-mono font-semibold text-slate-800 px-3 py-2 rounded-xl border border-slate-300">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Opening Balance (₹)</label>
                        <input type="number" step="0.01" min="0" name="opening_balance" x-model="editForm.opening_balance" class="w-full bg-white text-xs font-mono font-semibold text-slate-800 px-3 py-2 rounded-xl border border-slate-300">
                    </div>
                    <div class="sm:col-span-2 flex items-center pt-1">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_default" value="1" x-model="editForm.is_default" class="w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span class="text-xs font-bold text-slate-700">Set as Primary / Default Account</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-200">
                    <button type="button" @click="showEditModal = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-sm flex items-center gap-1.5">
                        <i data-lucide="check" class="w-4 h-4"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Company Accounts Table -->
    <div class="white-card space-y-4 rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-6">
        <div class="flex flex-col gap-3 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                    <i data-lucide="building-2" class="w-5 h-5 text-slate-700"></i> Registered Accounts Matrix
                </h3>
                <p class="text-xs text-slate-500">Click edit to update account details, bank names, or balance settings.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-mono font-bold text-slate-500">{{ count($companyAccounts) }} Accounts</span>
                <button type="button" @click="openCreate()" class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 text-xs font-bold text-white shadow-sm transition-all hover:bg-emerald-500">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Add Account</span>
                </button>
            </div>
        </div>

        @if($companyAccounts->isEmpty())
            <div class="text-center py-12 px-4 rounded-2xl border border-dashed border-slate-200 bg-slate-50">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 mb-3">
                    <i data-lucide="landmark" class="w-6 h-6"></i>
                </div>
                <h4 class="text-sm font-bold text-slate-800">No accounts registered yet</h4>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Add your company bank accounts, cash drawers, or UPI merchant accounts to start tracking reconciliations.</p>
                <button type="button" @click="openCreate()" class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-500 shadow-sm transition">
                    <i data-lucide="plus" class="w-4 h-4"></i> Add First Account
                </button>
            </div>
        @else
            <div class="space-y-3 lg:hidden">
                @foreach($companyAccounts as $acc)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="break-words text-sm font-extrabold text-slate-950">{{ $acc->name }}</h4>
                                    @if($acc->is_default)
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-extrabold text-emerald-800">Default</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $acc->bank_name ?: '-' }}</p>
                                <p class="mt-1 break-all font-mono text-xs font-bold text-slate-600">{{ $acc->account_number ?: '-' }}</p>
                            </div>
                            <span class="shrink-0 rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-700">{{ strtoupper($acc->account_type) }}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                            <div class="rounded-xl bg-white p-2">
                                <span class="block font-bold text-slate-400">Opening</span>
                                <strong class="font-mono text-slate-700">₹{{ number_format($acc->opening_balance, 2) }}</strong>
                            </div>
                            <div class="rounded-xl bg-white p-2">
                                <span class="block font-bold text-slate-400">Current</span>
                                <strong class="font-mono text-emerald-700">₹{{ number_format($acc->current_balance, 2) }}</strong>
                            </div>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <a href="{{ route('admin.cashbook.bank-accounts.show', $acc) }}" class="inline-flex min-h-9 items-center justify-center gap-1 rounded-lg bg-white px-2 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-100">
                                <i data-lucide="eye" class="h-3.5 w-3.5"></i> Details
                            </a>
                            <a href="{{ route('admin.cashbook.bank-accounts.statement', $acc) }}" class="inline-flex min-h-9 items-center justify-center gap-1 rounded-lg bg-emerald-50 px-2 text-xs font-bold text-emerald-700 hover:bg-emerald-100">
                                <i data-lucide="list-checks" class="h-3.5 w-3.5"></i> Statement
                            </a>
                            <button @click="openEdit({{ json_encode($acc) }})" class="inline-flex min-h-9 items-center justify-center gap-1 rounded-lg bg-slate-100 px-2 text-xs font-bold text-slate-800 transition hover:bg-slate-200">
                                <i data-lucide="edit-3" class="h-3.5 w-3.5 text-slate-600"></i> Edit
                            </button>
                            <form action="{{ route('admin.cashbook.bank-accounts.delete', $acc->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this bank account?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex min-h-9 w-full items-center justify-center gap-1 rounded-lg bg-rose-50 px-2 text-xs font-bold text-rose-700 transition hover:bg-rose-100">
                                    <i data-lucide="trash-2" class="h-3.5 w-3.5 text-rose-600"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="hidden overflow-x-auto custom-scrollbar lg:block">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-600 bg-slate-100/80 border-b border-slate-200 uppercase tracking-wider font-bold">
                            <th class="py-3 px-3">Account Name</th>
                            <th class="py-3 px-3">Type</th>
                            <th class="py-3 px-3">Bank / Provider</th>
                            <th class="py-3 px-3">Account Number</th>
                            <th class="py-3 px-3 text-right">Opening Balance</th>
                            <th class="py-3 px-3 text-right">Current Balance</th>
                            <th class="py-3 px-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                        @foreach($companyAccounts as $acc)
                            <tr class="hover:bg-slate-50 transition-all">
                                <td class="py-3 px-3 font-bold text-slate-900 flex items-center gap-2">
                                    {{ $acc->name }}
                                    @if($acc->is_default)
                                        <span class="px-2 py-0.5 text-[9px] font-extrabold bg-emerald-100 text-emerald-800 rounded-full">Default</span>
                                    @endif
                                </td>
                                <td class="py-3 px-3">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ strtoupper($acc->account_type) }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 font-semibold text-slate-700">{{ $acc->bank_name ?: '-' }}</td>
                                <td class="py-3 px-3 font-mono font-bold text-slate-700">{{ $acc->account_number ?: '-' }}</td>
                                <td class="py-3 px-3 text-right font-mono text-slate-600">₹{{ number_format($acc->opening_balance, 2) }}</td>
                                <td class="py-3 px-3 text-right font-mono font-bold text-emerald-600">₹{{ number_format($acc->current_balance, 2) }}</td>
                                <td class="py-3 px-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <a href="{{ route('admin.cashbook.bank-accounts.show', $acc) }}" class="px-2.5 py-1 bg-white hover:bg-slate-100 text-slate-700 rounded-lg text-xs font-bold transition flex items-center gap-1 border border-slate-200">
                                            <i data-lucide="eye" class="w-3.5 h-3.5 text-slate-600"></i> Details
                                        </a>
                                        <button @click="openEdit({{ json_encode($acc) }})" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg text-xs font-bold transition flex items-center gap-1">
                                            <i data-lucide="edit-3" class="w-3.5 h-3.5 text-slate-600"></i> Edit
                                        </button>

                                        <a href="{{ route('admin.cashbook.bank-accounts.statement', $acc) }}" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg text-xs font-bold transition flex items-center gap-1">
                                            <i data-lucide="list-checks" class="w-3.5 h-3.5 text-emerald-600"></i> Statement
                                        </a>

                                        <form action="{{ route('admin.cashbook.bank-accounts.delete', $acc->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this bank account?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-2 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg text-xs font-bold transition flex items-center gap-1">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5 text-rose-600"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>

@push('scripts')
<script>
    function bankAccountsApp() {
        return {
            showCreateModal: false,
            showEditModal: false,
            editUrl: '',
            createForm: {
                name: '',
                account_type: 'bank',
                bank_name: '',
                account_number: '',
                opening_balance: '0.00',
                is_default: false,
            },
            editForm: {
                id: null,
                name: '',
                account_type: 'bank',
                bank_name: '',
                account_number: '',
                opening_balance: 0,
                is_default: false,
            },

            openCreate() {
                this.createForm = {
                    name: '',
                    account_type: 'bank',
                    bank_name: '',
                    account_number: '',
                    opening_balance: '0.00',
                    is_default: false,
                };
                this.showCreateModal = true;
                this.$nextTick(() => {
                    if (window.lucide) { lucide.createIcons(); }
                });
            },

            openEdit(acc) {
                this.editForm = {
                    id: acc.id,
                    name: acc.name,
                    account_type: acc.account_type,
                    bank_name: acc.bank_name || '',
                    account_number: acc.account_number || '',
                    opening_balance: acc.opening_balance,
                    is_default: acc.is_default ? true : false,
                };
                this.editUrl = `/admin/cashbook/bank-accounts/${acc.id}`;
                this.showEditModal = true;
                this.$nextTick(() => {
                    if (window.lucide) { lucide.createIcons(); }
                });
            }
        };
    }
</script>
@endpush
@endsection
