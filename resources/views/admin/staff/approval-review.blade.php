<x-layouts.staff title="Review Employee Registration">
    @push('styles')
        <style>
            [x-cloak] { display: none !important; }
        </style>
    @endpush

    <div class="mx-auto max-w-4xl space-y-6" x-data="{ showRejectModal: false, salaryType: '{{ old('salary_type', $employee->salary_type ?? 'monthly') }}' }">
        <!-- HEADER -->
        <div class="flex items-center justify-between gap-3">
            <div>
                <a href="{{ route('admin.staff.approvals.index') }}" class="inline-flex items-center gap-1.5 text-xs font-black text-slate-500 hover:text-slate-900 transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    <span>Back to Pending Approvals</span>
                </a>
                <h1 class="text-2xl font-black text-slate-950 mt-1">Review Employee Registration</h1>
                <p class="text-sm font-semibold text-slate-500">Verify submitted details, assign HR category, and configure salary before approval.</p>
            </div>
        </div>

        <!-- MAIN PROFILE BANNER -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                @if($employee->photo_url)
                    <button type="button" 
                            onclick="openEmployeeImagePreview(this.dataset.fullImage, this.dataset.title)" 
                            data-full-image="{{ $employee->photo_url }}" 
                            data-title="Profile Photo - {{ e($employee->name) }}"
                            class="relative group cursor-pointer text-left focus:outline-none focus:ring-2 focus:ring-emerald-500 rounded-2xl"
                            title="Click to enlarge Profile Photo">
                        <img src="{{ $employee->photo_url }}" alt="{{ $employee->name }}" class="h-20 w-20 rounded-2xl object-cover border-2 border-slate-100 shadow-md group-hover:opacity-90 transition">
                        <div class="pointer-events-none absolute inset-0 flex items-center justify-center rounded-2xl bg-black/40 opacity-0 group-hover:opacity-100 transition">
                            <span class="text-[10px] font-black text-white bg-slate-900/90 px-2 py-1 rounded-lg shadow-sm">🔍 Enlarge</span>
                        </div>
                    </button>
                @else
                    <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-slate-900 text-xl font-black text-white shadow-md">
                        {{ Illuminate\Support\Str::upper(substr($employee->name, 0, 2)) }}
                    </div>
                @endif
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="rounded-full bg-amber-100 px-3 py-0.5 text-xs font-black uppercase text-amber-900 border border-amber-200">
                            PENDING HR REVIEW
                        </span>
                        <span class="rounded-full bg-slate-100 px-3 py-0.5 text-xs font-black text-slate-700">
                            Code: {{ $employee->employee_code }}
                        </span>
                    </div>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $employee->name }}</h2>
                    <p class="text-xs font-bold text-slate-500">Submitted for: {{ $employee->defaultShop?->name ?? 'Shop Staff' }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('delete', $employee)
                    <form method="POST" action="{{ route('admin.staff.duplicate.destroy', $employee) }}" onsubmit="return confirm('Permanently delete this pending registration as a duplicate? This cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-2xl border border-rose-300 bg-white px-5 py-2.5 text-xs font-black text-rose-700 hover:bg-rose-50 transition cursor-pointer">
                            Delete Duplicate
                        </button>
                    </form>
                @endcan
                <button id="reject-trigger-btn" type="button" @click="showRejectModal = true" class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-2.5 text-xs font-black text-rose-800 hover:bg-rose-100 transition cursor-pointer">
                    ✕ Reject Registration
                </button>
            </div>
        </div>

        <!-- 2 COLUMNS GRID: PERSONAL DETAILS & SHOP ORIGIN -->
        <div class="grid gap-6 sm:grid-cols-2">
            <!-- PERSONAL DETAILS -->
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm space-y-3">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-2">Personal Details</h3>
                <div class="space-y-2 text-xs">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Full Legal Name</p>
                        <p class="font-black text-slate-950 text-sm">{{ $employee->name }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Primary Phone</p>
                            <p class="font-bold text-slate-900">{{ $employee->phone }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Emergency Contact</p>
                            <p class="font-semibold text-slate-700">{{ $employee->alternate_phone ?: 'None' }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Email</p>
                            <p class="font-semibold text-slate-700">{{ $employee->email ?: 'None' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Joining Date</p>
                            <p class="font-bold text-slate-900">{{ $employee->joined_on?->format('d M Y') ?? 'N/A' }}</p>
                        </div>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Residential Address</p>
                        <p class="font-semibold text-slate-700">{{ $employee->address }}</p>
                    </div>
                    @if($employee->notes)
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Notes</p>
                            <p class="font-medium text-slate-600 bg-slate-50 p-2 rounded-xl border border-slate-100">{{ $employee->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- SHOP ORIGIN -->
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm space-y-3">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-2">Submitted From Shop</h3>
                <div class="space-y-3 text-xs">
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 space-y-1">
                        <p class="text-[10px] font-black uppercase text-amber-800">Originating Shop</p>
                        <p class="text-lg font-black text-amber-950">{{ $employee->defaultShop?->name ?? 'Office / General' }}</p>
                        <p class="text-[11px] font-bold text-amber-900">Shop Code: {{ $employee->defaultShop?->code ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Submitted By</p>
                        <p class="font-black text-slate-900">{{ $employee->submittedBy?->name ?? 'Shop Owner' }}</p>
                        <p class="text-[11px] font-semibold text-slate-500">{{ $employee->submittedBy?->email }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Submission Timestamp</p>
                        <p class="font-bold text-slate-900">{{ $employee->created_at?->format('d M Y, h:i A') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- GOVERNMENT ID PROOF -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-2">Government ID Proof</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase">ID Type & Document Number</p>
                    <p class="text-sm font-black text-slate-950 uppercase">{{ str_replace('_', ' ', $employee->id_type) }} {{ $employee->other_id_type ? "({$employee->other_id_type})" : '' }}</p>
                    <p class="font-mono text-sm font-bold text-slate-800 mt-0.5">{{ $employee->id_number }}</p>
                </div>
            </div>
            
            <div class="grid gap-4 sm:grid-cols-2 pt-2">
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase mb-1">ID Front Image *</p>
                    @if($employee->id_front_url)
                        <button type="button"
                                onclick="openEmployeeImagePreview(this.dataset.fullImage, this.dataset.title)"
                                data-full-image="{{ $employee->id_front_url }}"
                                data-title="ID Front - {{ e($employee->name) }}"
                                class="relative group h-48 w-full rounded-2xl border border-slate-200 bg-slate-900 overflow-hidden flex items-center justify-center cursor-pointer focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                title="Click to enlarge ID Front">
                            <img src="{{ $employee->id_front_url }}" class="h-full w-full object-contain transition duration-200 group-hover:scale-102" alt="ID Front">
                            <div class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition">
                                <span class="inline-flex items-center gap-1.5 text-white text-xs font-bold bg-slate-900/90 px-3 py-1.5 rounded-xl shadow-md">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10.5 7.5v6m3-3h-6" />
                                    </svg>
                                    <span>Click to Enlarge</span>
                                </span>
                            </div>
                        </button>
                    @else
                        <div class="h-48 w-full rounded-2xl border border-slate-200 bg-slate-900 flex items-center justify-center">
                            <span class="text-xs font-bold text-slate-400">No Image Uploaded</span>
                        </div>
                    @endif
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-500 uppercase mb-1">ID Back Image (optional)</p>
                    @if($employee->id_back_url)
                        <button type="button"
                                onclick="openEmployeeImagePreview(this.dataset.fullImage, this.dataset.title)"
                                data-full-image="{{ $employee->id_back_url }}"
                                data-title="ID Back - {{ e($employee->name) }}"
                                class="relative group h-48 w-full rounded-2xl border border-slate-200 bg-slate-900 overflow-hidden flex items-center justify-center cursor-pointer focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                title="Click to enlarge ID Back">
                            <img src="{{ $employee->id_back_url }}" class="h-full w-full object-contain transition duration-200 group-hover:scale-102" alt="ID Back">
                            <div class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition">
                                <span class="inline-flex items-center gap-1.5 text-white text-xs font-bold bg-slate-900/90 px-3 py-1.5 rounded-xl shadow-md">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10.5 7.5v6m3-3h-6" />
                                    </svg>
                                    <span>Click to Enlarge</span>
                                </span>
                            </div>
                        </button>
                    @else
                        <div class="h-48 w-full rounded-2xl border border-slate-200 bg-slate-900 flex items-center justify-center">
                            <span class="text-xs font-bold text-slate-400">No Image Uploaded</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- EMPLOYMENT & SALARY SETUP (HR FILL-IN FORM) -->
        <form id="approval-form" method="POST" action="{{ route('admin.staff.approve', $employee) }}" class="rounded-3xl border-2 border-emerald-300 bg-emerald-50/60 p-6 shadow-md space-y-4">
            @csrf
            <div>
                <h3 class="text-lg font-black text-emerald-950">Employment & Salary Setup</h3>
                <p class="text-xs font-semibold text-emerald-800 mt-0.5">HR must assign the final Employee Category and Salary structure to activate this employee.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <!-- CATEGORY / DESIGNATION -->
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-700 mb-1">Category / Designation *</label>
                    <div class="relative">
                        <select name="employee_category_id" class="h-10 w-full appearance-none rounded-xl border border-slate-200 bg-white pl-3 pr-8 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600 cursor-pointer" required>
                            <option value="">Select Designation *</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('employee_category_id', $employee->employee_category_id) == $category->id)>
                                    {{ $category->name }} ({{ ucfirst($category->staff_area) }})
                                </option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-500">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>
                    @error('employee_category_id')
                        <p class="mt-1 text-[11px] font-bold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- SALARY TYPE -->
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-700 mb-1">Salary Type *</label>
                    <div class="relative">
                        <select name="salary_type" x-model="salaryType" class="h-10 w-full appearance-none rounded-xl border border-slate-200 bg-white pl-3 pr-8 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600 cursor-pointer" required>
                            <option value="monthly" @selected(old('salary_type', $employee->salary_type ?? 'monthly') === 'monthly')>Monthly Salary</option>
                            <option value="daily_wage" @selected(old('salary_type', $employee->salary_type) === 'daily_wage')>Daily Wage</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-500">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </div>
                    </div>
                    @error('salary_type')
                        <p class="mt-1 text-[11px] font-bold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- MONTHLY SALARY -->
                <div x-show="salaryType === 'monthly'">
                    <label class="block text-xs font-bold uppercase text-slate-700 mb-1">Monthly Salary (₹) *</label>
                    <input type="number" step="0.01" min="0" name="monthly_salary" value="{{ old('monthly_salary', $employee->monthly_salary ? number_format((float)$employee->monthly_salary, 2, '.', '') : '') }}" placeholder="e.g. 18000" class="h-10 w-full rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" :required="salaryType === 'monthly'" :disabled="salaryType !== 'monthly'">
                    @error('monthly_salary')
                        <p class="mt-1 text-[11px] font-bold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- DAILY WAGE -->
                <div x-show="salaryType === 'daily_wage'" x-cloak :class="{ 'hidden': salaryType !== 'daily_wage' }">
                    <label class="block text-xs font-bold uppercase text-slate-700 mb-1">Daily Wage (₹) *</label>
                    <input type="number" step="0.01" min="0" name="daily_wage" value="{{ old('daily_wage', $employee->daily_wage ? number_format((float)$employee->daily_wage, 2, '.', '') : '') }}" placeholder="e.g. 750" class="h-10 w-full rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" :required="salaryType === 'daily_wage'" :disabled="salaryType !== 'daily_wage'">
                    @error('daily_wage')
                        <p class="mt-1 text-[11px] font-bold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="pt-2 flex items-center justify-end gap-3 border-t border-emerald-200/80">
                <button type="submit" form="approval-form" class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-8 text-sm font-black text-white shadow-lg hover:bg-emerald-700 active:scale-95 transition cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    <span>Approve Employee</span>
                </button>
            </div>
        </form>

        <!-- REJECTION MODAL -->
        <div x-show="showRejectModal" x-cloak style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="fixed inset-0 bg-slate-950/75 transition-opacity" @click="showRejectModal = false"></div>
                <div class="relative w-full max-w-md transform overflow-hidden rounded-3xl bg-white p-6 text-left shadow-2xl transition-all space-y-4">
                    <h3 class="text-lg font-black text-slate-950">Reject Staff Registration</h3>
                    <p class="text-xs font-semibold text-slate-500">Provide a reason for rejecting this registration request. The shop owner will see this reason.</p>
                    <form id="reject-form" method="POST" action="{{ route('admin.staff.reject', $employee) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Rejection Reason *</label>
                            <textarea name="rejection_reason" rows="3" placeholder="e.g. Government ID image is blurry" class="w-full rounded-xl border border-slate-200 p-3 text-xs font-semibold focus:border-rose-600 focus:ring-rose-600" required></textarea>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button id="cancel-reject-btn" type="button" @click="showRejectModal = false" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-700 cursor-pointer">Cancel</button>
                            <button type="submit" form="reject-form" class="rounded-xl bg-rose-600 px-5 py-2 text-xs font-black text-white hover:bg-rose-700 cursor-pointer">Submit Rejection</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- PLAIN JS IMAGE LIGHTBOX MODAL -->
        <div id="employee-image-preview-modal" 
             class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-3 sm:p-6" 
             role="dialog" aria-modal="true">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-950/85 backdrop-blur-xs transition-opacity" onclick="closeEmployeeImagePreview()"></div>

            <!-- Modal Panel -->
            <div class="relative z-10 w-full max-w-[95vw] max-h-[85vh] flex flex-col items-center justify-center rounded-3xl bg-slate-900 p-3 shadow-2xl overflow-hidden border border-slate-800" onclick="event.stopPropagation()">
                <div class="w-full flex items-center justify-between gap-3 px-3 py-2 border-b border-slate-800 text-white shrink-0">
                    <span id="employee-image-preview-title" class="text-xs font-bold truncate text-slate-300"></span>
                    <button id="lightbox-close-btn" type="button" onclick="closeEmployeeImagePreview()" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-800 hover:text-white transition cursor-pointer" title="Close Preview (Esc)">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="w-full flex-1 flex items-center justify-center p-2 min-h-0 overflow-auto">
                    <img id="employee-image-preview-img" src="" alt="" class="max-h-[72vh] max-w-full object-contain rounded-xl shadow-lg">
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                window.openEmployeeImagePreview = function (src, title) {
                    if (!src) return;
                    var modal = document.getElementById('employee-image-preview-modal');
                    var img = document.getElementById('employee-image-preview-img');
                    var titleEl = document.getElementById('employee-image-preview-title');

                    if (modal && img && titleEl) {
                        img.src = src;
                        img.alt = title || 'Image Preview';
                        titleEl.textContent = title || 'Image Preview';
                        modal.classList.remove('hidden');
                        document.body.classList.add('overflow-hidden');
                    }
                };

                window.closeEmployeeImagePreview = function () {
                    var modal = document.getElementById('employee-image-preview-modal');
                    var img = document.getElementById('employee-image-preview-img');

                    if (modal) {
                        modal.classList.add('hidden');
                        if (img) img.src = '';
                        document.body.classList.remove('overflow-hidden');
                    }
                };

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        window.closeEmployeeImagePreview();
                    }
                });
            })();
        </script>
    @endpush
</x-layouts.staff>
