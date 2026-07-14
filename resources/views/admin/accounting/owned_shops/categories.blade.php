<x-layouts.accounting :title="$shop->name.' Categories'">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Ledger Categories</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">{{ $shop->name }}</h1>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Create new income and expense categories here and review all available ledger category options for this shop.</p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('admin.accounting.owned-shops.show', ['shop' => $shop->code, 'tab' => 'cashbook']) }}" class="inline-flex h-11 items-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                        Back to Shop
                    </a>
                </div>
            </div>
        </section>

        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Create Category</p>
            <h2 class="mt-2 text-xl font-black text-slate-950">Add new ledger category</h2>

            <form method="POST" action="{{ route('admin.accounting.owned-shops.categories.store', $shop) }}" class="mt-5 grid gap-4 xl:grid-cols-5">
                @csrf
                <label class="block">
                    <span class="mb-2 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Scope</span>
                    <select name="scope" class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                        <option value="global" @selected(old('scope') === 'global')>Global</option>
                        <option value="shop" @selected(old('scope') === 'shop')>{{ $shop->name }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-2 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Type</span>
                    <select name="type" class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                        <option value="income" @selected(old('type') === 'income')>Income</option>
                        <option value="expense" @selected(old('type') === 'expense')>Expense</option>
                    </select>
                </label>
                <label class="block xl:col-span-2">
                    <span class="mb-2 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Name</span>
                    <div class="flex gap-3">
                        <input type="text" name="name" value="{{ old('name') }}" placeholder="Category name" class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                        <button type="submit" class="inline-flex h-11 shrink-0 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800">
                            Create
                        </button>
                    </div>
                </label>
                <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-700">
                    <input type="checkbox" name="cash_effect" value="1" @checked(old('cash_effect', true)) class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <span>Cash movement</span>
                </label>
            </form>
        </section>

        <section class="grid gap-6 xl:grid-cols-3">
            <article class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Global Income</p>
                <div class="mt-4 space-y-2">
                    @forelse($globalCategories->where('type', 'income')->values() as $category)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">
                            {{ $category->name }}
                        </div>
                    @empty
                        <p class="text-sm font-semibold text-slate-500">No global income categories yet.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Global Expense</p>
                <div class="mt-4 space-y-2">
                    @forelse($globalCategories->where('type', 'expense')->values() as $category)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">
                            {{ $category->name }}
                        </div>
                    @empty
                        <p class="text-sm font-semibold text-slate-500">No global expense categories yet.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Shop Categories</p>
                <div class="mt-4 space-y-2">
                    @forelse($shopCategories as $category)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">
                            {{ $category->name }} <span class="text-xs uppercase tracking-[0.14em] text-slate-400">({{ $category->type }})</span>
                        </div>
                    @empty
                        <p class="text-sm font-semibold text-slate-500">No shop-only categories yet.</p>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
</x-layouts.accounting>
