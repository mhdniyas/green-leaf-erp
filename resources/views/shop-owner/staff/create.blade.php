@extends('shop-owner.layouts.app')

@section('title', $employee ? 'Edit Submission' : 'Add Staff')
@section('page_title', $employee ? 'Edit Staff Submission' : 'Add Shop Staff')

@section('content')
    <div class="mx-auto w-full max-w-2xl space-y-3">
        <!-- COMPACT HEADER -->
        <header class="flex items-center justify-between gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-xs">
            <div>
                <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code]) }}" class="inline-flex items-center gap-1 text-[11px] font-bold text-slate-500 hover:text-slate-900 transition">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    <span>Back to Staff</span>
                </a>
                <h1 class="text-base font-black text-slate-950 sm:text-lg mt-0.5">{{ $employee ? 'Edit Staff Submission' : 'Add New Shop Staff' }}</h1>
                <p class="text-[11px] font-semibold text-slate-400">Submit employee details for HR verification</p>
            </div>
        </header>

        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-2.5 text-xs font-bold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if($employee && $employee->verification_status === 'rejected' && $employee->rejection_reason)
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 space-y-0.5">
                <p class="text-[10px] font-black uppercase text-rose-900 tracking-wider">HR Rejection Reason</p>
                <p class="text-xs font-bold text-rose-800">{{ $employee->rejection_reason }}</p>
                <p class="text-[10px] font-semibold text-rose-600">Please correct details below and resubmit.</p>
            </div>
        @endif

        <!-- SINGLE MAIN COMPACT CARD WITH SUBTLE DIVIDERS -->
        <div class="rounded-2xl border border-slate-200 bg-white p-3.5 sm:p-4 shadow-xs" x-data="{
            idType: '{{ old('id_type', $employee?->id_type ?? 'aadhaar') }}',
        }">
            <form method="POST" action="{{ $employee ? route('shop-owner.staff.employees.resubmit', $employee) : route('shop-owner.staff.employees.store') }}" class="space-y-3">
                @csrf
                @if($employee)
                    @method('PUT')
                @endif
                <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">

                <!-- SECTION 1: PROFILE PHOTO AVATAR UPLOAD (COMPACT) -->
                <div class="flex items-center gap-3 border-b border-slate-100 pb-3">
                    <div class="relative h-16 w-16 rounded-full border border-slate-200 bg-slate-100 flex items-center justify-center overflow-hidden shrink-0">
                        <img id="photo-preview-image" src="{{ $employee?->photo_url ?? '' }}" class="h-full w-full object-cover {{ $employee?->photo_url ? '' : 'hidden' }}" alt="Profile">
                        <div id="photo-preview-placeholder" class="text-center text-[10px] font-bold text-slate-400 {{ $employee?->photo_url ? 'hidden' : '' }}">No Photo</div>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs font-black text-slate-900">Profile Photo *</p>
                        <p class="text-[10px] font-semibold text-slate-400">Square 1:1 crop · Max 512 KB</p>
                        <div class="flex items-center gap-2 pt-0.5">
                            <label class="cursor-pointer rounded-lg bg-slate-950 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-slate-800 transition">
                                <span>Choose Photo</span>
                                <input type="file" id="photo_file_input" accept="image/jpeg,image/png,image/webp" onchange="handleCropImageSelect(event, 'photo')" class="hidden">
                            </label>
                            <button type="button" id="photo-remove-btn" onclick="removeSelectedEmployeeImage('photo')" class="rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-[11px] font-bold text-rose-700 hover:bg-rose-100 transition {{ $employee?->photo_url ? '' : 'hidden' }}">
                                Remove
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="photo_data_url" id="photo_data_url" value="">
                    @error('photo_data_url') <p class="text-[11px] font-bold text-rose-600">Photo required.</p> @enderror
                </div>

                <!-- SECTION 2: BASIC INFO -->
                <div class="space-y-2.5">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Basic Information</p>
                    
                    <!-- FULL NAME (FULL WIDTH) -->
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Full Legal Name *</label>
                        <input type="text" name="name" value="{{ old('name', $employee?->name) }}" placeholder="Full legal name" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>
                        @error('name') <p class="mt-0.5 text-[11px] font-bold text-rose-600">{{ $message }}</p> @enderror
                    </div>



                    <!-- 2 COLUMNS: PRIMARY PHONE / EMERGENCY CONTACT NUMBER -->
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Primary Phone *</label>
                            <input type="tel" name="phone" value="{{ old('phone', $employee?->phone) }}" placeholder="Mobile number" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>
                            @error('phone') <p class="mt-0.5 text-[11px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Emergency Contact Number *</label>
                            <input type="tel" name="alternate_phone" value="{{ old('alternate_phone', $employee?->alternate_phone) }}" placeholder="Emergency contact number" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>
                            @error('alternate_phone') <p class="mt-0.5 text-[11px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- 2 COLUMNS: EMAIL / JOINING DATE -->
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Email</label>
                            <input type="email" name="email" value="{{ old('email', $employee?->email) }}" placeholder="Email address" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Joining Date *</label>
                            <input type="date" name="joined_on" value="{{ old('joined_on', $employee?->joined_on?->format('Y-m-d') ?? date('Y-m-d')) }}" class="h-9 w-full rounded-lg border border-slate-200 px-2 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>
                        </div>
                    </div>
                </div>



                <!-- SECTION 4: GOVERNMENT ID (COMPACT 2 COLUMNS) -->
                <div class="space-y-2.5 border-t border-slate-100 pt-2.5">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Government ID Proof</p>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">ID Type *</label>
                            <div class="relative">
                                <select name="id_type" x-model="idType" class="h-9 w-full appearance-none rounded-lg border border-slate-200 bg-white pl-3 pr-8 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600 cursor-pointer" required>
                                    <option value="aadhaar">Aadhaar</option>
                                    <option value="passport">Passport</option>
                                    <option value="driving_licence">Driving Licence</option>
                                    <option value="voter_id">Voter ID</option>
                                    <option value="pan">PAN</option>
                                    <option value="other">Other</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-500">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">ID Number *</label>
                            <input type="text" name="id_number" value="{{ old('id_number', $employee?->id_number) }}" placeholder="ID document number" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>
                        </div>
                    </div>

                    <div x-show="idType === 'other'" x-cloak :class="{ 'hidden': idType !== 'other' }">
                        <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Other ID Type Name *</label>
                        <input type="text" name="other_id_type" value="{{ old('other_id_type', $employee?->other_id_type) }}" placeholder="Specify ID type" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" :required="idType === 'other'" :disabled="idType !== 'other'">
                    </div>

                    <!-- SIDE-BY-SIDE COMPACT ID PROOF PREVIEWS -->
                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <!-- FRONT ID -->
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-2 text-center space-y-1.5">
                            <label class="block text-[10px] font-bold uppercase text-slate-600 truncate">ID Front *</label>
                            <div class="h-24 w-full rounded-lg border border-slate-200 bg-white flex items-center justify-center overflow-hidden">
                                <img id="id_front-preview-image" src="{{ $employee?->id_front_url ?? '' }}" class="h-full w-full object-contain {{ $employee?->id_front_url ? '' : 'hidden' }}" alt="ID Front">
                                <span id="id_front-preview-placeholder" class="text-[10px] font-bold text-slate-400 {{ $employee?->id_front_url ? 'hidden' : '' }}">No Front Image</span>
                            </div>
                            <div class="flex items-center justify-center gap-1">
                                <label class="cursor-pointer rounded bg-slate-950 px-2 py-0.5 text-[10px] font-bold text-white hover:bg-slate-800">
                                    <span>Choose</span>
                                    <input type="file" id="id_front_file_input" accept="image/jpeg,image/png,image/webp" onchange="handleCropImageSelect(event, 'id_front')" class="hidden">
                                </label>
                                <button type="button" id="id_front-remove-btn" onclick="removeSelectedEmployeeImage('id_front')" class="rounded border border-rose-200 bg-rose-50 px-1.5 py-0.5 text-[10px] font-bold text-rose-700 {{ $employee?->id_front_url ? '' : 'hidden' }}">
                                    Clear
                                </button>
                            </div>
                            <input type="hidden" name="id_front_data_url" id="id_front_data_url" value="">
                            @error('id_front_data_url') <p class="text-[10px] font-bold text-rose-600">Front image required.</p> @enderror
                        </div>

                        <!-- BACK ID -->
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-2 text-center space-y-1.5">
                            <label class="block text-[10px] font-bold uppercase text-slate-600 truncate">ID Back</label>
                            <div class="h-24 w-full rounded-lg border border-slate-200 bg-white flex items-center justify-center overflow-hidden">
                                <img id="id_back-preview-image" src="{{ $employee?->id_back_url ?? '' }}" class="h-full w-full object-contain {{ $employee?->id_back_url ? '' : 'hidden' }}" alt="ID Back">
                                <span id="id_back-preview-placeholder" class="text-[10px] font-bold text-slate-400 {{ $employee?->id_back_url ? 'hidden' : '' }}">No Back Image</span>
                            </div>
                            <div class="flex items-center justify-center gap-1">
                                <label class="cursor-pointer rounded bg-slate-950 px-2 py-0.5 text-[10px] font-bold text-white hover:bg-slate-800">
                                    <span>Choose</span>
                                    <input type="file" id="id_back_file_input" accept="image/jpeg,image/png,image/webp" onchange="handleCropImageSelect(event, 'id_back')" class="hidden">
                                </label>
                                <button type="button" id="id_back-remove-btn" onclick="removeSelectedEmployeeImage('id_back')" class="rounded border border-rose-200 bg-rose-50 px-1.5 py-0.5 text-[10px] font-bold text-rose-700 {{ $employee?->id_back_url ? '' : 'hidden' }}">
                                    Clear
                                </button>
                            </div>
                            <input type="hidden" name="id_back_data_url" id="id_back_data_url" value="">
                        </div>
                    </div>
                </div>

                <!-- SECTION 5: ADDRESS & NOTES (FULL WIDTH) -->
                <div class="space-y-2 border-t border-slate-100 pt-2.5">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Residential Address *</label>
                        <textarea name="address" rows="2" placeholder="Complete address" class="w-full rounded-lg border border-slate-200 p-2 text-xs font-semibold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>{{ old('address', $employee?->address) }}</textarea>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Notes (optional)</label>
                        <textarea name="notes" rows="1" placeholder="Notes for HR" class="w-full rounded-lg border border-slate-200 p-2 text-xs font-semibold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600">{{ old('notes', $employee?->notes) }}</textarea>
                    </div>
                </div>

                <!-- SUBMIT BUTTON -->
                <div class="pt-2">
                    <button type="submit" class="inline-flex h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-600 text-xs font-black text-white shadow-xs hover:bg-emerald-700 active:scale-[0.99] transition">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                        </svg>
                        <span>{{ $employee ? 'Resubmit for HR Approval' : 'Submit for HR Approval' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- SHARED REUSED CROPPER MODAL & SCRIPTS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>

    <div id="crop-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex min-h-screen items-center justify-center p-3 text-center sm:p-0">
            <div class="fixed inset-0 bg-slate-950/75 transition-opacity" onclick="closeCropModal()"></div>
            <div class="relative w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-4 text-left shadow-2xl transition-all">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <h3 class="text-xs font-black text-slate-950" id="crop-modal-title">Crop Image</h3>
                    <button type="button" onclick="closeCropModal()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="mt-3">
                    <div class="max-h-[300px] overflow-hidden rounded-xl bg-slate-900 flex items-center justify-center">
                        <img id="crop-image-target" src="" class="max-w-full block" alt="Crop Target">
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between gap-2">
                    <div class="flex gap-1">
                        <button type="button" onclick="rotateCropper(-90)" class="rounded-lg border border-slate-200 px-2 py-1 text-[10px] font-bold text-slate-700 hover:bg-slate-50">Rotate L</button>
                        <button type="button" onclick="rotateCropper(90)" class="rounded-lg border border-slate-200 px-2 py-1 text-[10px] font-bold text-slate-700 hover:bg-slate-50">Rotate R</button>
                    </div>
                    <div class="flex gap-1.5">
                        <button type="button" onclick="closeCropModal()" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button type="button" onclick="applyCrop()" class="rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-black text-white hover:bg-emerald-700">Apply Crop</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let cropper = null;
        let currentTargetField = null;

        function handleCropImageSelect(event, targetField) {
            const files = event.target.files;
            if (!files || files.length === 0) return;
            const file = files[0];
            currentTargetField = targetField;

            const reader = new FileReader();
            reader.onload = function(e) {
                const image = document.getElementById('crop-image-target');
                image.src = e.target.result;
                const modal = document.getElementById('crop-modal');
                const title = document.getElementById('crop-modal-title');
                
                title.textContent = targetField === 'photo' ? 'Crop Profile Photo (Square 1:1)' : 'Crop Government ID Document';
                modal.classList.remove('hidden');

                if (cropper) {
                    cropper.destroy();
                }

                const options = {
                    viewMode: 1,
                    autoCropArea: 0.95,
                    responsive: true,
                    restore: false,
                };

                if (targetField === 'photo') {
                    options.aspectRatio = 1;
                }

                cropper = new Cropper(image, options);
            };
            reader.readAsDataURL(file);
        }

        function closeCropModal() {
            const modal = document.getElementById('crop-modal');
            modal.classList.add('hidden');
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            if (currentTargetField) {
                const input = document.getElementById(currentTargetField + '_file_input');
                if (input) input.value = '';
            }
        }

        function rotateCropper(degree) {
            if (cropper) cropper.rotate(degree);
        }

        function applyCrop() {
            if (!cropper || !currentTargetField) return;

            const canvasOptions = currentTargetField === 'photo'
                ? { width: 600, height: 600 }
                : { maxWidth: 1600, maxHeight: 1600 };

            const canvas = cropper.getCroppedCanvas(canvasOptions);
            const dataUrl = canvas.toDataURL('image/jpeg', 0.85);

            document.getElementById(currentTargetField + '_data_url').value = dataUrl;

            const previewImg = document.getElementById(currentTargetField + '-preview-image');
            const previewPlaceholder = document.getElementById(currentTargetField + '-preview-placeholder');
            const removeBtn = document.getElementById(currentTargetField + '-remove-btn');

            if (previewImg) {
                previewImg.src = dataUrl;
                previewImg.classList.remove('hidden');
            }
            if (previewPlaceholder) previewPlaceholder.classList.add('hidden');
            if (removeBtn) removeBtn.classList.remove('hidden');

            closeCropModal();
        }

        function removeSelectedEmployeeImage(targetField) {
            document.getElementById(targetField + '_data_url').value = '';
            const fileInput = document.getElementById(targetField + '_file_input');
            if (fileInput) fileInput.value = '';

            const previewImg = document.getElementById(targetField + '-preview-image');
            const previewPlaceholder = document.getElementById(targetField + '-preview-placeholder');
            const removeBtn = document.getElementById(targetField + '-remove-btn');

            if (previewImg) {
                previewImg.src = '';
                previewImg.classList.add('hidden');
            }
            if (previewPlaceholder) previewPlaceholder.classList.remove('hidden');
            if (removeBtn) removeBtn.classList.add('hidden');
        }
    </script>
@endsection
