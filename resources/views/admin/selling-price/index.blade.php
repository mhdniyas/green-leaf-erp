<x-layouts.admin title="Selling Price Management">
    <div class="mx-auto max-w-5xl space-y-6">
        {{-- Header Section --}}
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Administration</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Selling Price Management</h1>
                    <p class="mt-2 max-w-2xl text-sm font-semibold leading-6 text-slate-500">
                        Configure global retail markups, matrices, and shop pricing rules.
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('admin.overview') }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 transition hover:bg-slate-50">
                        Back to Admin
                    </a>
                </div>
            </div>
        </section>

        {{-- Flash Notification --}}
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        {{-- Module Container --}}
        <div class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-sm">
            {{-- Navigation Tabs --}}
            <div class="flex gap-1 overflow-x-auto border-b border-slate-200 bg-slate-50 p-2" role="tablist" aria-label="Selling price management sections">
                {{-- Tab 1: General Pricing (Active) --}}
                <a href="{{ route('admin.selling-price.index') }}"
                   role="tab"
                   aria-selected="true"
                   class="inline-flex items-center gap-2 shrink-0 rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-black text-white shadow-xs">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
                    </svg>
                    General Pricing
                </a>

                {{-- Tab 2: Price Categories --}}
                <a href="{{ route('admin.selling-price.categories.index') }}"
                   role="tab"
                   aria-selected="false"
                   class="inline-flex items-center gap-2 shrink-0 rounded-xl px-4 py-2.5 text-xs font-black text-slate-600 transition hover:bg-white hover:text-slate-900">
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                    </svg>
                    <span>Price Categories</span>
                </a>

                {{-- Tab 3: Product Matrix --}}
                <a href="{{ route('admin.selling-price.matrix.index') }}"
                   role="tab"
                   aria-selected="false"
                   class="inline-flex items-center gap-2 shrink-0 rounded-xl px-4 py-2.5 text-xs font-black text-slate-600 transition hover:bg-white hover:text-slate-900">
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                    </svg>
                    <span>Product Matrix</span>
                </a>

                {{-- Tab 4: Shop Pricing --}}
                <a href="{{ route('admin.selling-price.shop-pricing.index') }}"
                   role="tab"
                   aria-selected="false"
                   class="inline-flex items-center gap-2 shrink-0 rounded-xl px-4 py-2.5 text-xs font-black text-slate-600 transition hover:bg-white hover:text-slate-900">
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                    </svg>
                    <span>Shop Pricing</span>
                </a>
            </div>

            {{-- General Pricing Panel Content --}}
            <div class="p-5 sm:p-6 max-w-4xl">
                <form method="POST" action="{{ route('admin.selling-price.update-general') }}" id="selling-price-form" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-teal-600">Pricing Configuration</p>
                        <h2 class="mt-1 text-lg font-black text-slate-950">Shop Owner Selling Price</h2>
                        <p class="mt-1 text-sm font-semibold leading-6 text-slate-500">
                            Configure the default retail markup used to calculate the recommended minimum retail price shown to Shop Owners.
                        </p>
                    </div>

                    {{-- Default Markup Input Card --}}
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-xs space-y-4">
                        <div>
                            <label for="selling_price_default_percentage" class="text-xs font-bold text-slate-800">
                                Default Retail Markup (%)
                            </label>
                            <p class="mt-1 text-xs font-semibold text-slate-500">
                                Percentage added on top of the Supply Price to calculate the recommended Min Retail Price.
                            </p>
                        </div>

                        <div class="max-w-xs">
                            <div class="relative">
                                <input
                                    id="selling_price_default_percentage"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="500"
                                    name="selling_price_default_percentage"
                                    value="{{ old('selling_price_default_percentage', $sellingPriceDefaultPercentage) }}"
                                    required
                                    class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 pr-8 text-sm font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none"
                                >
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-black text-slate-400">%</span>
                            </div>
                            <p class="mt-1.5 text-[11px] text-slate-400 font-semibold">Standard default: 25% · Accepted range: 0% – 500%</p>
                            @error('selling_price_default_percentage')
                                <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Live Calculation Preview --}}
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5 space-y-3">
                        <div class="flex items-center justify-between">
                            <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-700">Live Calculation Preview</p>
                            <span class="rounded-full bg-slate-200/70 px-2 py-0.5 text-[10px] font-bold text-slate-600">Illustrative Example</span>
                        </div>
                        <p class="text-xs font-semibold text-slate-500">
                            Demonstrates the recommended Min Retail Price based on an illustrative supply price of ₹100.00 (not an actual product price).
                        </p>

                        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                            <table class="w-full text-left text-xs">
                                <thead class="border-b border-slate-100 bg-slate-50/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th class="px-4 py-2.5">Calculation</th>
                                        <th class="px-4 py-2.5 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                                    <tr>
                                        <td class="px-4 py-2.5 text-slate-600">Example Supply Price</td>
                                        <td class="px-4 py-2.5 text-right font-bold text-slate-900">₹100.00</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-2.5 text-slate-600">Retail Markup</td>
                                        <td id="preview-markup-percent" class="px-4 py-2.5 text-right font-bold text-teal-700">
                                            {{ $sellingPriceDefaultPercentage }}%
                                        </td>
                                    </tr>
                                    <tr class="bg-teal-50/50 font-black text-slate-900">
                                        <td class="px-4 py-2.5 text-teal-950">Recommended Min Retail Price</td>
                                        <td id="preview-min-retail-price" class="px-4 py-2.5 text-right text-sm font-black text-teal-800">
                                            ₹{{ number_format(round(100 * (1 + ($sellingPriceDefaultPercentage / 100)), 2), 2) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Informational Note --}}
                    <div class="rounded-2xl border border-blue-200/80 bg-blue-50/60 p-4 sm:p-5 flex items-start gap-3 text-xs leading-5 text-blue-900">
                        <svg class="h-5 w-5 shrink-0 text-blue-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                        </svg>
                        <div>
                            <p class="font-bold text-blue-950">Pricing Notice</p>
                            <p class="mt-0.5 text-blue-800 font-medium">
                                This percentage is applied to the Supply Price to calculate the recommended Min Retail Price for Shop Owners. It does not change Green Leaf supply prices, shop invoices, or finalized bills.
                            </p>
                        </div>
                    </div>

                    {{-- Save Button Bar --}}
                    <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 p-4 rounded-xl">
                        <p class="text-xs font-semibold text-slate-500">Applies to the Shop Owner product catalog and daily prices view.</p>
                        <button type="submit" id="save-selling-price-btn" class="inline-flex h-11 items-center justify-center rounded-xl bg-teal-600 px-5 text-sm font-black text-white shadow-sm transition hover:bg-teal-500">
                            Save Selling Price Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sellingPriceInput = document.getElementById('selling_price_default_percentage');
            const previewMarkupPercent = document.getElementById('preview-markup-percent');
            const previewMinRetailPrice = document.getElementById('preview-min-retail-price');

            function updateSellingPricePreview() {
                if (!sellingPriceInput || !previewMarkupPercent || !previewMinRetailPrice) {
                    return;
                }

                const rawVal = parseFloat(sellingPriceInput.value);
                const percent = (!isNaN(rawVal) && rawVal >= 0) ? rawVal : 0;

                previewMarkupPercent.innerText = percent + '%';

                const exampleSupplyPrice = 100.00;
                const minRetail = exampleSupplyPrice * (1 + (percent / 100));
                const roundedMinRetail = Math.round(minRetail * 100) / 100;

                previewMinRetailPrice.innerText = '₹' + roundedMinRetail.toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            if (sellingPriceInput) {
                sellingPriceInput.addEventListener('input', updateSellingPricePreview);
                updateSellingPricePreview();
            }

            const sellingPriceForm = document.getElementById('selling-price-form');
            if (sellingPriceForm) {
                sellingPriceForm.addEventListener('submit', function () {
                    const btn = document.getElementById('save-selling-price-btn');
                    if (btn) {
                        btn.disabled = true;
                        btn.classList.add('opacity-75', 'cursor-not-allowed');
                        btn.innerText = 'Saving...';
                    }
                });
            }
        });
    </script>
</x-layouts.admin>

