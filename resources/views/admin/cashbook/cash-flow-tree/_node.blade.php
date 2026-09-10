@php
    $level = $level ?? 0;
    $paddingLeft = $level === 0 ? 'pl-0' : ($level === 1 ? 'pl-6' : ($level === 2 ? 'pl-12' : 'pl-16'));
    $hasChildren = $node->hasChildren();
    $isReview = $node->metadata['is_review'] ?? false;
@endphp

<div x-data="{ expanded: {{ $level <= 1 ? 'true' : 'false' }} }" class="tree-node border-l-2 {{ $level === 0 ? 'border-transparent' : ($isReview ? 'border-amber-400' : 'border-slate-200 hover:border-brand-400') }} transition-colors my-1">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between py-2 px-3 rounded-lg hover:bg-slate-100/80 transition-all gap-2 {{ $paddingLeft }} {{ $isReview ? 'bg-amber-50/50' : '' }}">
        
        <!-- Left: Toggle Icon & Titles -->
        <div class="flex items-center gap-2.5 min-w-0">
            @if($hasChildren)
                <button 
                    type="button" 
                    @click="expanded = !expanded" 
                    class="w-6 h-6 flex items-center justify-center rounded-md bg-white border border-slate-200 text-slate-500 hover:text-brand-600 hover:border-brand-300 shadow-sm transition-transform duration-200"
                    :class="{ 'rotate-90': expanded }"
                    title="Toggle details"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            @else
                <span class="w-6 h-6 flex items-center justify-center text-slate-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                </span>
            @endif

            <div class="truncate">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-sm font-semibold text-slate-900 font-sans tracking-tight">{{ $node->title }}</span>
                    
                    @if($node->badge)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium {{ $isReview ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-700 border border-slate-200' }}">
                            {{ $node->badge }}
                        </span>
                    @endif

                    @if($node->movementsCount() > 0)
                        <button 
                            type="button"
                            @click="fetchDrilldown('{{ $node->id }}', '{{ addslashes($node->title) }}')"
                            class="inline-flex items-center gap-1 text-[11px] text-brand-600 hover:text-brand-800 font-medium underline decoration-brand-300 underline-offset-2 hover:bg-brand-50 px-1.5 py-0.5 rounded transition"
                            title="View individual transactions"
                        >
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            {{ $node->movementsCount() }} moves
                        </button>
                    @endif
                </div>

                @if($node->subtitle)
                    <p class="text-xs text-slate-500 font-sans truncate mt-0.5">{{ $node->subtitle }}</p>
                @endif
            </div>
        </div>

        <!-- Right: Financial Figures (only show with non-zero values) -->
        @php
            $hasValues = abs((float) $node->openingBalance) > 0.001 
                || (float) $node->totalIn > 0.001 
                || (float) $node->totalOut > 0.001 
                || abs((float) $node->closingBalance) > 0.001;
        @endphp

        @if($hasValues)
            <div class="flex items-center gap-3 sm:gap-4 shrink-0 text-xs font-mono self-end sm:self-auto pl-8 sm:pl-0 flex-wrap">
                <!-- Opening -->
                @if(abs((float) $node->openingBalance) > 0.001)
                    <div class="text-right">
                        <span class="block text-[10px] uppercase font-sans text-slate-400 font-medium">Opening</span>
                        <span class="text-slate-600 font-medium">₹{{ number_format($node->openingBalance, 2) }}</span>
                    </div>
                @endif

                <!-- Inflow -->
                @if((float) $node->totalIn > 0.001)
                    <div class="text-right">
                        <span class="block text-[10px] uppercase font-sans text-slate-400 font-medium">In (+)</span>
                        <span class="text-emerald-600 font-semibold">
                            ₹{{ number_format($node->totalIn, 2) }}
                        </span>
                    </div>
                @endif

                <!-- Outflow -->
                @if((float) $node->totalOut > 0.001)
                    <div class="text-right">
                        <span class="block text-[10px] uppercase font-sans text-slate-400 font-medium">Out (-)</span>
                        <span class="text-rose-600 font-semibold">
                            ₹{{ number_format($node->totalOut, 2) }}
                        </span>
                    </div>
                @endif

                <!-- Closing -->
                @if(abs((float) $node->closingBalance) > 0.001)
                    <div class="text-right bg-slate-50 px-2 py-1 rounded border border-slate-200/60">
                        <span class="block text-[10px] uppercase font-sans text-slate-500 font-semibold">Closing</span>
                        <span class="font-bold {{ $node->closingBalance < 0 ? 'text-rose-700' : 'text-slate-900' }}">
                            ₹{{ number_format($node->closingBalance, 2) }}
                        </span>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- Recursive Children -->
    @if($hasChildren)
        <div x-show="expanded" x-collapse.duration.200ms class="tree-children mt-0.5">
            @foreach($node->children as $child)
                @include('admin.cashbook.cash-flow-tree._node', ['node' => $child, 'level' => $level + 1])
            @endforeach
        </div>
    @endif
</div>
