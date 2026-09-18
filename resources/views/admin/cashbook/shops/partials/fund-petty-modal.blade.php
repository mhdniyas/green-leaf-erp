<!-- FUND SHOP PETTY MODAL -->
<div x-show="showFundPettyModal"
     x-cloak
     @keydown.escape.window="showFundPettyModal = false"
     class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div @click.away="showFundPettyModal = false"
         class="bg-white rounded-3xl max-w-lg w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        <div class="px-6 py-5 bg-gradient-to-r from-emerald-800 to-teal-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="p-2 rounded-xl bg-white/10">
                    <i data-lucide="coins" class="w-5 h-5 text-emerald-300"></i>
                </div>
                <div>
                    <h3 class="text-sm font-black uppercase tracking-wide">Fund Shop Petty Cash</h3>
                    <p class="text-[11px] text-emerald-200 font-medium">Company Account &rarr; {{ $currentShop->name }} Petty Float</p>
                </div>
            </div>
            <button type="button" @click="showFundPettyModal = false" class="p-1 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST"
              action="{{ route('admin.cashbook.shop.petty.fund', $currentShop->slug ?: $currentShop->shop_id) }}"
              class="p-6 space-y-4 text-xs font-medium text-slate-700"
              @submit="isSubmitting = true">
            @csrf

            <!-- Warning / Info Alert -->
            <div class="p-3 bg-emerald-50 rounded-2xl border border-emerald-100 flex items-start gap-2.5 text-slate-600 text-xs">
                <i data-lucide="info" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5"></i>
                <span>
                    This records an outgoing payment from the selected company account and increases <strong>{{ $currentShop->name }}</strong> petty cash float without affecting shop sales, payable, or settlement obligations.
                </span>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <!-- Amount -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">
                        Amount (₹) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 font-bold">₹</span>
                        <input type="number"
                               step="0.01"
                               min="0.01"
                               name="amount"
                               required
                               placeholder="5000.00"
                               class="w-full pl-7 pr-3 py-2 bg-slate-50 rounded-xl border border-slate-300 font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    </div>
                </div>

                <!-- Business Date -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">
                        Business Date <span class="text-rose-500">*</span>
                    </label>
                    <input type="date"
                           name="business_date"
                           value="{{ (!empty($isDayDetail) && !empty($businessDate)) ? $businessDate : today()->toDateString() }}"
                           required
                           class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-300 font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                </div>
            </div>

            <!-- Company Account -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">
                    Company Funding Account <span class="text-rose-500">*</span>
                </label>
                <select name="company_account_id"
                        required
                        class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-300 font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    <option value="">Select funding account...</option>
                    @foreach($pettyFundingCompanyAccounts as $account)
                        <option value="{{ $account->id }}" {{ $account->is_default ? 'selected' : '' }}>
                            {{ $account->name }} ({{ ucfirst($account->account_type) }}) &mdash; Available: ₹{{ number_format((float) $account->current_balance, 2) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Reference -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">
                    Reference / Cheque / UTR
                </label>
                <input type="text"
                       name="reference"
                       placeholder="e.g. SHOP-PETTY-{{ $currentShop->code }}-{{ date('Ymd') }}"
                       class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-300 font-mono text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-600 focus:outline-none">
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">
                    Notes / Purpose
                </label>
                <textarea name="notes"
                          rows="2"
                          placeholder="Optional notes regarding this petty cash top-up..."
                          class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-300 text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-600 focus:outline-none"></textarea>
            </div>

            <!-- Modal Footer -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                <button type="button"
                        @click="showFundPettyModal = false"
                        class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 font-bold hover:bg-slate-50 transition">
                    Cancel
                </button>
                <button type="submit"
                        :disabled="isSubmitting"
                        class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-black shadow-sm transition flex items-center gap-1.5 disabled:opacity-50">
                    <i data-lucide="coins" class="w-4 h-4"></i>
                    <span x-text="isSubmitting ? 'Funding...' : 'Fund Petty Cash'">Fund Petty Cash</span>
                </button>
            </div>
        </form>
    </div>
</div>
