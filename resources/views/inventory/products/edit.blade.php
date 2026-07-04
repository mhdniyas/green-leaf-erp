@php
/** @var \App\Models\Product $product */
/** @var \Illuminate\Database\Eloquent\Collection $categories */
$units = ['kg' => 'Kilogram (kg)', 'box' => 'Box', 'bunch' => 'Bunch', 'piece' => 'Piece', 'bag' => 'Bag'];
@endphp

<x-layouts.inventory title="Edit Product: {{ $product->name }}">

    <div class="max-w-2xl">
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Edit Product</h2>
                <p class="text-xs text-gray-500 mt-0.5">Update details for <strong>{{ $product->name }}</strong></p>
            </div>

            <form
                method="POST"
                action="{{ route('inventory.products.update', $product) }}"
                class="p-6 space-y-5"
            >
                @csrf
                @method('PUT')

                {{-- Product Image Upload Area --}}
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Product Image</label>
                    <div class="flex items-center gap-6">
                        {{-- Image Preview --}}
                        <div class="relative w-24 h-24 rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden shrink-0 group">
                            @if($product->image)
                                <img id="preview-image" src="{{ $product->getImageUrl() }}" class="w-full h-full object-cover">
                            @else
                                <img id="preview-image" src="" class="w-full h-full object-cover hidden">
                                <div id="preview-placeholder" class="text-gray-400 text-center p-2">
                                    <svg class="w-8 h-8 mx-auto opacity-60" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375 0 11-.75 0 .375 0 01.75 0z" />
                                    </svg>
                                    <span class="text-[10px] block mt-1 font-medium">No Image</span>
                                </div>
                            @endif
                        </div>

                        {{-- Upload controls --}}
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" onclick="document.getElementById('image_input').click()" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 transition-colors cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                    </svg>
                                    Choose Photo
                                </button>
                                <button type="button" id="remove-btn" onclick="removeSelectedImage()" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-red-600 bg-white border border-red-200 rounded-lg shadow-sm hover:bg-red-50 hover:border-red-300 transition-colors {{ $product->image ? '' : 'hidden' }}">
                                    Remove
                                </button>
                            </div>
                            <p class="text-xs text-gray-400">JPEG, PNG or WebP. Aspect ratio will be cropped to square 1:1.</p>
                        </div>
                    </div>

                    {{-- Hidden inputs --}}
                    <input type="file" id="image_input" accept="image/*" class="hidden" onchange="handleImageSelect(event)">
                    <input type="hidden" id="image_data" name="image_data">
                    <input type="hidden" id="remove_image" name="remove_image" value="0">
                </div>

                {{-- Name + Category row --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label for="name" class="block text-sm font-medium text-gray-700">Product Name <span class="text-red-500">*</span></label>
                        <input id="name" name="name" type="text" required
                               value="{{ old('name', $product->name) }}"
                               placeholder="e.g. Tomato"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('name') border-red-300 @enderror">
                        @error('name') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="category_id" class="block text-sm font-medium text-gray-700">Category <span class="text-red-500">*</span></label>
                        <select id="category_id" name="category_id" required
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white @error('category_id') border-red-300 @enderror">
                            <option value="">Select category…</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected(old('category_id', $product->category_id) == $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- SKU + Unit row --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label for="sku" class="block text-sm font-medium text-gray-700">
                            SKU <span class="text-red-500">*</span>
                            <span class="text-gray-400 font-normal text-xs ml-1">(letters, numbers, hyphens only)</span>
                        </label>
                        <input id="sku" name="sku" type="text" required
                               value="{{ old('sku', $product->sku) }}"
                               placeholder="e.g. TOMATO-001"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono uppercase focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('sku') border-red-300 @enderror">
                        @error('sku') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="unit" class="block text-sm font-medium text-gray-700">Unit of Measure <span class="text-red-500">*</span></label>
                        <select id="unit" name="unit" required
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white @error('unit') border-red-300 @enderror">
                            @foreach($units as $val => $label)
                                <option value="{{ $val }}" @selected(old('unit', $product->unit) === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('unit') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Default Warehouse --}}
                <div class="space-y-1.5">
                    <label for="default_warehouse_id" class="block text-sm font-medium text-gray-700">Default Warehouse <span class="text-gray-400 font-normal">(optional)</span></label>
                    <select id="default_warehouse_id" name="default_warehouse_id"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white @error('default_warehouse_id') border-red-300 @enderror">
                        <option value="">None (No Default)</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" @selected(old('default_warehouse_id', $product->default_warehouse_id) == $wh->id)>{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                    @error('default_warehouse_id') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                </div>

                {{-- Description --}}
                <div class="space-y-1.5">
                    <label for="description" class="block text-sm font-medium text-gray-700">Description <span class="text-gray-400 font-normal">(optional)</span></label>
                    <textarea id="description" name="description" rows="2"
                              placeholder="Product notes, varieties, storage tips…"
                              class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 resize-none">{{ old('description', $product->description) }}</textarea>
                </div>

                {{-- Active toggle --}}
                <div class="flex items-center gap-3">
                    <input id="is_active" name="is_active" type="checkbox" value="1"
                           @checked(old('is_active', $product->is_active))
                           class="w-4 h-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/30 cursor-pointer">
                    <label for="is_active" class="text-sm text-gray-700 cursor-pointer">Active — visible in sales and inventory</label>
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                    <button type="submit" id="save-product-btn"
                            class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition-colors">
                        Save Changes
                    </button>
                    <a href="{{ route('inventory.products.index') }}"
                       class="text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Cropper Modal --}}
    <div id="crop-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
            <div class="fixed inset-0 bg-black/60 transition-opacity" aria-hidden="true" onclick="closeCropModal()"></div>

            <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full border border-gray-100 flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900" id="modal-title">Crop Product Photo</h3>
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
                    <button type="button" onclick="applyCrop()" class="px-4 py-2 text-xs font-semibold text-white bg-brand-600 rounded-lg hover:bg-brand-700">Apply Crop</button>
                </div>
            </div>
        </div>
    </div>

    @push('styles')
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
    @endpush

    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
        <script>
            let cropper = null;
            let selectedFile = null;

            function handleImageSelect(event) {
                const files = event.target.files;
                if (!files || files.length === 0) return;

                selectedFile = files[0];
                const reader = new FileReader();
                reader.onload = function (e) {
                    const cropperImage = document.getElementById('cropper-image');
                    
                    // Show modal first so all elements have non-zero layout dimensions
                    document.getElementById('crop-modal').classList.remove('hidden');

                    // Setup onload handler
                    cropperImage.onload = function () {
                        // Destroy previous cropper if any
                        if (cropper) {
                            cropper.destroy();
                            cropper = null;
                        }

                        // Delay initialization slightly to let modal animation/reflow finish
                        setTimeout(() => {
                            try {
                                cropper = new Cropper(cropperImage, {
                                    aspectRatio: 1,
                                    viewMode: 1,
                                    dragMode: 'move',
                                    autoCropArea: 1,
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

                        // Clear onload to prevent loops
                        cropperImage.onload = null;
                    };

                    cropperImage.src = e.target.result;
                };
                reader.readAsDataURL(selectedFile);
            }

            function closeCropModal() {
                document.getElementById('crop-modal').classList.add('hidden');
                document.getElementById('image_input').value = ''; // Reset input
                if (cropper) {
                    cropper.destroy();
                    cropper = null;
                }
            }

            function applyCrop() {
                if (!cropper) {
                    alert('Cropper is not initialized.');
                    return;
                }

                // Get cropped canvas at 400x400
                const canvas = cropper.getCroppedCanvas({
                    width: 400,
                    height: 400,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });

                if (!canvas) {
                    alert('Could not crop image. Please try again.');
                    return;
                }

                // Convert to base64 DataURL (JPEG for efficiency)
                const dataUrl = canvas.toDataURL('image/jpeg', 0.85);

                // Update hidden input & preview
                document.getElementById('image_data').value = dataUrl;
                document.getElementById('remove_image').value = '0';

                const previewImg = document.getElementById('preview-image');
                previewImg.src = dataUrl;
                previewImg.classList.remove('hidden');

                const placeholder = document.getElementById('preview-placeholder');
                if (placeholder) {
                    placeholder.classList.add('hidden');
                }

                // Show remove button
                document.getElementById('remove-btn').classList.remove('hidden');

                closeCropModal();
            }

            function removeSelectedImage() {
                // Set remove flag
                document.getElementById('remove_image').value = '1';
                document.getElementById('image_data').value = '';
                document.getElementById('image_input').value = '';

                // Update preview and buttons
                const previewImg = document.getElementById('preview-image');
                previewImg.src = '';
                previewImg.classList.add('hidden');

                // Find or dynamically create placeholder if not present
                let placeholder = document.getElementById('preview-placeholder');
                if (!placeholder) {
                    const container = previewImg.parentElement;
                    container.insertAdjacentHTML('beforeend', `
                        <div id="preview-placeholder" class="text-gray-400 text-center p-2">
                            <svg class="w-8 h-8 mx-auto opacity-60" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375 0 11-.75 0 .375 0 01.75 0z" />
                            </svg>
                            <span class="text-[10px] block mt-1 font-medium">No Image</span>
                        </div>
                    `);
                } else {
                    placeholder.classList.remove('hidden');
                }

                document.getElementById('remove-btn').classList.add('hidden');
            }
        </script>
    @endpush

</x-layouts.inventory>
