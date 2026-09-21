@props([
    'modalId',
    'title',
    'subtitle' => 'Payments Configuration',
    'shopName' => '',
    'period' => '',
    'maxWidth' => 'max-w-3xl',
    'saveFunction' => null,
    'saveBtnId' => null,
    'saveBtnText' => 'Save Changes',
])

<div id="payment-modal-{{ $modalId }}"
     data-payment-modal="{{ $modalId }}"
     class="payment-modal-backdrop fixed inset-0 z-50 hidden items-center justify-center p-3 sm:p-6 bg-slate-950/60 backdrop-blur-xs transition-opacity duration-200 opacity-0"
     aria-hidden="true"
     role="dialog">
    <div class="payment-modal-panel relative w-full {{ $maxWidth }} max-h-[92vh] flex flex-col rounded-3xl bg-white shadow-2xl border border-slate-200 overflow-hidden transform transition-all duration-200 scale-95 opacity-0">
        {{-- Modal Header (Sticky) --}}
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-slate-50/70 shrink-0">
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-violet-700 block">{{ $subtitle }}</span>
                <h3 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">{{ $title }}</h3>
                <p class="text-xs text-slate-400 font-medium">{{ $shopName }} @if(!empty($period)) • {{ $period }} @endif</p>
            </div>
            <button type="button"
                    data-payment-modal-close
                    class="rounded-xl p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 transition cursor-pointer"
                    aria-label="Close modal">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        {{-- Modal Body (Scrollable) --}}
        <div class="p-6 overflow-y-auto space-y-5 flex-1 scrollbar-thin">
            {{ $slot }}
        </div>

        {{-- Modal Footer (Sticky) --}}
        <div class="flex items-center justify-end gap-3 border-t border-slate-100 px-6 py-4 bg-slate-50/70 shrink-0">
            <button type="button"
                    data-payment-modal-close
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100 transition cursor-pointer shadow-2xs">
                Cancel
            </button>
            @if($saveFunction)
                <button type="button"
                        onclick="{{ $saveFunction }}"
                        @if($saveBtnId) id="{{ $saveBtnId }}" @endif
                        class="inline-flex items-center gap-2 rounded-xl bg-violet-700 px-5 py-2.5 text-xs font-black text-white hover:bg-violet-800 transition cursor-pointer shadow-sm active:scale-98">
                    <i data-lucide="check" class="h-4 w-4"></i>
                    <span>{{ $saveBtnText }}</span>
                </button>
            @endif
        </div>
    </div>
</div>
