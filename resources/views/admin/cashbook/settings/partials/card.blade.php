@php
    $code = strtolower((string) ($setting->entryType?->code ?? ''));
    $category = strtolower((string) ($setting->entryType?->category ?? ''));
    $isTransferOrSettlement = $category === 'transfer' || $category === 'settlement' || (! $setting->include_in_sales && ! $setting->include_in_income && ! $setting->include_in_expense);
    $isDigital = in_array($code, $digitalEntryCodes ?? [], true) || $setting->company_account_id !== null;
    $isEnabled = (bool) $setting->enabled;

    if ($isTransferOrSettlement) {
        $src = $setting->default_funding_source ?? 'none';
        $settle = $setting->settlement_behavior ?? 'none';
        $petty = $setting->petty_behavior ?? 'none';
        $pending = $setting->company_pending_behavior ?? 'none';

        if ($src === 'sales' && $petty === 'increase') {
            $movementLabel = 'Shop Balance → Petty';
        } elseif ($src === 'company' && $petty === 'increase') {
            $movementLabel = 'Company → Petty';
        } elseif ($src === 'sales' && $settle === 'decrease') {
            $movementLabel = 'Shop Balance → Company';
        } elseif ($src === 'company' && $pending === 'decrease') {
            $movementLabel = 'Company → Shop';
        } elseif ($src === 'company' && $pending === 'none' && $petty === 'none' && $settle === 'none') {
            $movementLabel = 'Company → Vendor';
        } elseif ($src === 'bank' && $petty === 'increase') {
            $movementLabel = 'Bank → Petty';
        } elseif ($src === 'petty' && $petty === 'decrease') {
            $movementLabel = 'Petty → Company';
        } elseif ($src === 'sales') {
            $movementLabel = 'Shop Balance → Settlement';
        } elseif ($src === 'company') {
            $movementLabel = 'Company → Settlement';
        } else {
            $movementLabel = 'Transfer / Settlement';
        }
    }
@endphp
<div onclick="openConfigModal({{ $setting->id }})"
     data-setting-card="1"
     data-setting-id="{{ $setting->id }}"
     data-header-id="{{ $setting->header_group_id ?: 'unassigned' }}"
     data-enabled="{{ $isEnabled ? '1' : '0' }}"
     draggable="true"
     ondragstart="handleCardDragStart(event)"
     class="setting-card group relative cursor-pointer rounded-2xl border p-4 shadow-xs transition flex flex-col justify-between min-h-[145px] {{ $isEnabled ? 'bg-white border-slate-200 hover:border-slate-300 hover:shadow-md' : 'bg-slate-50/70 border-slate-200 hidden' }}">
    <div>
        <div class="flex items-start justify-between gap-2">
            <div class="flex items-center gap-1.5 min-w-0">
                <span class="card-drag-handle cursor-grab active:cursor-grabbing text-slate-300 hover:text-slate-600 shrink-0" title="Drag card">
                    <i data-lucide="grip-vertical" class="h-4 w-4"></i>
                </span>
                <h3 class="font-extrabold text-sm leading-snug transition truncate {{ $isEnabled ? 'text-slate-950 group-hover:text-indigo-700' : 'text-slate-500' }}">{{ $setting->displayName() }}</h3>
            </div>
            @if($isEnabled)
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-extrabold text-emerald-700 shrink-0 border border-emerald-200">
                    <i data-lucide="circle-check" class="h-3 w-3 text-emerald-600"></i> Active
                </span>
            @else
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-200/60 px-2 py-0.5 text-[10px] font-bold text-slate-500 shrink-0 border border-slate-300">
                    <i data-lucide="circle-off" class="h-3 w-3 text-slate-400"></i> Disabled
                </span>
            @endif
        </div>
        <div class="mt-1 font-mono text-[10px] font-bold text-slate-400 uppercase tracking-wider pl-5">
            {{ $setting->entryType->category }}
        </div>

        <div class="mt-3 text-xs font-bold pl-5">
            @if($isTransferOrSettlement)
                <div class="flex items-center gap-1.5 {{ $isEnabled ? 'text-indigo-700' : 'text-slate-400' }}">
                    <i data-lucide="arrow-right-left" class="h-3.5 w-3.5 shrink-0"></i>
                    <span class="truncate">{{ $movementLabel }}</span>
                </div>
            @elseif($isDigital && $setting->companyAccount)
                <div class="flex items-center gap-1.5 {{ $isEnabled ? 'text-indigo-700' : 'text-slate-400' }}">
                    <i data-lucide="landmark" class="h-3.5 w-3.5 shrink-0"></i>
                    <span class="truncate">{{ $setting->companyAccount->name }}</span>
                </div>
            @else
                <div class="{{ $isEnabled ? 'text-slate-600' : 'text-slate-400' }} flex items-center gap-1.5 truncate">
                    <i data-lucide="banknote" class="h-3.5 w-3.5 shrink-0"></i>
                    <span>{{ $fundingSourceBusinessLabels[$setting->default_funding_source] ?? 'Shop Cash' }}</span>
                </div>
            @endif
        </div>
    </div>

    <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-2 text-xs font-extrabold transition {{ $isEnabled ? 'text-slate-500 group-hover:text-slate-950' : 'text-slate-400' }}">
        <span class="inline-flex items-center gap-1"><i data-lucide="sliders-horizontal" class="h-3.5 w-3.5"></i> Configure</span>
        <i data-lucide="arrow-right" class="h-3.5 w-3.5 group-hover:translate-x-0.5 transition-transform"></i>
    </div>
</div>

