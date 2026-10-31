@props([
    'shop' => null,
    'editPrefix' => 'create-shop',
    'showEditForm' => false,
])

@php
    $prefix = $editPrefix;
    if ($shop) {
        $initialAllowed = (array) old('allowed_product_grades', $shop->allowedProductGrades());
        $initialDefault = (string) old('default_product_grade', $shop->defaultProductGrade());
        $initialMultiGrade = (bool) old('enable_multi_grade_ordering', $shop->isMultiGradeOrderingEnabled());
    } else {
        $initialAllowed = (array) old('allowed_product_grades', ['A']);
        $initialDefault = (string) old('default_product_grade', 'A');
        $initialMultiGrade = (bool) old('enable_multi_grade_ordering', false);
    }

    if (! in_array($initialDefault, $initialAllowed, true)) {
        $initialDefault = $initialAllowed[0] ?? 'A';
    }
@endphp

<div x-data="shopGradeConfig({
    initialAllowed: {{ json_encode(array_values($initialAllowed)) }},
    initialDefault: '{{ $initialDefault }}',
    initialMultiGrade: {{ $initialMultiGrade ? 'true' : 'false' }}
})" class="rounded-xl border border-slate-200/90 bg-slate-50/70 p-3.5 space-y-2.5 transition">
    
    <!-- Card Header -->
    <div class="flex items-center justify-between gap-2">
        <span class="flex items-center gap-1.5 text-xs font-bold text-slate-800 uppercase tracking-wide">
            <svg class="h-4 w-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
            Product Ordering Grades
        </span>
        <button type="button"
                @click="openModal()"
                id="{{ $prefix }}-edit-grades-btn"
                class="inline-flex items-center gap-1.5 rounded-lg border border-purple-200 bg-white px-2.5 py-1 text-xs font-semibold text-purple-700 shadow-2xs hover:bg-purple-50 hover:border-purple-300 transition active:scale-95 cursor-pointer">
            <svg class="h-3.5 w-3.5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
            </svg>
            Edit Configuration
        </button>
    </div>

    <!-- Summary Details -->
    <div class="space-y-1.5 pt-0.5">
        <!-- Allowed Grades Badges -->
        <div class="flex items-center gap-2 text-xs">
            <span class="text-slate-500 font-medium">Allowed:</span>
            <div class="flex flex-wrap items-center gap-1.5">
                <template x-for="g in appliedAllowed" :key="g">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold border"
                          :class="{
                              'bg-emerald-50 text-emerald-700 border-emerald-200': g === 'A',
                              'bg-blue-50 text-blue-700 border-blue-200': g === 'B',
                              'bg-amber-50 text-amber-700 border-amber-200': g === 'C',
                          }"
                          x-text="'Grade ' + g"></span>
                </template>
                <!-- SSR Fallback -->
                <div x-init="$el.remove()" class="flex flex-wrap items-center gap-1.5">
                    @foreach($initialAllowed as $grade)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold border @if($grade === 'A') bg-emerald-50 text-emerald-700 border-emerald-200 @elseif($grade === 'B') bg-blue-50 text-blue-700 border-blue-200 @else bg-amber-50 text-amber-700 border-amber-200 @endif">
                            Grade {{ $grade }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Default Grade -->
        <div class="flex items-center gap-1.5 text-xs">
            <span class="text-slate-500 font-medium">Default:</span>
            <span class="font-bold text-slate-900" x-text="'Grade ' + appliedDefault">Grade {{ $initialDefault }}</span>
        </div>

        <!-- Multi-Grade Ordering Status -->
        <div class="flex items-center gap-1.5 text-xs">
            <span class="text-slate-500 font-medium">Multi-Grade:</span>
            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-semibold"
                  :class="appliedMultiGrade ? 'bg-purple-100 text-purple-800 border border-purple-200' : 'bg-slate-200 text-slate-700'"
                  x-text="appliedMultiGrade ? 'Enabled (ON)' : 'Disabled (OFF)'">
                {{ $initialMultiGrade ? 'Enabled (ON)' : 'Disabled (OFF)' }}
            </span>
        </div>
    </div>

    @if($showEditForm)
        @error('allowed_product_grades')
            <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
        @enderror
        @error('default_product_grade')
            <p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>
        @enderror
    @endif

    <!-- Parent Form Hidden / SR-Only Inputs -->
    <div id="{{ $prefix }}-form-inputs">
        <input type="checkbox" name="allowed_product_grades[]" value="A" x-model="appliedAllowed" :checked="appliedAllowed.includes('A')" @checked(in_array('A', $initialAllowed, true)) class="sr-only" tabindex="-1" aria-hidden="true">
        <input type="checkbox" name="allowed_product_grades[]" value="B" x-model="appliedAllowed" :checked="appliedAllowed.includes('B')" @checked(in_array('B', $initialAllowed, true)) class="sr-only" tabindex="-1" aria-hidden="true">
        <input type="checkbox" name="allowed_product_grades[]" value="C" x-model="appliedAllowed" :checked="appliedAllowed.includes('C')" @checked(in_array('C', $initialAllowed, true)) class="sr-only" tabindex="-1" aria-hidden="true">
        <input type="hidden" name="default_product_grade" :value="appliedDefault" value="{{ $initialDefault }}">
        <input type="hidden" name="enable_multi_grade_ordering" :value="appliedMultiGrade ? '1' : '0'" value="{{ $initialMultiGrade ? '1' : '0' }}">
    </div>

    <!-- Responsive Edit Configuration Modal -->
    <div x-show="modalOpen"
         x-cloak
         id="{{ $prefix }}-grade-modal"
         class="fixed inset-0 z-50 overflow-y-auto"
         aria-labelledby="modal-title-{{ $prefix }}"
         role="dialog"
         aria-modal="true"
         @keydown.escape.window="closeModal()">
        
        <!-- Backdrop -->
        <div x-show="modalOpen"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity"
             @click="closeModal()"></div>

        <!-- Modal Center Dialog -->
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-6">
            <div x-show="modalOpen"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-5 text-left shadow-2xl transition-all border border-slate-100 sm:p-6"
                 @click.stop>
                
                <!-- Modal Header -->
                <div class="flex items-start justify-between pb-3.5 border-b border-slate-100">
                    <div>
                        <h3 id="modal-title-{{ $prefix }}" class="text-base font-bold text-slate-900">Shop Grade Configuration</h3>
                        <p class="mt-1 text-xs text-slate-500">Choose which product grades this shop can order and set its default grade.</p>
                    </div>
                    <button type="button"
                            @click="closeModal()"
                            class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition cursor-pointer"
                            title="Close">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="mt-4 space-y-4">
                    <!-- Allowed Grades Selection -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Allowed Grades <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-3 gap-2.5">
                            <template x-for="g in ['A', 'B', 'C']" :key="g">
                                <label :class="{
                                    'border-purple-500 bg-purple-50/70 text-purple-900 font-bold shadow-2xs ring-1 ring-purple-400': draftAllowed.includes(g),
                                    'border-slate-200 bg-slate-50/50 text-slate-600 hover:bg-slate-100 hover:border-slate-300': !draftAllowed.includes(g)
                                }" class="flex flex-col items-center justify-center p-3 rounded-xl border cursor-pointer transition select-none text-center">
                                    <input type="checkbox"
                                           :value="g"
                                           :checked="draftAllowed.includes(g)"
                                           @change="toggleGrade(g)"
                                           class="sr-only">
                                    <span class="text-xs font-black tracking-wide" x-text="'Grade ' + g"></span>
                                    <span class="mt-1 text-[10px]" :class="draftAllowed.includes(g) ? 'text-purple-600 font-semibold' : 'text-slate-400'"
                                          x-text="draftAllowed.includes(g) ? '✓ Allowed' : 'Disabled'"></span>
                                </label>
                            </template>
                        </div>
                        <p x-show="errorMessage" x-text="errorMessage" class="mt-1.5 text-xs text-rose-600 font-medium"></p>
                    </div>

                    <!-- Default Grade Dropdown -->
                    <div>
                        <label for="{{ $prefix }}-modal-default-grade" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Default Grade <span class="text-red-500">*</span></label>
                        <select id="{{ $prefix }}-modal-default-grade"
                                x-model="draftDefault"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 font-medium focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-100">
                            <template x-for="g in draftAllowed" :key="g">
                                <option :value="g" x-text="'Grade ' + g"></option>
                            </template>
                            @foreach($initialAllowed as $g)
                                <option value="{{ $g }}" x-init="$el.remove()" @selected($initialDefault === $g)>Grade {{ $g }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[11px] text-slate-500">The primary ordering grade presented when this shop places requisitions.</p>
                    </div>

                    <!-- Enable Multi-Grade Ordering Toggle -->
                    <div class="pt-3 border-t border-slate-100">
                        <label class="flex items-center justify-between cursor-pointer group">
                            <div class="pr-3">
                                <span class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Enable Multi-Grade Ordering</span>
                                <p class="mt-0.5 text-[11px] text-slate-500">Allow purchaser to order additional eligible grades (A/B/C) on the shop marketplace.</p>
                            </div>
                            <div class="relative inline-flex items-center">
                                <input type="checkbox"
                                       id="{{ $prefix }}-modal-multi-grade-toggle"
                                       x-model="draftMultiGrade"
                                       class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="mt-6 flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100">
                    <button type="button"
                            @click="closeModal()"
                            id="{{ $prefix }}-modal-cancel-btn"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition active:scale-95 cursor-pointer">
                        Cancel
                    </button>
                    <button type="button"
                            @click="applyChanges()"
                            id="{{ $prefix }}-modal-apply-btn"
                            :disabled="draftAllowed.length === 0"
                            class="rounded-xl bg-purple-600 px-4 py-2 text-xs font-semibold text-white hover:bg-purple-700 transition active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed shadow-xs cursor-pointer">
                        Apply Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
if (typeof window.shopGradeConfig === 'undefined') {
    window.shopGradeConfig = function(config) {
        return {
            modalOpen: false,
            appliedAllowed: Array.isArray(config.initialAllowed) ? [...config.initialAllowed] : ['A'],
            appliedDefault: config.initialDefault || 'A',
            appliedMultiGrade: Boolean(config.initialMultiGrade),
            draftAllowed: Array.isArray(config.initialAllowed) ? [...config.initialAllowed] : ['A'],
            draftDefault: config.initialDefault || 'A',
            draftMultiGrade: Boolean(config.initialMultiGrade),
            errorMessage: '',

            openModal() {
                this.draftAllowed = [...this.appliedAllowed];
                this.draftDefault = this.appliedDefault;
                this.draftMultiGrade = this.appliedMultiGrade;
                this.errorMessage = '';
                this.modalOpen = true;
            },

            closeModal() {
                this.modalOpen = false;
                this.errorMessage = '';
            },

            toggleGrade(grade) {
                const idx = this.draftAllowed.indexOf(grade);
                if (idx > -1) {
                    if (this.draftAllowed.length === 1) {
                        this.errorMessage = 'At least one grade must be selected.';
                        return;
                    }
                    this.draftAllowed.splice(idx, 1);
                    this.errorMessage = '';
                } else {
                    this.draftAllowed.push(grade);
                    this.draftAllowed.sort();
                    this.errorMessage = '';
                }

                // If only one grade is selected, automatically choose it as default
                if (this.draftAllowed.length === 1) {
                    this.draftDefault = this.draftAllowed[0];
                } else if (!this.draftAllowed.includes(this.draftDefault)) {
                    this.draftDefault = this.draftAllowed[0] || 'A';
                }
            },

            applyChanges() {
                if (this.draftAllowed.length === 0) {
                    this.errorMessage = 'At least one grade must be selected.';
                    return;
                }
                if (!this.draftAllowed.includes(this.draftDefault)) {
                    this.draftDefault = this.draftAllowed[0] || 'A';
                }

                this.appliedAllowed = [...this.draftAllowed];
                this.appliedDefault = this.draftDefault;
                this.appliedMultiGrade = this.draftMultiGrade;
                this.modalOpen = false;
            }
        };
    };
}
</script>
