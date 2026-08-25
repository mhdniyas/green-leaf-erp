@php($activePurchaseTab = 'reports')
@include('admin.cashbook.finance.purchase._nav')
@include('admin.cashbook.finance.purchase.reports._tabs')
<header class="space-y-2">
    <nav class="flex flex-wrap items-center gap-1.5 text-[10px] font-black uppercase text-slate-500" aria-label="Breadcrumb">
        <a href="{{ route('admin.cashbook.finance.purchase') }}" class="hover:text-emerald-700">Purchase</a>
        <i data-lucide="chevron-right" class="h-3 w-3"></i>
        <a href="{{ route('admin.cashbook.finance.purchase.reports') }}" class="hover:text-emerald-700">Reports</a>
        @isset($reportName)<i data-lucide="chevron-right" class="h-3 w-3"></i><span class="text-slate-900">{{ $reportName }}</span>@endisset
    </nav>
    <div>
        <p class="text-[10px] font-black uppercase text-emerald-700">{{ isset($reportName) ? 'Purchase Reports' : 'Purchase Reports' }}</p>
        <h1 class="mt-1 text-2xl font-black text-slate-950">{{ $reportName ?? 'Purchase Reports' }}</h1>
        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $reportDescription ?? 'Detailed procurement and price analysis.' }}</p>
    </div>
</header>
