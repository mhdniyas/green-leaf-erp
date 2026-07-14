@php
    $cutoffService = app(\App\Services\Purchasing\PurchaserBusinessDayService::class);
    $cutoffLabel = $cutoffService->cutoffLabel();
    $cutoffInputValue = $cutoffService->cutoffInputValue();
@endphp

<button
    type="button"
    onclick="openCutoffSettingsModal()"
    class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50"
>
    Cutoff: {{ $cutoffLabel }}
</button>

<div id="cutoff-settings-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/45 px-4">
    <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Business Day</p>
                <h3 class="mt-1 text-lg font-black text-slate-950">Change cutoff time</h3>
                <p class="mt-2 text-sm text-slate-600">After this time, the default board date rolls to the next business day.</p>
            </div>
            <button type="button" onclick="closeCutoffSettingsModal()" class="rounded-2xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form method="POST" action="{{ route('business-day-settings.cutoff.update') }}" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="redirect_date" value="{{ $date ?? request('date') }}">

            <div>
                <label for="cutoff-time-input" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Cutoff time</label>
                <input
                    id="cutoff-time-input"
                    type="time"
                    name="cutoff_time"
                    value="{{ old('cutoff_time', $cutoffInputValue) }}"
                    required
                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-800 focus:border-sky-500 focus:outline-none"
                >
                <p class="mt-2 text-xs text-slate-500">Current live cutoff: {{ $cutoffLabel }}</p>
            </div>

            <div class="flex items-center justify-end gap-2">
                <button type="button" onclick="closeCutoffSettingsModal()" class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit" class="rounded-2xl bg-sky-600 px-4 py-2.5 text-xs font-black text-white transition hover:bg-sky-700">
                    Save Cutoff
                </button>
            </div>
        </form>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function openCutoffSettingsModal() {
                const modal = document.getElementById('cutoff-settings-modal');
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
            }

            function closeCutoffSettingsModal() {
                const modal = document.getElementById('cutoff-settings-modal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            }
        </script>
    @endpush
@endonce
