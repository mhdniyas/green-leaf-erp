<x-layouts.app title="Manage Custom Lists">
    <div class="max-w-6xl mx-auto px-4 py-8 animate-fade-in">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <a href="{{ route('dashboard') }}#requisition" class="text-xs font-bold text-emerald-600 hover:text-emerald-700 flex items-center gap-1.5 transition-colors mb-2">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    Back to Requisition Sheet
                </a>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                    Requisition Presets <span class="text-xs font-black bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full uppercase tracking-wider">Custom Lists</span>
                </h1>
                <p class="text-xs text-slate-500 mt-1">Create and manage recurring item templates to quickly prefill your tomorrow requisition sheets.</p>
            </div>
            
            <a href="{{ route('requisitions.presets.create') }}" class="inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-4 py-2.5 rounded-xl shadow-md transition-all cursor-pointer focus:outline-none">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                Create Preset List
            </a>
        </div>

        @if(session('success'))
            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 flex gap-3 animate-fade-in">
                <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <div class="text-xs font-bold text-emerald-800">{{ session('success') }}</div>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- 1. Default Favorites Card (Always Visible) -->
            <div class="bg-white border-2 border-dashed border-amber-200 rounded-3xl shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden flex flex-col justify-between relative">
                <div class="absolute top-0 right-0 bg-amber-500 text-white font-black text-[9px] uppercase px-3 py-1.5 rounded-bl-2xl tracking-wider">
                    System Default
                </div>
                <div class="p-6">
                    <div class="flex items-start justify-between gap-4 mb-4">
                        <div>
                            <h3 class="text-base font-extrabold text-slate-900 tracking-tight flex items-center gap-1.5">
                                <svg class="w-4.5 h-4.5 text-amber-500 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" /></svg>
                                Default Favorites
                            </h3>
                            <p class="text-[10px] text-slate-400 font-mono mt-0.5">System Staples Requisition Template</p>
                        </div>
                        <span class="shrink-0 text-xs font-black bg-amber-50 text-amber-800 border border-amber-100 px-2.5 py-1 rounded-xl mr-24">
                            {{ $favoriteProducts->count() }} {{ Str::plural('Item', $favoriteProducts->count()) }}
                        </span>
                    </div>

                    <!-- Preset Items List Preview -->
                    <div class="bg-slate-50 rounded-2xl border border-slate-100 p-4 max-h-48 overflow-y-auto divide-y divide-slate-100">
                        @forelse($favoriteProducts as $prod)
                            <div class="py-2 first:pt-0 last:pb-0 flex items-center justify-between gap-4 text-xs">
                                <div class="flex flex-col">
                                    <span class="font-bold text-slate-800">{{ $prod->name }}</span>
                                    <span class="text-[9px] text-slate-400 font-mono">{{ $prod->sku }}</span>
                                </div>
                                <span class="font-black text-slate-900 shrink-0 bg-white border border-slate-200/80 px-2.5 py-0.5 rounded-lg">
                                    Default <span class="text-[9px] text-slate-400 uppercase font-black">{{ $prod->unit }}</span>
                                </span>
                            </div>
                        @empty
                            <div class="text-center py-4 text-xs text-slate-400 italic">No products in staples.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Card Footer actions -->
                <div class="px-6 py-4 bg-amber-50/20 border-t border-slate-100 flex items-center justify-between gap-3 shrink-0">
                    <span class="text-[10px] font-bold text-amber-700 italic">Read-only template</span>
                    <a href="{{ route('requisitions.presets.create', ['copy_favorites' => 1]) }}" class="bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold px-4 py-2 rounded-xl transition-all cursor-pointer focus:outline-none flex items-center gap-1.5 shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                        Customize & Save As Preset
                    </a>
                </div>
            </div>

            <!-- Custom Presets Cards -->
            @foreach($presets as $preset)
                <div class="bg-white border border-slate-200 rounded-3xl shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden flex flex-col justify-between">
                    <div class="p-6">
                        <!-- Title & Actions -->
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div>
                                <h3 class="text-base font-extrabold text-slate-900 tracking-tight">{{ $preset->name }}</h3>
                                <p class="text-[10px] text-slate-400 font-mono mt-0.5">Created by: {{ $preset->creator->name }} · {{ $preset->created_at->format('d M Y') }}</p>
                            </div>
                            <span class="shrink-0 text-xs font-black bg-emerald-50 text-emerald-700 border border-emerald-100 px-2.5 py-1 rounded-xl">
                                {{ $preset->items->count() }} {{ Str::plural('Item', $preset->items->count()) }}
                            </span>
                        </div>

                        <!-- Preset Items List Preview -->
                        <div class="bg-slate-50 rounded-2xl border border-slate-100 p-4 max-h-48 overflow-y-auto divide-y divide-slate-100">
                            @forelse($preset->items as $item)
                                <div class="py-2 first:pt-0 last:pb-0 flex items-center justify-between gap-4 text-xs">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-slate-800">{{ $item->product->name }}</span>
                                        <span class="text-[9px] text-slate-400 font-mono">{{ $item->product->sku }}</span>
                                    </div>
                                    <span class="font-black text-slate-900 shrink-0 bg-white border border-slate-200/80 px-2.5 py-0.5 rounded-lg">
                                        {{ floatval($item->quantity) }} <span class="text-[9px] text-slate-400 uppercase font-black">{{ $item->product->unit }}</span>
                                    </span>
                                </div>
                            @empty
                                <div class="text-center py-4 text-xs text-slate-400 italic">No products added.</div>
                            @endforelse
                        </div>
                    </div>

                    <!-- Card Footer actions -->
                    <div class="px-6 py-4 bg-slate-50/50 border-t border-slate-100 flex items-center justify-end gap-3 shrink-0">
                        <form action="{{ route('requisitions.presets.destroy', $preset->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this preset?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-bold text-red-600 hover:text-red-700 hover:underline cursor-pointer focus:outline-none bg-transparent border-0 py-1.5 px-3">
                                Delete
                            </button>
                        </form>
                        <a href="{{ route('requisitions.presets.edit', $preset->id) }}" class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-xs font-bold px-4 py-1.5 rounded-xl transition-all cursor-pointer focus:outline-none">
                            Edit List
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.app>
