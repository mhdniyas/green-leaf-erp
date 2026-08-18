@extends('admin.cashbook.layouts.app')

@section('content')
<div x-data="bankAccountsApp()" class="space-y-6">

    <!-- Top Banner & Navigation Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 white-card p-6 rounded-3xl shadow-sm border border-slate-200">
        <div class="flex items-center gap-4">
            <div class="h-12 w-12 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-700 shadow-sm">
                <i data-lucide="landmark" class="w-6 h-6"></i>
            </div>
            <div>
                <h2 class="text-xl font-extrabold text-slate-900">Company Bank & Cash Accounts</h2>
                <p class="text-xs text-slate-500 font-medium mt-0.5">Register and manage company bank accounts, cash vaults, or merchant QR accounts for ledger settlements.</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.cashbook.finance') }}" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs transition-all flex items-center gap-1.5 shadow-sm">
                <i data-lucide="badge-dollar-sign" class="w-4 h-4"></i> Company Finance
            </a>
            <a href="{{ route('admin.cashbook.reports') }}" class="px-4 py-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold text-xs transition-all flex items-center gap-1.5 shadow-sm">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Reports
            </a>
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

    <!-- Edit Bank Account Modal -->
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-transition.opacity>
        <div class="white-card max-w-lg w-full p-6 rounded-3xl space-y-4 shadow-2xl mx-4" @click.away="showEditModal = false">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-wider text-emerald-600">Manage Account</p>
                    <h3 class="text-base font-extrabold text-slate-900">Edit Bank Account Details</h3>
                </div>
                <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-lg">✕</button>
            </div>

            <form :action="editUrl" method="POST" class="space-y-4 text-xs">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Account Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" x-model="editForm.name" required class="w-full bg-white text-xs font-semibold text-slate-800 px-3 py-2 rounded-xl border border-slate-300">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Account Type <span class="text-rose-500">*</span></label>
                        <select name="account_type" x-model="editForm.account_type" required class="w-full bg-white text-xs font-semibold text-slate-800 px-3 py-2 rounded-xl border border-slate-300">
                            <option value="bank">Bank Account</option>
                            <option value="cash">Cash Box / Vault</option>
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
                    <div class="sm:col-span-2">
                        <label class="block font-bold text-slate-700 mb-1">Opening Balance (₹)</label>
                        <input type="number" step="0.01" min="0" name="opening_balance" x-model="editForm.opening_balance" class="w-full bg-white text-xs font-mono font-semibold text-slate-800 px-3 py-2 rounded-xl border border-slate-300">
                    </div>
                    <div class="sm:col-span-2 flex items-center pt-2">
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

    <!-- Create Bank Account Form -->
    <div class="white-card p-6 rounded-3xl space-y-5 shadow-xl border border-slate-200">
        <div class="border-b border-slate-200 pb-3 flex items-center justify-between">
            <div>
                <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                    <i data-lucide="plus-circle" class="w-5 h-5 text-emerald-600"></i> New Account Registration
                </h3>
                <p class="text-xs text-slate-500">Fill in the details below to add a new account to the system.</p>
            </div>
        </div>

        <form action="{{ route('admin.cashbook.bank-accounts.store') }}" method="POST" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <!-- Account Name -->
                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Account Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" required placeholder="e.g. SBI Main Current Account" class="w-full bg-white text-xs font-semibold text-slate-800 px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>

                <!-- Account Type -->
                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Account Type <span class="text-rose-500">*</span></label>
                    <select name="account_type" required class="w-full bg-white text-xs font-semibold text-slate-800 px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="bank">Bank Account</option>
                        <option value="cash">Cash Box / Vault</option>
                        <option value="wallet">Merchant Wallet / QR Account</option>
                    </select>
                </div>

                <!-- Bank Name -->
                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Bank / Financial Institution</label>
                    <input type="text" name="bank_name" placeholder="e.g. State Bank of India, HDFC Bank" class="w-full bg-white text-xs font-semibold text-slate-800 px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>

                <!-- Account Number -->
                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Account Number / UPI ID</label>
                    <input type="text" name="account_number" placeholder="e.g. 50200084920194" class="w-full bg-white text-xs font-mono font-semibold text-slate-800 px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>

                <!-- Opening Balance -->
                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Opening Balance (₹)</label>
                    <input type="number" step="0.01" min="0" name="opening_balance" value="0.00" class="w-full bg-white text-xs font-mono font-semibold text-slate-800 px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                </div>

                <!-- Default Account Switch -->
                <div class="flex items-center pt-6">
                    <label class="inline-flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" name="is_default" value="1" class="w-4 h-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-xs font-bold text-slate-700">Set as Primary / Default Account</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-200">
                <a href="{{ route('admin.cashbook.reports') }}" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-all">Cancel</a>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-sm flex items-center gap-1.5 transition-all">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i> Create Account
                </button>
            </div>
        </form>
    </div>

    <!-- Active Company Accounts Table -->
    <div class="white-card p-6 rounded-3xl space-y-4 shadow-xl border border-slate-200">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
            <div>
                <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                    <i data-lucide="building-2" class="w-5 h-5 text-slate-700"></i> Registered Accounts Matrix
                </h3>
                <p class="text-xs text-slate-500">Click edit to update account details, bank names, or balance settings.</p>
            </div>
            <span class="text-xs font-mono font-bold text-slate-500">{{ count($companyAccounts) }} Accounts</span>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
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
                                    <button @click="openEdit({{ json_encode($acc) }})" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg text-xs font-bold transition flex items-center gap-1">
                                        <i data-lucide="edit-3" class="w-3.5 h-3.5 text-slate-600"></i> Edit
                                    </button>

                                    <a href="{{ route('admin.cashbook.finance') }}" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg text-xs font-bold transition flex items-center gap-1">
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
    </div>

</div>

@push('scripts')
<script>
    function bankAccountsApp() {
        return {
            showEditModal: false,
            editUrl: '',
            editForm: {
                id: null,
                name: '',
                account_type: 'bank',
                bank_name: '',
                account_number: '',
                opening_balance: 0,
                is_default: false,
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
            }
        };
    }
</script>
@endpush
@endsection
