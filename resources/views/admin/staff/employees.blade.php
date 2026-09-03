<x-layouts.staff title="Staff Employees">
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-slate-950">Employees</h1>
                <p class="text-sm font-semibold text-slate-500">CRUD all staff records and switch quickly between category tabs.</p>
            </div>
            <div class="flex flex-wrap items-start gap-3">
                <a href="{{ route('admin.staff.assignments.index', ['date' => $selectedDate->format('Y-m-d')]) }}" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white">Assign Employees</a>
            </div>
        </div>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap gap-2">
                @php
                    $selectedCategoryCode = $selectedCategory?->code;
                    $isPendingTab = ($selectedTab ?? 'all') === 'pending';
                @endphp
                <a href="{{ route('admin.staff.employees.index', request()->except('category', 'tab', 'page')) }}" class="rounded-full px-4 py-2 text-sm font-black {{ !$isPendingTab && $selectedCategoryCode === null ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700' }}">
                    All Categories ({{ $employees->total() }})
                </a>
                @foreach($categoryTabs as $tab)
                    <a href="{{ route('admin.staff.employees.index', array_merge(request()->except('page', 'tab'), ['category' => $tab['code']])) }}" class="rounded-full px-4 py-2 text-sm font-black {{ !$isPendingTab && $selectedCategoryCode === $tab['code'] ? 'bg-cyan-500 text-slate-950' : 'bg-slate-100 text-slate-700' }}">
                        {{ $tab['name'] }} ({{ $tab['count'] }})
                    </a>
                @endforeach

                @if(isset($pendingCount) && $pendingCount > 0)
                    <a href="{{ route('admin.staff.approvals.index') }}" class="rounded-full border border-amber-300 px-4 py-2 text-sm font-black bg-amber-50 text-amber-900 hover:bg-amber-100 transition">
                        Pending HR Approvals ({{ $pendingCount }})
                    </a>
                @endif
            </div>

            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-black text-slate-950">Staff CRUD</h2>
                    <p class="text-sm font-semibold text-slate-500">Search by name, code, phone, or email. Linked user roles and client shops are shown inline.</p>
                </div>
                <form method="GET" class="flex flex-wrap gap-2">
                    @if($selectedCategoryCode !== null)
                        <input type="hidden" name="category" value="{{ $selectedCategoryCode }}">
                    @endif
                    <input type="search" name="search" value="{{ $search }}" placeholder="Search name, code, phone" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <select name="staff_area" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <option value="">All Areas</option>
                        <option value="office" @selected(request('staff_area') === 'office')>Office</option>
                        <option value="shop" @selected(request('staff_area') === 'shop')>Shop</option>
                    </select>
                    <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white">Filter</button>
                </form>
            </div>

            <div class="mt-5 overflow-x-auto">
                @php($employeeSerial = ($employees->currentPage() - 1) * $employees->perPage())
                <table class="min-w-full text-left text-sm">
                    <thead class="text-slate-500">
                            <tr>
                                <th class="pb-3">SL No</th>
                                <th class="pb-3">Photo</th>
                                <th class="pb-3">Name & Code</th>
                                <th class="pb-3">ID Proof</th>
                                <th class="pb-3">Category</th>
                                <th class="pb-3">Workplace</th>
                                <th class="pb-3">Contact</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3">Salary</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        @foreach($employees as $employee)
                            <tr>
                                <td class="py-3 font-black text-slate-500">{{ $employeeSerial + $loop->iteration }}</td>
                                <td class="py-3">
                                    @if($employee->photo_url)
                                        <img src="{{ $employee->photo_url }}" alt="{{ $employee->name }}" class="h-10 w-10 rounded-full object-cover border border-slate-200">
                                    @else
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-xs font-black text-slate-500 uppercase border border-slate-200">
                                            {{ substr($employee->name, 0, 2) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <a href="{{ route('admin.staff.show', $employee) }}" class="font-bold text-slate-900 underline-offset-4 hover:text-cyan-700 hover:underline">{{ $employee->name }}</a>
                                    <p class="text-xs font-mono text-slate-500">{{ $employee->employee_code }}</p>
                                </td>
                                <td class="py-3 font-mono text-xs text-slate-700">
                                    {{ $employee->masked_id_number ?? 'N/A' }}
                                </td>
                                <td class="py-3">
                                    <span class="font-semibold text-slate-700">{{ $employee->category?->name ?? 'Pending Assignment' }}</span>
                                    <p class="text-[10px] font-black uppercase text-slate-400">{{ $employee->staff_area }}</p>
                                </td>
                                <td class="py-3 font-semibold text-slate-700">
                                    {{ $employee->defaultShop?->name ?? 'Office / General' }}
                                </td>
                                <td class="py-3 text-xs font-semibold text-slate-600">
                                    <p>{{ $employee->phone ?: 'No phone' }}</p>
                                    @if($employee->email)
                                        <p class="text-slate-400">{{ $employee->email }}</p>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.12em] {{ $employee->employment_status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                                        {{ $employee->employment_status }}
                                    </span>
                                    @if($employee->verification_status === 'pending')
                                        <p class="mt-1"><span class="rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase text-amber-900">Pending HR</span></p>
                                    @elseif($employee->verification_status === 'rejected')
                                        <p class="mt-1"><span class="rounded-full bg-rose-100 px-2 py-0.5 text-[9px] font-black uppercase text-rose-900">Rejected</span></p>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <p class="font-bold text-slate-900">Rs. {{ number_format((float) $employee->monthly_salary, 2) }}</p>
                                    @if($employee->salary_type === 'daily_wage')
                                        <p class="text-xs font-semibold text-cyan-700">Daily Rs. {{ number_format((float) $employee->daily_wage, 2) }}</p>
                                    @else
                                        <p class="text-xs font-semibold text-slate-500">Monthly</p>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $employees->withQueryString()->links() }}</div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" x-data="{
            idType: 'aadhaar',
            categoryId: '{{ $categories->first()?->id }}',
            staffArea: '{{ $categories->first()?->staff_area ?? "office" }}',
            salaryType: 'monthly',
            categoriesMap: @json($categories->pluck('staff_area', 'id')),
            onCategoryChange() {
                if (this.categoryId && this.categoriesMap[this.categoryId]) {
                    this.staffArea = this.categoriesMap[this.categoryId];
                }
            }
        }">
            <h2 class="text-xl font-black text-slate-950">Add Employee</h2>
            <p class="text-xs font-semibold text-slate-500 mt-0.5 mb-4">Create a new real employee record. Profile photo and ID proofs feature live preview and cropping.</p>
            
            <form method="POST" action="{{ route('admin.staff.store') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Full Name *</label>
                    <input type="text" name="name" placeholder="Full name" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Employee Code</label>
                    <input type="text" name="employee_code" value="{{ App\Models\Employee::generateNextCode() }}" placeholder="Auto-generated if left blank" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Category / Designation *</label>
                    <select name="employee_category_id" x-model="categoryId" @change="onCategoryChange()" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        <option value="">Select category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected($selectedCategoryCode === $category->code)>{{ $category->name }} ({{ ucfirst($category->staff_area) }})</option>
                        @endforeach
                    </select>
                </div>

                <input type="hidden" name="staff_area" :value="staffArea">

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">
                        Default Shop / Workplace
                        <span x-show="staffArea === 'shop'" class="text-rose-600 font-bold">*</span>
                    </label>
                    <select name="default_shop_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" :required="staffArea === 'shop'">
                        <option value="">Select default shop</option>
                        @foreach($shops as $shop)
                            <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Primary Phone *</label>
                    <input type="text" name="phone" placeholder="Phone number" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Emergency Contact Number *</label>
                    <input type="text" name="alternate_phone" placeholder="Emergency contact number" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Email (optional)</label>
                    <input type="email" name="email" placeholder="Email address" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Joining Date</label>
                    <input type="date" name="joined_on" value="{{ date('Y-m-d') }}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Salary Type *</label>
                    <select name="salary_type" x-model="salaryType" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        <option value="monthly">Monthly salary</option>
                        <option value="daily_wage">Daily wage</option>
                    </select>
                </div>

                <div x-show="salaryType === 'monthly'" :class="{ 'hidden': salaryType !== 'monthly' }">
                    <label class="block text-xs font-bold text-slate-600 mb-1">Monthly Salary *</label>
                    <input type="number" step="0.01" name="monthly_salary" placeholder="Monthly salary" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" :required="salaryType === 'monthly'" :disabled="salaryType !== 'monthly'">
                </div>

                <div x-show="salaryType === 'daily_wage'" x-cloak :class="{ 'hidden': salaryType !== 'daily_wage' }">
                    <label class="block text-xs font-bold text-slate-600 mb-1">Daily Wage *</label>
                    <input type="number" step="0.01" name="daily_wage" placeholder="Daily wage" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" :required="salaryType === 'daily_wage'" :disabled="salaryType !== 'daily_wage'">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Government ID Type *</label>
                    <select name="id_type" x-model="idType" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        <option value="aadhaar">Aadhaar</option>
                        <option value="passport">Passport</option>
                        <option value="driving_licence">Driving Licence</option>
                        <option value="voter_id">Voter ID</option>
                        <option value="pan">PAN</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div x-show="idType === 'other'" x-cloak :class="{ 'hidden': idType !== 'other' }">
                    <label class="block text-xs font-bold text-slate-600 mb-1">Other ID Type Name *</label>
                    <input type="text" name="other_id_type" placeholder="e.g. State Ration Card" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" :required="idType === 'other'" :disabled="idType !== 'other'">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">ID Number *</label>
                    <input type="text" name="id_number" placeholder="Document ID number" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                </div>

                <!-- PROFILE PHOTO CROP & PREVIEW CARD -->
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                    <label class="block text-xs font-bold text-slate-700">Profile Photo (Square 1:1)</label>
                    <div class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-200 bg-white min-h-[140px]">
                        <img id="photo-preview-image" src="" class="h-28 w-28 rounded-2xl object-cover border border-slate-300 shadow-sm hidden" alt="Profile Preview">
                        <div id="photo-preview-placeholder" class="text-center text-xs text-slate-400 font-semibold py-4">No photo selected</div>
                    </div>
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        <label class="cursor-pointer rounded-xl bg-slate-950 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-slate-800 transition">
                            <span>Choose Photo</span>
                            <input type="file" id="photo_file_input" accept="image/jpeg,image/png,image/webp" onchange="handleCropImageSelect(event, 'photo')" class="hidden">
                        </label>
                        <button type="button" id="photo-remove-btn" onclick="removeSelectedEmployeeImage('photo')" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100 transition hidden">
                            Remove
                        </button>
                    </div>
                    <input type="hidden" name="photo_data_url" id="photo_data_url" value="">
                </div>

                <!-- GOVERNMENT ID FRONT CROP & PREVIEW CARD -->
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                    <label class="block text-xs font-bold text-slate-700">Government ID Front * (Free Crop)</label>
                    <div class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-200 bg-white min-h-[140px]">
                        <img id="id_front-preview-image" src="" class="h-28 w-auto max-w-full rounded-xl object-contain border border-slate-300 shadow-sm hidden" alt="ID Front Preview">
                        <div id="id_front-preview-placeholder" class="text-center text-xs text-slate-400 font-semibold py-4">No front image selected</div>
                    </div>
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        <label class="cursor-pointer rounded-xl bg-slate-950 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-slate-800 transition">
                            <span>Choose Image</span>
                            <input type="file" id="id_front_file_input" accept="image/jpeg,image/png,image/webp" onchange="handleCropImageSelect(event, 'id_front')" class="hidden">
                        </label>
                        <button type="button" id="id_front-remove-btn" onclick="removeSelectedEmployeeImage('id_front')" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100 transition hidden">
                            Remove
                        </button>
                    </div>
                    <input type="hidden" name="id_front_data_url" id="id_front_data_url" value="">
                </div>

                <!-- GOVERNMENT ID BACK CROP & PREVIEW CARD -->
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                    <label class="block text-xs font-bold text-slate-700">Government ID Back (Free Crop)</label>
                    <div class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-200 bg-white min-h-[140px]">
                        <img id="id_back-preview-image" src="" class="h-28 w-auto max-w-full rounded-xl object-contain border border-slate-300 shadow-sm hidden" alt="ID Back Preview">
                        <div id="id_back-preview-placeholder" class="text-center text-xs text-slate-400 font-semibold py-4">No back image selected</div>
                    </div>
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        <label class="cursor-pointer rounded-xl bg-slate-950 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-slate-800 transition">
                            <span>Choose Image</span>
                            <input type="file" id="id_back_file_input" accept="image/jpeg,image/png,image/webp" onchange="handleCropImageSelect(event, 'id_back')" class="hidden">
                        </label>
                        <button type="button" id="id_back-remove-btn" onclick="removeSelectedEmployeeImage('id_back')" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100 transition hidden">
                            Remove
                        </button>
                    </div>
                    <input type="hidden" name="id_back_data_url" id="id_back_data_url" value="">
                </div>

                <div class="xl:col-span-3">
                    <label class="block text-xs font-bold text-slate-600 mb-1">Address</label>
                    <textarea name="address" rows="2" placeholder="Residential address" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                </div>

                <div class="xl:col-span-3">
                    <label class="block text-xs font-bold text-slate-600 mb-1">Notes</label>
                    <textarea name="notes" rows="2" placeholder="Additional HR notes" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                </div>

                <input type="hidden" name="employment_status" value="active">
                <div class="xl:col-span-3 mt-2">
                    <button type="submit" class="rounded-xl bg-cyan-500 px-6 py-2.5 text-sm font-black text-slate-950 shadow-sm hover:bg-cyan-400">Create Employee</button>
                </div>
            </form>

            {{-- Crop Modal (Reused from Product Crop implementation: resources/views/inventory/products/create.blade.php) --}}
            <div id="crop-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
                    <div class="fixed inset-0 bg-black/60 transition-opacity" aria-hidden="true" onclick="closeCropModal()"></div>

                    <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full border border-gray-100 flex flex-col max-h-[90vh]">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-900" id="crop-modal-title">Crop Image</h3>
                            <button type="button" onclick="closeCropModal()" class="text-gray-400 hover:text-gray-500">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        
                        {{-- Cropper Container --}}
                        <div class="flex-1 overflow-hidden p-6 bg-gray-950 flex items-center justify-center min-h-[380px] max-h-[500px]">
                            <div style="width: 100%; max-width: 450px; height: 350px; position: relative;">
                                <img id="cropper-image" style="display: block; max-width: 100%; max-height: 100%;">
                            </div>
                        </div>

                        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-end gap-3">
                            <button type="button" onclick="closeCropModal()" class="px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                            <button type="button" onclick="applyCrop()" class="px-4 py-2 text-xs font-semibold text-slate-950 bg-cyan-500 rounded-lg hover:bg-cyan-400">Apply Crop</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

@push('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
@endpush

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
    <script>
        let cropper = null;
        let currentCropTarget = null;
        let selectedFile = null;

        const cropTargetConfigs = {
            photo: {
                title: 'Crop Profile Photo',
                aspectRatio: 1,
                maxWidth: 800,
                dataInputId: 'photo_data_url',
                previewImgId: 'photo-preview-image',
                placeholderId: 'photo-preview-placeholder',
                removeBtnId: 'photo-remove-btn',
                fileInputId: 'photo_file_input'
            },
            id_front: {
                title: 'Crop ID Front Document',
                aspectRatio: NaN,
                maxWidth: 1400,
                dataInputId: 'id_front_data_url',
                previewImgId: 'id_front-preview-image',
                placeholderId: 'id_front-preview-placeholder',
                removeBtnId: 'id_front-remove-btn',
                fileInputId: 'id_front_file_input'
            },
            id_back: {
                title: 'Crop ID Back Document',
                aspectRatio: NaN,
                maxWidth: 1400,
                dataInputId: 'id_back_data_url',
                previewImgId: 'id_back-preview-image',
                placeholderId: 'id_back-preview-placeholder',
                removeBtnId: 'id_back-remove-btn',
                fileInputId: 'id_back_file_input'
            }
        };

        function handleCropImageSelect(event, targetKey) {
            const files = event.target.files;
            if (!files || files.length === 0) return;

            const file = files[0];
            if (!file.type.startsWith('image/')) {
                alert('Please select a valid image file (JPG, PNG, or WEBP).');
                event.target.value = '';
                return;
            }

            currentCropTarget = targetKey;
            selectedFile = file;
            const config = cropTargetConfigs[targetKey];

            const reader = new FileReader();
            reader.onload = function (e) {
                const cropperImage = document.getElementById('cropper-image');
                document.getElementById('crop-modal-title').textContent = config ? config.title : 'Crop Image';

                // Show modal first so all elements have non-zero layout dimensions
                document.getElementById('crop-modal').classList.remove('hidden');

                // Setup onload handler
                cropperImage.onload = function () {
                    if (cropper) {
                        cropper.destroy();
                        cropper = null;
                    }

                    setTimeout(() => {
                        try {
                            cropper = new Cropper(cropperImage, {
                                aspectRatio: config ? config.aspectRatio : 1,
                                viewMode: 1,
                                dragMode: 'move',
                                autoCropArea: 0.95,
                                restore: false,
                                guides: true,
                                center: true,
                                highlight: false,
                                cropBoxMovable: true,
                                cropBoxResizable: true,
                                toggleDragModeOnDblclick: false,
                            });
                        } catch (err) {
                            console.error('Failed to initialize Cropper:', err);
                        }
                    }, 100);

                    cropperImage.onload = null;
                };

                cropperImage.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        function closeCropModal() {
            document.getElementById('crop-modal').classList.add('hidden');

            if (currentCropTarget && cropTargetConfigs[currentCropTarget]) {
                const fileInput = document.getElementById(cropTargetConfigs[currentCropTarget].fileInputId);
                if (fileInput) fileInput.value = '';
            }

            if (cropper) {
                cropper.destroy();
                cropper = null;
            }

            const cropperImage = document.getElementById('cropper-image');
            if (cropperImage) {
                cropperImage.onload = null;
                cropperImage.src = '';
            }
        }

        function applyCrop() {
            if (!cropper || !currentCropTarget || !cropTargetConfigs[currentCropTarget]) {
                alert('Cropper is not initialized.');
                return;
            }

            const config = cropTargetConfigs[currentCropTarget];
            const canvasOptions = {
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            };

            if (!isNaN(config.aspectRatio) && config.aspectRatio === 1) {
                canvasOptions.width = 600;
                canvasOptions.height = 600;
            } else if (config.maxWidth) {
                canvasOptions.maxWidth = config.maxWidth;
            }

            const canvas = cropper.getCroppedCanvas(canvasOptions);

            if (!canvas) {
                alert('Could not crop image. Please try again.');
                return;
            }

            let quality = 0.85;
            let dataUrl = canvas.toDataURL('image/jpeg', quality);

            while (dataUrl.length * 0.75 > 524288 && quality > 0.35) {
                quality -= 0.10;
                dataUrl = canvas.toDataURL('image/jpeg', quality);
            }

            const dataInput = document.getElementById(config.dataInputId);
            if (dataInput) dataInput.value = dataUrl;

            const previewImg = document.getElementById(config.previewImgId);
            if (previewImg) {
                previewImg.src = dataUrl;
                previewImg.classList.remove('hidden');
            }

            const placeholder = document.getElementById(config.placeholderId);
            if (placeholder) {
                placeholder.classList.add('hidden');
            }

            const removeBtn = document.getElementById(config.removeBtnId);
            if (removeBtn) {
                removeBtn.classList.remove('hidden');
            }

            closeCropModal();
        }

        function removeSelectedEmployeeImage(targetKey) {
            const config = cropTargetConfigs[targetKey];
            if (!config) return;

            const dataInput = document.getElementById(config.dataInputId);
            if (dataInput) dataInput.value = '';

            const previewImg = document.getElementById(config.previewImgId);
            if (previewImg) {
                previewImg.src = '';
                previewImg.classList.add('hidden');
            }

            const placeholder = document.getElementById(config.placeholderId);
            if (placeholder) {
                placeholder.classList.remove('hidden');
            }

            const removeBtn = document.getElementById(config.removeBtnId);
            if (removeBtn) {
                removeBtn.classList.add('hidden');
            }

            const fileInput = document.getElementById(config.fileInputId);
            if (fileInput) fileInput.value = '';
        }
    </script>
@endpush
</x-layouts.staff>
