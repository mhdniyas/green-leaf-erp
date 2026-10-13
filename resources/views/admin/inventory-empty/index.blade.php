<x-layouts.admin title="Empty Warehouse">
    <div class="mx-auto max-w-3xl space-y-6">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-900">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-900">
                {{ session('error') }}
            </div>
        @endif

        <div class="rounded-3xl border border-rose-200 bg-rose-50 p-6 shadow-sm">
            <p class="text-xs font-black uppercase tracking-[.18em] text-rose-700">Danger zone</p>
            <h1 class="mt-2 text-2xl font-black text-rose-950">Empty Warehouse</h1>
            <p class="mt-2 text-sm font-semibold text-rose-900">This will set every current inventory stock to zero. Products, orders, purchases, deliveries and history are not deleted.</p>
            @if($recordCount > 0)
                <p class="mt-4 text-sm font-black text-rose-950">{{ number_format($recordCount) }} products with remaining stock will be cleared.</p>
            @else
                <p class="mt-4 text-sm font-black text-emerald-800 flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                    All products are already at zero stock. No products need to be cleared.
                </p>
            @endif
        </div>

        @if($activeProcess)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm font-bold text-amber-900 flex items-center justify-between gap-4">
                <span>An empty-inventory process is currently active. Its progress is shown below.</span>
                <form method="POST" action="{{ route('admin.inventory-empty.cancel', $activeProcess->id) }}" onsubmit="return confirm('Are you sure you want to cancel the empty warehouse process?');" class="inline">
                    @csrf
                    <button type="submit" class="rounded-xl bg-amber-200 hover:bg-amber-300 text-amber-950 px-3.5 py-1.5 text-xs font-black transition cursor-pointer">
                        Cancel Process
                    </button>
                </form>
            </div>
        @elseif($recordCount === 0)
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm text-center py-6">
                <p class="text-sm font-bold text-slate-600">All warehouse inventory is already zero. There is nothing to clear.</p>
            </div>
        @else
            <form method="POST" action="{{ route('admin.inventory-empty.store') }}" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                @csrf
                <label class="block text-sm font-black text-slate-900">Type <span class="font-mono">EMPTY WAREHOUSE</span> to confirm</label>
                <input name="confirmation" required autocomplete="off" class="mt-2 h-11 w-full rounded-xl border border-slate-200 px-3 font-bold focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/20">
                <button class="mt-5 rounded-xl bg-rose-700 px-5 py-3 text-sm font-black text-white hover:bg-rose-800 transition cursor-pointer">Empty Inventory ({{ number_format($recordCount) }} products with stock)</button>
            </form>
        @endif

        @php
            $targetProcess = $activeProcess ?? ($processId = request('process') ? \App\Models\InventoryEmptyProcess::find(request('process')) : null);
        @endphp

        @if($targetProcess)
            <div id="progress" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <p class="text-xs font-black uppercase tracking-[.18em] text-slate-500">Emptying Warehouse Inventory</p>
                    <div id="action-buttons" class="flex items-center gap-2">
                        @if(in_array($targetProcess->status, ['pending', 'running'], true))
                            <form method="POST" action="{{ route('admin.inventory-empty.cancel', $targetProcess->id) }}" onsubmit="return confirm('Are you sure you want to cancel this process?');" class="inline">
                                @csrf
                                <button type="submit" id="cancel-btn" class="rounded-xl bg-rose-50 border border-rose-200 hover:bg-rose-100 text-rose-800 px-3 py-1.5 text-xs font-bold transition cursor-pointer">
                                    Cancel Process
                                </button>
                            </form>
                        @elseif(in_array($targetProcess->status, ['completed_with_errors', 'failed', 'cancelled'], true))
                            <form method="POST" action="{{ route('admin.inventory-empty.retry', $targetProcess->id) }}" class="inline">
                                @csrf
                                <button type="submit" class="rounded-xl bg-slate-900 hover:bg-slate-800 text-white px-3 py-1.5 text-xs font-bold transition cursor-pointer">
                                    Retry / Resume
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <p id="status" class="mt-2 text-xl font-black text-slate-950 capitalize">{{ $targetProcess->status }}</p>
                <p id="counts" class="mt-3 text-sm font-bold text-slate-600">Processed: {{ $targetProcess->processed_records }} / {{ $targetProcess->total_records }} · Successful: {{ $targetProcess->successful_records }} · Failed: {{ $targetProcess->failed_records }}</p>
                <p id="current" class="mt-1 text-sm font-semibold text-slate-500">{{ $targetProcess->currentProduct ? 'Current product: ' . $targetProcess->currentProduct->name : '' }}</p>

                @php
                    $initPercent = $targetProcess->total_records ? (int) round($targetProcess->processed_records / $targetProcess->total_records * 100) : 0;
                @endphp
                <div class="mt-5 h-3 overflow-hidden rounded-full bg-slate-100">
                    <div id="bar" class="h-full bg-rose-600 transition-all duration-300" style="width: {{ $initPercent }}%"></div>
                </div>
                <p id="percent" class="mt-2 text-right text-sm font-black text-rose-700">{{ $initPercent }}%</p>
            </div>

            <script>
                const progressUrl = @json(route('admin.inventory-empty.progress', ['process' => $targetProcess->id]));
                const stepUrl = @json(route('admin.inventory-empty.step', ['process' => $targetProcess->id]));
                const csrfToken = @json(csrf_token());

                let isProcessing = false;

                const stepBatch = async () => {
                    if (isProcessing) return;
                    isProcessing = true;
                    try {
                        const res = await fetch(stepUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            }
                        });
                        if (!res.ok) {
                            // Fallback to progress get
                            await pollProgress();
                            return;
                        }
                        const d = await res.json();
                        updateUI(d);
                        if (d.is_active) {
                            setTimeout(stepBatch, 1000);
                        } else {
                            setTimeout(() => window.location.reload(), 1500);
                        }
                    } catch (e) {
                        setTimeout(pollProgress, 2000);
                    } finally {
                        isProcessing = false;
                    }
                };

                const pollProgress = async () => {
                    try {
                        const res = await fetch(progressUrl, { headers: { 'Accept': 'application/json' } });
                        if (!res.ok) return;
                        const d = await res.json();
                        updateUI(d);
                        if (d.is_active) {
                            setTimeout(stepBatch, 1500);
                        }
                    } catch (e) {}
                };

                const updateUI = (d) => {
                    const statusEl = document.querySelector('#status');
                    if (statusEl) statusEl.textContent = d.status.charAt(0).toUpperCase() + d.status.slice(1);

                    const countsEl = document.querySelector('#counts');
                    if (countsEl) countsEl.textContent = `Processed: ${d.processed} / ${d.total} · Successful: ${d.successful} · Failed: ${d.failed}`;

                    const currentEl = document.querySelector('#current');
                    if (currentEl) currentEl.textContent = d.current_product ? `Current product: ${d.current_product}` : '';

                    const barEl = document.querySelector('#bar');
                    if (barEl) barEl.style.width = d.percent + '%';

                    const percentEl = document.querySelector('#percent');
                    if (percentEl) percentEl.textContent = d.percent + '%';
                };

                @if(in_array($targetProcess->status, ['pending', 'running'], true))
                    setTimeout(stepBatch, 800);
                @endif
            </script>
        @endif
    </div>
</x-layouts.admin>
