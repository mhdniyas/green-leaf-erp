<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seed Test Shop Data - Green Leaf Traders</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
        }
    </style>
</head>
<body class="h-full text-slate-800 flex flex-col justify-between">
    <div class="py-12 px-4 sm:px-6 lg:px-8 max-w-6xl mx-auto w-full space-y-8">
        <!-- Header -->
        <div class="text-center">
            <span class="text-[10px] font-black uppercase tracking-[0.24em] text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-full border border-emerald-200">Development tools</span>
            <h1 class="mt-4 text-4xl font-black tracking-tight text-slate-950 sm:text-5xl">Seed Test Shop Data</h1>
            <p class="mt-3 text-slate-500 text-sm max-w-lg mx-auto">Seed June month-end carry-over balance and July daily transactions dynamically by entering amounts only for the categories you want to seed.</p>
        </div>

        <!-- Notification Banner -->
        @if (session('success'))
            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold flex items-center gap-3">
                <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-semibold">
                <div class="flex items-center gap-3 mb-2">
                    <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <span>Please resolve the following errors:</span>
                </div>
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Main Card Form -->
        <form method="POST" action="{{ route('seedtest.run') }}" class="space-y-6">
            @csrf

            <!-- Shop & Carry-over Selection -->
            <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm grid gap-6 md:grid-cols-2">
                <div>
                    <label class="block text-xs font-black uppercase tracking-[0.16em] text-slate-400 mb-2">Select Shop</label>
                    <select name="shop_id" id="shop-selector" class="w-full h-12 rounded-xl bg-slate-50 border border-slate-200 px-4 text-sm font-bold text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-0 transition" required>
                        <option value="">-- Choose a Shop --</option>
                        @foreach ($shops as $shop)
                            <option value="{{ $shop->id }}" data-code="{{ $shop->code }}">{{ $shop->name }} ({{ $shop->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-black uppercase tracking-[0.16em] text-slate-400 mb-2">June Month-End Carry-over (Rs.)</label>
                    <input type="number" step="0.01" name="carry_over" id="carry_over" value="0.00" class="w-full h-12 rounded-xl bg-slate-50 border border-slate-200 px-4 text-sm font-bold text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-0 transition" required>
                </div>
            </div>

            <!-- July 3-Day Data Cards Container -->
            <div id="days-container" class="grid gap-6 md:grid-cols-3">
                <div class="rounded-[2rem] border border-slate-200 bg-white p-8 text-center text-slate-400 font-bold md:col-span-3">
                    Please select a shop above to display the seeding inputs.
                </div>
            </div>

            <!-- Submit Button -->
            <div id="submit-section" class="flex justify-center pt-4 hidden">
                <button type="submit" class="inline-flex h-14 items-center justify-center rounded-2xl bg-slate-950 px-8 text-sm font-black uppercase tracking-[0.16em] text-white shadow-md transition hover:bg-slate-800 hover:scale-[1.02] active:scale-[0.98]">
                    Seed Shop Data
                </button>
            </div>
        </form>

        <!-- Shop Categories Reference Card -->
        <section id="categories-reference-card" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm space-y-6 hidden">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Reference</p>
                <h2 class="mt-1 text-xl font-black text-slate-950">Shop Accounting Categories</h2>
                <p class="text-xs text-slate-500 mt-1">Below are the categories that will be created and associated with the daily entries if you fill in their values.</p>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <!-- Income Column -->
                <div class="space-y-4">
                    <h3 class="text-sm font-black uppercase tracking-[0.14em] text-emerald-700 border-b border-slate-100 pb-2">Income Categories</h3>
                    
                    <!-- Global Income -->
                    <div class="space-y-2">
                        <h4 class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Global</h4>
                        <ul class="space-y-1 text-xs font-semibold text-slate-700 list-disc pl-4">
                            @foreach ($globalIncome as $cat)
                                <li>{{ $cat->name }} <span class="text-[10px] text-slate-400">({{ $cat->cash_effect ? 'Cash' : 'Non-Cash' }})</span></li>
                            @endforeach
                        </ul>
                    </div>

                    <!-- Shop Specific Income -->
                    <div id="shop-income-section" class="space-y-2 hidden">
                        <h4 class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Shop-Specific</h4>
                        <ul id="shop-income-list" class="space-y-1 text-xs font-semibold text-slate-700 list-disc pl-4">
                        </ul>
                    </div>
                </div>

                <!-- Expense Column -->
                <div class="space-y-4">
                    <h3 class="text-sm font-black uppercase tracking-[0.14em] text-rose-700 border-b border-slate-100 pb-2">Expense Categories</h3>

                    <!-- Global Expense -->
                    <div class="space-y-2">
                        <h4 class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Global</h4>
                        <ul class="space-y-1 text-xs font-semibold text-slate-700 list-disc pl-4">
                            @foreach ($globalExpense as $cat)
                                <li>{{ $cat->name }} <span class="text-[10px] text-slate-400">({{ $cat->cash_effect ? 'Cash' : 'Non-Cash' }})</span></li>
                            @endforeach
                        </ul>
                    </div>

                    <!-- Shop Specific Expense -->
                    <div id="shop-expense-section" class="space-y-2 hidden">
                        <h4 class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Shop-Specific</h4>
                        <ul id="shop-expense-list" class="space-y-1 text-xs font-semibold text-slate-700 list-disc pl-4">
                        </ul>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer class="border-t border-slate-200 bg-white py-6 text-center text-xs text-slate-500">
        <p>&copy; Green Leaf Traders. Internal testing and diagnostic tool.</p>
    </footer>

    <!-- JS Autofill & Dynamic Categories Logic -->
    <script>
        const defaults = @json($defaults);
        const globalIncome = @json($globalIncome);
        const globalExpense = @json($globalExpense);
        const shopSpecificCategories = @json($shopSpecificCategories);

        const shopSelector = document.getElementById('shop-selector');
        const carryOverInput = document.getElementById('carry_over');
        const daysContainer = document.getElementById('days-container');
        const submitSection = document.getElementById('submit-section');
        const categoriesReferenceCard = document.getElementById('categories-reference-card');

        const shopIncomeSection = document.getElementById('shop-income-section');
        const shopIncomeList = document.getElementById('shop-income-list');
        const shopExpenseSection = document.getElementById('shop-expense-section');
        const shopExpenseList = document.getElementById('shop-expense-list');

        shopSelector.addEventListener('change', function () {
            const selectedOption = shopSelector.options[shopSelector.selectedIndex];
            const shopId = shopSelector.value;
            const shopCode = selectedOption.dataset.code;

            if (!shopId) {
                daysContainer.innerHTML = `
                    <div class="rounded-[2rem] border border-slate-200 bg-white p-8 text-center text-slate-400 font-bold md:col-span-3">
                        Please select a shop above to display the seeding inputs.
                    </div>
                `;
                submitSection.classList.add('hidden');
                categoriesReferenceCard.classList.add('hidden');
                return;
            }

            // Gather all categories for this shop
            const customCats = shopSpecificCategories[shopId] || [];
            const activeCategories = [...globalIncome, ...globalExpense, ...customCats];

            // Render 3 Days of Inputs
            daysContainer.innerHTML = '';
            submitSection.classList.remove('hidden');
            categoriesReferenceCard.classList.remove('hidden');

            const shopConfig = defaults[shopCode] || null;
            carryOverInput.value = shopConfig ? shopConfig.carry_over.toFixed(2) : "0.00";

            for (let i = 0; i < 3; i++) {
                const dayNum = i + 1;
                const defaultDay = (shopConfig && shopConfig.days[i]) ? shopConfig.days[i] : null;
                const dayDate = defaultDay ? defaultDay.date : `2026-07-0${dayNum}`;
                const invoiceTotal = defaultDay ? defaultDay.invoice_total : 0;
                const loanGiven = defaultDay ? defaultDay.loan_given : 0;

                let dayHtml = `
                    <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="text-md font-black text-slate-950 font-bold">July Day ${dayNum}</h3>
                            <input type="date" name="days[${i}][date]" value="${dayDate}" class="bg-transparent border-0 p-0 text-xs font-black text-emerald-700 focus:outline-none focus:ring-0" required readonly>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[9px] font-black uppercase tracking-[0.12em] text-slate-400 mb-1">Invoice/Bill (Rs.)</label>
                                <input type="number" step="0.01" name="days[${i}][invoice_total]" value="${invoiceTotal > 0 ? invoiceTotal.toFixed(2) : ''}" placeholder="None" class="w-full h-9 rounded-lg bg-slate-50 border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:outline-none transition">
                            </div>
                            <div>
                                <label class="block text-[9px] font-black uppercase tracking-[0.12em] text-slate-400 mb-1">Loan Given (Rs.)</label>
                                <input type="number" step="0.01" name="days[${i}][loan_given]" value="${loanGiven > 0 ? loanGiven.toFixed(2) : ''}" placeholder="None" class="w-full h-9 rounded-lg bg-slate-50 border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:outline-none transition">
                            </div>
                        </div>

                        <div class="border-t border-slate-100 pt-3 space-y-3">
                            <h4 class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Category Lines</h4>
                            <div class="max-h-[300px] overflow-y-auto pr-1 space-y-2.5">
                `;

                // Render Input for Each Category
                activeCategories.forEach(cat => {
                    let prefilledVal = '';

                    if (defaultDay) {
                        // Prefill matching category names
                        if (cat.name === 'Sales' && defaultDay.sales > 0) {
                            prefilledVal = defaultDay.sales.toFixed(2);
                        } else if (cat.name === 'Rent' && defaultDay.rent > 0) {
                            prefilledVal = defaultDay.rent.toFixed(2);
                        } else if (cat.name === 'Vehicle' && defaultDay.vehicle > 0) {
                            prefilledVal = defaultDay.vehicle.toFixed(2);
                        } else if (cat.name === 'Cash Purchase' && defaultDay.cash_purchase > 0) {
                            prefilledVal = defaultDay.cash_purchase.toFixed(2);
                        }
                    }

                    const badgeColor = cat.type === 'income' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-rose-50 text-rose-700 border-rose-100';

                    dayHtml += `
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <span class="block text-xs font-bold text-slate-800 truncate" title="${cat.name}">${cat.name}</span>
                                <span class="inline-block text-[8px] font-bold uppercase tracking-wider border rounded px-1.5 py-0.5 mt-0.5 ${badgeColor}">${cat.type}</span>
                            </div>
                            <input type="number" step="0.01" name="days[${i}][categories][${cat.id}]" value="${prefilledVal}" placeholder="None" class="w-24 h-8 rounded-lg bg-slate-50 border border-slate-200 px-2 text-right text-xs font-bold text-slate-900 focus:border-emerald-500 focus:outline-none transition">
                        </div>
                    `;
                });

                dayHtml += `
                            </div>
                        </div>
                    </div>
                `;

                daysContainer.innerHTML += dayHtml;
            }

            // 3. Update Category Reference Display
            shopIncomeList.innerHTML = '';
            shopExpenseList.innerHTML = '';
            shopIncomeSection.classList.add('hidden');
            shopExpenseSection.classList.add('hidden');

            if (customCats.length > 0) {
                let hasIncome = false;
                let hasExpense = false;

                customCats.forEach(cat => {
                    const li = document.createElement('li');
                    li.innerHTML = `${cat.name} <span class="text-[10px] text-slate-400">(${cat.cash_effect ? 'Cash' : 'Non-Cash'})</span>`;
                    
                    if (cat.type === 'income') {
                        shopIncomeList.appendChild(li);
                        hasIncome = true;
                    } else {
                        shopExpenseList.appendChild(li);
                        hasExpense = true;
                    }
                });

                if (hasIncome) shopIncomeSection.classList.remove('hidden');
                if (hasExpense) shopExpenseSection.classList.remove('hidden');
            }
        });
    </script>
</body>
</html>
