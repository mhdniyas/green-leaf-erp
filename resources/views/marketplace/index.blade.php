<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Shop Green Leaf Traders daily fresh produce marketplace with live approved fruit and vegetable prices.">
        <title>Marketplace | Green Leaf Traders</title>
        @fonts
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-surface-50 text-slate-900 antialiased">
        <div class="bg-brand-800 px-4 py-2 text-center text-xs font-semibold text-white sm:text-sm">Daily prices update from approved market rates. Stock and rates can change with availability.</div>

        <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3" aria-label="Green Leaf Traders home">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-brand-700 text-sm font-black text-white">GL</span>
                    <span><span class="block text-base font-black leading-tight text-slate-950">Green Leaf Traders</span><span class="block text-xs font-medium text-slate-500">Fresh produce marketplace</span></span>
                </a>
                <nav id="primary-nav" class="absolute left-4 right-4 top-[76px] hidden flex-col gap-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-xl md:static md:flex md:flex-row md:items-center md:gap-6 md:border-0 md:bg-transparent md:p-0 md:shadow-none" aria-label="Primary navigation">
                    <a href="{{ route('home') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:text-brand-700 md:px-0 md:py-1">Home</a>
                    <a href="{{ route('marketplace.index') }}" class="rounded-lg px-3 py-2 text-sm font-bold text-brand-700 md:px-0 md:py-1">Marketplace</a>
                    <a href="#cart" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:text-brand-700 md:px-0 md:py-1">Cart</a>
                    <a href="#contact" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:text-brand-700 md:px-0 md:py-1">Enquiry</a>
                </nav>
                <div class="flex items-center gap-3">
                    <a href="{{ route('login') }}" class="hidden text-sm font-semibold text-slate-600 hover:text-brand-700 sm:inline">Login</a>
                    <a href="#cart" class="inline-flex rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-brand-800">Cart <span id="cart-count" class="ml-1">0</span></a>
                    <button id="menu-toggle" type="button" class="inline-flex size-10 items-center justify-center rounded-lg border border-slate-200 text-slate-700 md:hidden" aria-label="Toggle navigation" aria-expanded="false"><span class="text-lg">≡</span></button>
                </div>
            </div>
        </header>

        <main>
            <section class="relative overflow-hidden bg-slate-950 text-white">
                <img src="{{ asset('images/header.png') }}" alt="Fresh fruits and vegetables" class="absolute inset-0 h-full w-full object-cover opacity-45">
                <div class="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-950/90 to-brand-950/50"></div>
                <div class="relative mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:items-end lg:px-8">
                    <div>
                        <p class="inline-flex rounded-full border border-brand-300/40 bg-brand-500/15 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-brand-200">Public daily marketplace</p>
                        <h1 class="mt-5 text-4xl font-black tracking-tight sm:text-6xl">Fresh produce prices, ready to browse.</h1>
                        <p class="mt-5 max-w-2xl text-base font-semibold leading-7 text-slate-200">Filter by category, search products, sort by price, and add items to an enquiry cart. This is a public buyer-friendly marketplace, not an admin screen.</p>
                    </div>
                    <div class="rounded-3xl border border-white/15 bg-white/10 p-6 shadow-2xl backdrop-blur">
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-brand-200">Price board date</p>
                        <p class="mt-3 text-3xl font-black">{{ $marketPriceDate ? \Illuminate\Support\Carbon::parse($marketPriceDate)->format('d M Y') : 'Pending' }}</p>
                        <p class="mt-2 text-sm font-semibold text-slate-300">{{ $marketProducts->count() }} listed products</p>
                    </div>
                </div>
            </section>

            <section class="border-b border-slate-200 bg-white py-5">
                <form id="marketplace-filters" class="mx-auto grid max-w-7xl gap-3 px-4 sm:px-6 md:grid-cols-[1fr_220px_180px_auto] lg:px-8">
                    <label class="grid gap-1 text-xs font-black uppercase tracking-wider text-slate-500">Search
                        <input id="market-search" name="q" type="search" value="{{ $filters['q'] }}" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm normal-case tracking-normal outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100" placeholder="Search apple, tomato, grapes...">
                    </label>
                    <label class="grid gap-1 text-xs font-black uppercase tracking-wider text-slate-500">Category
                        <select id="market-category" name="category" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm normal-case tracking-normal outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                            <option value="">All categories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected($filters['category'] === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-xs font-black uppercase tracking-wider text-slate-500">Sort
                        <select id="market-sort" name="sort" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm normal-case tracking-normal outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100">
                            <option value="featured" @selected($filters['sort'] === 'featured')>Featured</option>
                            <option value="price_low" @selected($filters['sort'] === 'price_low')>Price: low to high</option>
                            <option value="price_high" @selected($filters['sort'] === 'price_high')>Price: high to low</option>
                            <option value="name" @selected($filters['sort'] === 'name')>Name A-Z</option>
                        </select>
                    </label>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="w-full rounded-xl bg-brand-700 px-5 py-3 text-sm font-black text-white hover:bg-brand-800">Apply</button>
                        <button type="button" id="reset-market-filters" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700 hover:border-brand-300">Reset</button>
                    </div>
                </form>
            </section>

            <section class="bg-brand-50 py-12">
                <div class="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[1fr_360px] lg:px-8">
                    <div>
                        <div class="mb-6 flex flex-wrap gap-2">
                            <button type="button" class="market-category-chip rounded-full {{ $filters['category'] === null ? 'bg-brand-700 text-white' : 'border border-brand-200 bg-white text-brand-800' }} px-4 py-2 text-xs font-black" data-category="">All</button>
                            @foreach ($categories as $category)
                                <button type="button" class="market-category-chip rounded-full {{ $filters['category'] === $category->id ? 'bg-brand-700 text-white' : 'border border-brand-200 bg-white text-brand-800 hover:border-brand-500' }} px-4 py-2 text-xs font-black" data-category="{{ $category->id }}">{{ $category->name }}</button>
                            @endforeach
                        </div>

                        @if ($marketProducts->isNotEmpty())
                            <div id="market-grid" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($marketProducts as $product)
                                    <article class="market-card group overflow-hidden rounded-3xl border border-brand-100 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl" data-category="{{ $product['category_id'] }}" data-name="{{ strtolower($product['name'].' '.$product['category']) }}" data-price="{{ $product['price'] }}">
                                        <div class="relative h-52 overflow-hidden bg-amber-50">
                                            <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                                            <span class="absolute left-3 top-3 rounded-full bg-white/95 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-brand-700 shadow">{{ $product['category'] }}</span>
                                        </div>
                                        <div class="p-5">
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="min-w-0">
                                                    <h2 class="truncate text-lg font-black text-slate-950">{{ $product['name'] }}</h2>
                                                    <p class="mt-1 text-xs font-bold text-slate-500">Fresh daily stock</p>
                                                </div>
                                                <div class="shrink-0 rounded-2xl bg-emerald-700 px-3 py-2 text-right text-white">
                                                    <p class="text-[10px] font-black uppercase leading-none">Per {{ $product['unit'] }}</p>
                                                    <p class="mt-1 text-3xl font-black leading-none">₹{{ rtrim(rtrim(number_format((float) $product['price'], 2), '0'), '.') }}</p>
                                                </div>
                                            </div>
                                            <div class="mt-5 grid grid-cols-[1fr_auto] gap-2">
                                                <input type="number" min="1" step="1" value="1" class="cart-qty rounded-xl border border-slate-200 px-3 py-3 text-sm font-bold outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100" aria-label="Quantity for {{ $product['name'] }}">
                                                <button type="button" class="add-to-cart rounded-xl bg-brand-700 px-4 py-3 text-sm font-black text-white hover:bg-brand-800" data-id="{{ $product['id'] }}" data-name="{{ $product['name'] }}" data-unit="{{ $product['unit'] }}" data-price="{{ $product['price'] }}">Add</button>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                            <div id="empty-market-products" class="mt-6 hidden rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center">
                                <p class="text-xl font-black text-slate-950">No marketplace products found.</p>
                                <p class="mt-3 text-sm font-semibold text-slate-600">Try another category/search, or send an enquiry for current availability.</p>
                            </div>
                        @else
                            <div id="empty-market-products" class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center">
                                <p class="text-xl font-black text-slate-950">No marketplace products found.</p>
                                <p class="mt-3 text-sm font-semibold text-slate-600">Try another category/search, or send an enquiry for current availability.</p>
                            </div>
                        @endif
                    </div>

                    <aside id="cart" class="h-fit rounded-3xl border border-slate-200 bg-white p-5 shadow-xl lg:sticky lg:top-28">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[0.18em] text-brand-700">Enquiry cart</p>
                                <h2 class="mt-2 text-2xl font-black text-slate-950">Selected items</h2>
                            </div>
                            <button type="button" id="clear-cart" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-black text-slate-600 hover:border-rose-200 hover:text-rose-700">Clear</button>
                        </div>
                        <div id="cart-items" class="mt-5 grid gap-3"></div>
                        <div class="mt-5 rounded-2xl bg-brand-50 p-4">
                            <div class="flex items-center justify-between text-sm font-black text-slate-950"><span>Estimated total</span><span id="cart-total">₹0</span></div>
                            <p class="mt-2 text-xs font-semibold leading-5 text-slate-500">Final billing depends on confirmed stock, weight, packing, and delivery.</p>
                        </div>
                        <a href="#contact" id="cart-enquiry-link" class="mt-5 inline-flex w-full justify-center rounded-xl bg-brand-700 px-5 py-3 text-sm font-black text-white hover:bg-brand-800">Send enquiry</a>
                    </aside>
                </div>
            </section>

            <section id="contact" class="bg-white py-20">
                <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-brand-700">Marketplace enquiry</p>
                        <h2 class="mt-4 text-3xl font-black text-slate-950 sm:text-4xl">Send the cart or your own list.</h2>
                        <p class="mt-5 text-base leading-7 text-slate-600">Use the cart to prepare a quick enquiry. We will confirm availability, exact quantity, and final price before billing.</p>
                    </div>
                    <form action="{{ route('website-enquiries.store') }}" method="POST" class="rounded-2xl border border-slate-200 bg-surface-50 p-6">
                        @csrf
                        <input type="hidden" name="source_page" value="marketplace">
                        @if (session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">{{ session('success') }}</div>@endif
                        @if ($errors->any())<div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>@endif
                        <div class="{{ session('success') || $errors->any() ? 'mt-4 ' : '' }}grid gap-4 sm:grid-cols-2">
                            <label class="grid gap-2 text-sm font-bold">Name<input name="name" required type="text" value="{{ old('name') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100" placeholder="Your name"></label>
                            <label class="grid gap-2 text-sm font-bold">Phone / WhatsApp<input name="phone" required type="tel" value="{{ old('phone') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100" placeholder="Phone number"></label>
                            <label class="grid gap-2 text-sm font-bold">Customer type<select name="customer_type" class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100"><option value="Wholesale buyer" @selected(old('customer_type', 'Wholesale buyer') === 'Wholesale buyer')>Wholesale buyer</option><option value="Retail customer" @selected(old('customer_type') === 'Retail customer')>Retail customer</option><option value="Restaurant or hotel" @selected(old('customer_type') === 'Restaurant or hotel')>Restaurant or hotel</option><option value="Shop or supermarket" @selected(old('customer_type') === 'Shop or supermarket')>Shop or supermarket</option></select></label>
                            <label class="grid gap-2 text-sm font-bold">Required date<input name="required_date" type="date" value="{{ old('required_date') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100"></label>
                        </div>
                        <label class="mt-4 grid gap-2 text-sm font-bold">Required products and quantity<textarea id="marketplace-message" name="message" required rows="6" class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100" placeholder="Add items to cart or type your product list">{{ old('message') }}</textarea></label>
                        <button type="submit" class="mt-5 w-full rounded-lg bg-brand-700 px-5 py-3.5 text-sm font-black text-white hover:bg-brand-800">Send enquiry</button>
                    </form>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 bg-white"><div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-8 text-sm text-slate-500 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8"><p>© {{ date('Y') }} Green Leaf Traders. Daily fresh marketplace.</p><div class="flex gap-5"><a href="{{ route('home') }}" class="font-semibold hover:text-brand-700">Home</a><a href="{{ route('products.index') }}" class="font-semibold hover:text-brand-700">Products</a><a href="{{ route('login') }}" class="font-semibold hover:text-brand-700">Login</a></div></div></footer>

        <script>
            (() => {
                const storageKey = 'greenleaf-marketplace-cart';
                const filterForm = document.getElementById('marketplace-filters');
                const searchInput = document.getElementById('market-search');
                const categorySelect = document.getElementById('market-category');
                const sortSelect = document.getElementById('market-sort');
                const resetFilters = document.getElementById('reset-market-filters');
                const marketGrid = document.getElementById('market-grid');
                const marketCards = Array.from(document.querySelectorAll('.market-card'));
                const emptyMarketProducts = document.getElementById('empty-market-products');
                const categoryChips = Array.from(document.querySelectorAll('.market-category-chip'));
                const cartItems = document.getElementById('cart-items');
                const cartCount = document.getElementById('cart-count');
                const cartTotal = document.getElementById('cart-total');
                const message = document.getElementById('marketplace-message');
                const formatter = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 });
                let cart = JSON.parse(localStorage.getItem(storageKey) || '[]');

                const activeChipClasses = ['bg-brand-700', 'text-white'];
                const inactiveChipClasses = ['border', 'border-brand-200', 'bg-white', 'text-brand-800'];
                const setActiveCategoryChip = (category) => {
                    categoryChips.forEach((chip) => {
                        const isActive = chip.dataset.category === category;
                        chip.classList.remove(...activeChipClasses, ...inactiveChipClasses);
                        chip.classList.add(...(isActive ? activeChipClasses : inactiveChipClasses));
                    });
                };
                const applyMarketFilters = () => {
                    if (!marketGrid) return;
                    const search = (searchInput?.value || '').trim().toLowerCase();
                    const category = categorySelect?.value || '';
                    const sort = sortSelect?.value || 'featured';
                    const visibleCards = marketCards.filter((card) => {
                        const matchesCategory = category === '' || card.dataset.category === category;
                        const matchesSearch = search === '' || (card.dataset.name || '').includes(search);
                        const shouldShow = matchesCategory && matchesSearch;
                        card.classList.toggle('hidden', !shouldShow);
                        return shouldShow;
                    });

                    visibleCards
                        .sort((first, second) => {
                            const firstPrice = Number.parseFloat(first.dataset.price || '0');
                            const secondPrice = Number.parseFloat(second.dataset.price || '0');
                            const firstName = first.dataset.name || '';
                            const secondName = second.dataset.name || '';

                            if (sort === 'price_low') return firstPrice - secondPrice;
                            if (sort === 'price_high') return secondPrice - firstPrice;
                            if (sort === 'name') return firstName.localeCompare(secondName);
                            return 0;
                        })
                        .forEach((card) => marketGrid.appendChild(card));

                    emptyMarketProducts?.classList.toggle('hidden', visibleCards.length > 0);
                    setActiveCategoryChip(category);
                };

                filterForm?.addEventListener('submit', (event) => {
                    event.preventDefault();
                    applyMarketFilters();
                });
                searchInput?.addEventListener('input', applyMarketFilters);
                sortSelect?.addEventListener('change', applyMarketFilters);
                categorySelect?.addEventListener('change', applyMarketFilters);
                categoryChips.forEach((chip) => {
                    chip.addEventListener('click', () => {
                        if (categorySelect) categorySelect.value = chip.dataset.category || '';
                        applyMarketFilters();
                    });
                });
                resetFilters?.addEventListener('click', () => {
                    if (searchInput) searchInput.value = '';
                    if (categorySelect) categorySelect.value = '';
                    if (sortSelect) sortSelect.value = 'featured';
                    applyMarketFilters();
                });

                const save = () => localStorage.setItem(storageKey, JSON.stringify(cart));
                const syncMessage = () => {
                    if (!message || cart.length === 0) return;
                    message.value = cart.map((item) => `${item.name} - ${item.qty} ${item.unit} @ ₹${formatter.format(item.price)}`).join('\n');
                };
                const render = () => {
                    const totalQty = cart.reduce((sum, item) => sum + item.qty, 0);
                    const total = cart.reduce((sum, item) => sum + (item.qty * item.price), 0);
                    cartCount.textContent = totalQty;
                    cartTotal.textContent = `₹${formatter.format(total)}`;
                    cartItems.innerHTML = cart.length
                        ? cart.map((item) => `<div class="rounded-2xl border border-slate-200 p-3"><div class="flex items-start justify-between gap-3"><div><p class="text-sm font-black text-slate-950">${item.name}</p><p class="mt-1 text-xs font-semibold text-slate-500">${item.qty} ${item.unit} × ₹${formatter.format(item.price)}</p></div><button type="button" class="remove-cart text-xs font-black text-rose-600" data-id="${item.id}">Remove</button></div></div>`).join('')
                        : '<p class="rounded-2xl border border-dashed border-slate-300 p-5 text-center text-sm font-semibold text-slate-500">Cart is empty. Add products to prepare an enquiry.</p>';
                    syncMessage();
                };

                document.querySelectorAll('.add-to-cart').forEach((button) => {
                    button.addEventListener('click', () => {
                        const qtyInput = button.closest('article').querySelector('.cart-qty');
                        const qty = Math.max(1, Number.parseFloat(qtyInput.value || '1'));
                        const id = button.dataset.id;
                        const existing = cart.find((item) => item.id === id);
                        if (existing) {
                            existing.qty += qty;
                        } else {
                            cart.push({ id, name: button.dataset.name, unit: button.dataset.unit, price: Number.parseFloat(button.dataset.price), qty });
                        }
                        save();
                        render();
                    });
                });

                cartItems.addEventListener('click', (event) => {
                    const button = event.target.closest('.remove-cart');
                    if (!button) return;
                    cart = cart.filter((item) => item.id !== button.dataset.id);
                    save();
                    render();
                });

                document.getElementById('clear-cart').addEventListener('click', () => {
                    cart = [];
                    save();
                    render();
                    if (message) message.value = '';
                });

                applyMarketFilters();
                render();
            })();
        </script>
    </body>
</html>
