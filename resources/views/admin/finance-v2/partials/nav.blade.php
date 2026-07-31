@php
    $navDate = $date->format('Y-m-d');
    $items = [
        ['label' => 'Dashboard', 'href' => route('admin.finance-v2.dashboard', ['date' => $navDate]), 'active' => request()->routeIs('admin.finance-v2.dashboard')],
        ['label' => 'Green Leaf', 'href' => route('admin.finance-v2.green-leaf.section', ['section' => 'purchase', 'date' => $navDate]), 'active' => request()->routeIs('admin.finance-v2.green-leaf.*')],
        ['label' => 'Clients', 'href' => route('admin.finance-v2.clients.index', ['date' => $navDate]), 'active' => request()->routeIs('admin.finance-v2.clients.*') || request()->routeIs('admin.finance-v2.shops.*')],
        ['label' => 'Payments', 'href' => route('admin.finance-v2.payments.index', ['date' => $navDate]), 'active' => request()->routeIs('admin.finance-v2.payments.*') || request()->routeIs('admin.finance-v2.direct-payments.*') || request()->routeIs('admin.finance-v2.client-payments.*') || request()->routeIs('admin.finance-v2.company-payables.*')],
        ['label' => 'Reports', 'href' => route('admin.finance-v2.reports', ['date' => $navDate]), 'active' => request()->routeIs('admin.finance-v2.reports')],
    ];
@endphp

<div class="flex flex-col gap-3 rounded-[1.5rem] border border-slate-200 bg-white p-3 shadow-sm lg:flex-row lg:items-center lg:justify-between print:hidden">
    <nav class="flex flex-wrap gap-2">
        @foreach($items as $item)
            <a href="{{ $item['href'] }}" class="inline-flex h-10 items-center rounded-[1rem] px-4 text-xs font-black uppercase tracking-[0.16em] transition {{ $item['active'] ? 'bg-slate-950 text-white' : 'border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ url()->current() }}" class="flex items-center gap-2">
        <label class="rounded-[1rem] border border-slate-200 bg-slate-50 px-3 py-2">
            <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Period</span>
            <input type="date" name="date" value="{{ $navDate }}" onchange="this.form.submit()" class="mt-1 w-40 border-0 bg-transparent p-0 text-sm font-black text-slate-950 focus:outline-none focus:ring-0">
        </label>
    </form>
</div>
