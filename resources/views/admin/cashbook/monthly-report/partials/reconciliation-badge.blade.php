@php
    $reconciliation = $reconciliation ?? ['is_reconciled' => true, 'status' => 'Reconciled', 'status_class' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'warnings' => []];
    $readiness = $readiness ?? ['status' => 'Ready', 'status_class' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'is_blocked' => false, 'blocked_reasons' => [], 'warning_reasons' => []];
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200/80 bg-slate-50/50 p-4 text-xs font-medium text-slate-600">
    <!-- RECONCILIATION BADGE & WARNINGS -->
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2">
            <span class="font-bold text-slate-500">Report Status:</span>
            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-black shadow-2xs {{ $reconciliation['status_class'] }}">
                <span class="h-2 w-2 rounded-full {{ $reconciliation['is_reconciled'] ? 'bg-emerald-500' : 'bg-amber-500 animate-ping' }}"></span>
                {{ $reconciliation['status'] }}
            </span>
        </div>

        <div class="flex items-center gap-2">
            <span class="font-bold text-slate-500">Settings Readiness:</span>
            <a href="{{ route('admin.cashbook.settings.final-report.index') }}"
               class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-black shadow-2xs transition hover:opacity-90 {{ $readiness['status_class'] }}">
                {{ $readiness['status'] }}
                <i data-lucide="arrow-up-right" class="h-3 w-3 opacity-60"></i>
            </a>
        </div>
    </div>

    <!-- TIMESTAMP & RECONCILIATION DETAILS -->
    <div class="flex items-center gap-4 text-[11px] text-slate-400">
        <span>Generated: <strong class="font-mono text-slate-600">{{ now()->setTimezone('Asia/Kolkata')->format('d M Y, h:i A') }}</strong></span>
        <span>Currency: <strong class="text-slate-600">INR (₹)</strong></span>
    </div>

    @if(!empty($reconciliation['warnings']))
        <div class="w-full rounded-xl border border-amber-200 bg-amber-50/80 p-3 text-xs text-amber-900">
            <div class="flex items-center gap-1.5 font-bold">
                <i data-lucide="alert-triangle" class="h-4 w-4 text-amber-600"></i>
                Reconciliation Warnings (Review Required):
            </div>
            <ul class="mt-1.5 list-inside list-disc space-y-0.5 pl-1 text-[11px] font-medium text-amber-800">
                @foreach($reconciliation['warnings'] as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
