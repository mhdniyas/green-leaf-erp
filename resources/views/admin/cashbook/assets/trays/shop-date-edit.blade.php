@extends('admin.cashbook.layouts.app')

@section('title', 'Edit ' . $shop->name . ' Trays (' . $date . ')')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.cashbook.assets.trays.index') }}" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
        </a>
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ $shop->name }} — Tray Movements</h1>
            <p class="text-sm text-slate-500 font-medium">Date: <span class="font-bold text-slate-800">{{ $date }}</span></p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
        <form action="{{ route('admin.cashbook.assets.trays.shop-date.update', ['shop' => $shop->id, 'date' => $date]) }}" method="POST" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                @foreach($rows as $index => $row)
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/60 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <input type="hidden" name="trays[{{ $index }}][tray_type_id]" value="{{ $row['tray_type_id'] }}">
                    
                    <div class="sm:w-1/3">
                        <p class="font-black text-slate-900 text-sm">{{ $row['tray_name'] }}</p>
                        <p class="text-xs text-slate-400 font-medium">Net Held: <span class="font-bold text-blue-600" id="net-display-{{ $index }}">{{ $row['net'] }}</span></p>
                    </div>

                    <div class="flex items-center gap-3">
                        <div>
                            <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-500 mb-1">Sent Qty</label>
                            <input type="number" min="0" name="trays[{{ $index }}][sent_qty]" id="sent-{{ $index }}" value="{{ $row['sent'] }}" oninput="recalculateNet({{ $index }})" class="w-24 px-3 py-1.5 rounded-lg border border-slate-200 text-sm font-bold text-center focus:ring-2 focus:ring-slate-900 focus:outline-none bg-white">
                        </div>

                        <div>
                            <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-500 mb-1">Returned Qty</label>
                            <input type="number" min="0" name="trays[{{ $index }}][returned_qty]" id="returned-{{ $index }}" value="{{ $row['returned'] }}" oninput="recalculateNet({{ $index }})" class="w-24 px-3 py-1.5 rounded-lg border border-slate-200 text-sm font-bold text-center focus:ring-2 focus:ring-slate-900 focus:outline-none bg-white">
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.cashbook.assets.trays.index') }}" class="px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold rounded-xl shadow-sm transition">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function recalculateNet(index) {
    const sent = parseInt(document.getElementById('sent-' + index).value) || 0;
    const returned = parseInt(document.getElementById('returned-' + index).value) || 0;
    const net = sent - returned;
    document.getElementById('net-display-' + index).innerText = net;
}
</script>
@endsection
