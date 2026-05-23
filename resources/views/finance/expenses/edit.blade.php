<x-layouts.app title="Edit Expense">

    <div class="mb-6">
        <a href="{{ route('finance.expenses.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </svg>
            Back to Expenses
        </a>
        <h1 class="text-xl font-bold text-gray-900 mt-4">Edit Expense #{{ $expense->id }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">Modifying this expense will automatically recalculate and update the corresponding General Ledger double entries.</p>
    </div>

    <div class="max-w-2xl bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <form method="POST" action="{{ route('finance.expenses.update', $expense) }}" class="p-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                {{-- Date --}}
                <div>
                    <label for="expense_date" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Date *</label>
                    <input type="date" name="expense_date" id="expense_date" value="{{ old('expense_date', $expense->expense_date->format('Y-m-d')) }}" required
                           class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 @error('expense_date') border-red-500 @enderror" />
                    @error('expense_date')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Expense Category --}}
                <div>
                    <label for="account_id" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Category (Expense Account) *</label>
                    <select name="account_id" id="account_id" required
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 bg-white @error('account_id') border-red-500 @enderror">
                        <option value="">Select Expense Category</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ old('account_id', $expense->account_id) == $acc->id ? 'selected' : '' }}>
                                {{ $acc->code }} - {{ $acc->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('account_id')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Amount --}}
                <div>
                    <label for="amount" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Amount (INR) *</label>
                    <input type="number" name="amount" id="amount" step="0.01" min="0.01" value="{{ old('amount', $expense->amount) }}" required placeholder="e.g. 5000.00"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 @error('amount') border-red-500 @enderror" />
                    @error('amount')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Payment Method --}}
                <div>
                    <label for="payment_method" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Payment Method *</label>
                    <select name="payment_method" id="payment_method" required
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 bg-white @error('payment_method') border-red-500 @enderror">
                        <option value="cash" {{ old('payment_method', $expense->payment_method) == 'cash' ? 'selected' : '' }}>Cash on Hand (Asset Account 1010)</option>
                        <option value="bank" {{ old('payment_method', $expense->payment_method) == 'bank' ? 'selected' : '' }}>Bank Account (Asset Account 1020)</option>
                    </select>
                    @error('payment_method')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Reference --}}
                <div class="col-span-full">
                    <label for="reference" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Reference / Invoice #</label>
                    <input type="text" name="reference" id="reference" value="{{ old('reference', $expense->reference) }}" placeholder="e.g. UT-MAY-2026, Rent-Inv-05"
                           class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 @error('reference') border-red-500 @enderror" />
                    @error('reference')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Description --}}
                <div class="col-span-full">
                    <label for="description" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Description / Notes</label>
                    <textarea name="description" id="description" rows="3" placeholder="Describe the purpose of this expense..."
                              class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 @error('description') border-red-500 @enderror">{{ old('description', $expense->description) }}</textarea>
                    @error('description')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center gap-3 border-t border-gray-100 pt-6">
                <button type="submit" class="rounded-xl bg-brand-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-brand-700 transition-colors shadow-sm shadow-brand-100">
                    Update Posted Expense
                </button>
                <a href="{{ route('finance.expenses.index') }}" class="rounded-xl border border-gray-200 px-5 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>

</x-layouts.app>
