{{-- 3D Financial Pyramid Architecture Explorer (Stepped Terraces, Apex Summit, Zero Emojis) --}}
@vite(['resources/js/cash-flow-3d.js'])

<div 
    id="money-explorer-3d-wrapper"
    class="relative w-full h-[720px] lg:h-[840px] bg-slate-50/95 rounded-2xl border border-slate-200 shadow-xl overflow-hidden select-none"
    x-data="{
        breadcrumbs: [{ id: 'root', label: 'Green Leaf (Apex)' }],
        canGoBack: false,
        goBack() {
            if (window.greenLeafExplorer) {
                window.greenLeafExplorer.goBack();
            }
        },
        resetToApex() {
            if (window.greenLeafExplorer) {
                window.greenLeafExplorer.resetToApex();
            }
        },
        navigateCrumb(id) {
            if (id === 'root') {
                this.resetToApex();
            } else if (window.greenLeafExplorer) {
                window.greenLeafExplorer.exploreNode(id);
            }
        }
    }"
    x-init="
        $nextTick(() => {
            const viewport = $el.querySelector('#three-explorer-canvas');
            const tryMount = () => {
                if (window.CashFlow3DExplorer) {
                    if (window.greenLeafExplorer) {
                        window.greenLeafExplorer.destroy();
                    }
                    window.greenLeafExplorer = new window.CashFlow3DExplorer(viewport, {
                        nodes: nodesRegistry || [],
                        edges: rawEdges || [],
                        onOpenTransactions: (node) => {
                            fetchDrilldown(node.id, node.label);
                        },
                        onOpenEdgeDetails: (edge) => {
                            fetchEdgeDrilldown(edge);
                        },
                        onBreadcrumbChange: (crumbs) => {
                            breadcrumbs = crumbs;
                            canGoBack = crumbs.length > 1;
                        },
                        onFallback: (msg) => {
                            console.warn(msg);
                            viewMode = 'tree';
                        }
                    });
                } else {
                    setTimeout(tryMount, 35);
                }
            };
            tryMount();
        });

        $watch('viewMode', (val) => {
            if (val === 'diagram' && window.greenLeafExplorer) {
                setTimeout(() => window.greenLeafExplorer.onResize(), 70);
            }
        });

        $watch('entitySearch', (query) => {
            if (window.greenLeafExplorer) {
                window.greenLeafExplorer.highlightSearch(query);
            }
        });
    "
>
    {{-- 3D WebGL Canvas & CSS2D Interactive Viewport --}}
    <div id="three-explorer-canvas" class="absolute inset-0 w-full h-full cursor-grab active:cursor-grabbing"></div>

    {{-- Top Floating Navigation Bar: Back Button, Pyramid Breadcrumb Chain, and Apex Shortcut --}}
    <div class="absolute top-4 left-4 z-20 flex items-center gap-2 flex-wrap">
        {{-- Back Button --}}
        <button 
            type="button" 
            @click="goBack()" 
            :disabled="!canGoBack"
            class="px-3 py-1.5 rounded-xl text-xs font-bold font-sans transition flex items-center gap-1.5 shadow-sm border"
            :class="canGoBack ? 'bg-slate-900 hover:bg-slate-800 text-white border-slate-900 cursor-pointer' : 'bg-white text-slate-400 border-slate-200 cursor-not-allowed opacity-50'"
            title="Ascend to upper pyramid level"
        >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>Back</span>
        </button>

        {{-- Pyramid Breadcrumbs --}}
        <div class="flex items-center gap-1 bg-white/95 backdrop-blur-md px-3 py-1.5 rounded-xl border border-slate-200 shadow-sm text-xs font-sans">
            <span class="text-slate-500 font-bold flex items-center gap-1 mr-1">
                <span>Pyramid:</span>
            </span>
            <template x-for="(crumb, idx) in breadcrumbs" :key="crumb.id">
                <div class="flex items-center gap-1">
                    <span x-show="idx > 0" class="text-slate-300">/</span>
                    <button 
                        type="button" 
                        @click="navigateCrumb(crumb.id)"
                        class="px-2 py-0.5 rounded-lg transition font-mono text-[11px]"
                        :class="idx === breadcrumbs.length - 1 ? 'font-black text-slate-900 bg-slate-100' : 'text-slate-500 hover:text-slate-800 hover:bg-slate-50'"
                        x-text="crumb.label"
                    ></button>
                </div>
            </template>
        </div>

        {{-- Apex Summit Shortcut --}}
        <button 
            type="button" 
            @click="resetToApex()" 
            class="px-3 py-1.5 rounded-xl bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 border border-slate-200 shadow-sm text-xs font-bold font-sans transition flex items-center gap-1.5"
            title="Return to Pyramid Summit (Green Leaf Apex)"
        >
            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
            </svg>
            <span>Apex Summit</span>
        </button>
    </div>

    {{-- Top-Right Controls: Camera Zoom & Recenter --}}
    <div class="absolute top-4 right-4 z-20 flex items-center gap-1.5 bg-white/95 backdrop-blur-md p-1.5 rounded-xl border border-slate-200 shadow-sm text-slate-700 text-xs">
        <button 
            type="button" 
            onclick="window.greenLeafExplorer && window.greenLeafExplorer.zoomIn()" 
            class="p-2 rounded-lg hover:bg-slate-100 hover:text-slate-900 transition" 
            title="Zoom In"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
            </svg>
        </button>
        <button 
            type="button" 
            onclick="window.greenLeafExplorer && window.greenLeafExplorer.zoomOut()" 
            class="p-2 rounded-lg hover:bg-slate-100 hover:text-slate-900 transition" 
            title="Zoom Out"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4"/>
            </svg>
        </button>
        <div class="h-4 w-px bg-slate-200 mx-0.5"></div>
        <button 
            type="button" 
            onclick="window.greenLeafExplorer && window.greenLeafExplorer.resetCamera()" 
            class="px-2.5 py-1.5 rounded-lg hover:bg-slate-100 hover:text-slate-900 font-sans text-xs font-semibold transition flex items-center gap-1.5" 
            title="Recenter Camera on Focused Pyramid Node"
        >
            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
            <span>Center</span>
        </button>
    </div>

    {{-- Bottom Floating Legend: Pyramid Tiers --}}
    <div class="absolute bottom-4 left-4 z-20 hidden md:flex items-center gap-3.5 bg-white/95 backdrop-blur-md px-3.5 py-2 rounded-xl border border-slate-200 shadow-sm text-[11px] font-sans text-slate-600">
        <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-emerald-600 border border-emerald-700"></span>
            <span class="font-bold text-slate-800">Summit (Apex)</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-sky-500 border border-sky-600"></span>
            <span>Tier 1 (Treasury)</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-green-500 border border-green-600"></span>
            <span>Tier 2 (Channels)</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-orange-500 border border-orange-600"></span>
            <span>Tier 3 (Suppliers)</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-amber-600 border border-amber-700"></span>
            <span class="font-bold text-slate-800">Foundation Holding</span>
        </div>
        <span class="text-slate-300">|</span>
        <span class="text-slate-500">Explore down tiers &bull; Back to ascend pyramid</span>
    </div>
</div>
