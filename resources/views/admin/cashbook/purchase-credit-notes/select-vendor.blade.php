@extends('admin.cashbook.layouts.app')

@section('title', 'Select Vendor — New Purchase Credit Note — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="store" class="h-5 w-5 text-emerald-600"></i>
    <span>New Purchase Credit Note — Select Vendor</span>
@endsection

@section('header_subtitle')
    Search and select a vendor to load their eligible products, purchase invoices, and credit note form.
@endsection

@section('content')
<div class="mx-auto max-w-3xl space-y-6"
     x-data="vendorPicker({
         searchUrl: '{{ route('admin.cashbook.purchase-credit-notes.search-vendors') }}',
         createBaseUrl: '{{ route('admin.cashbook.purchase-credit-notes.create') }}'
     })"
     x-init="fetchVendors('')">

    <div class="flex items-center justify-between border-b border-slate-200/80 pb-4">
        <a href="{{ route('admin.cashbook.purchase-credit-notes.index') }}"
           class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5 text-slate-400"></i>
            <span>Back to Purchase Credit Notes</span>
        </a>
    </div>

    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-2xs space-y-4">
        <div>
            <label class="mb-1.5 block text-xs font-black uppercase tracking-wider text-slate-500">Search Vendor</label>
            <div class="relative">
                <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-3 h-4 w-4 text-slate-400"></i>
                <input type="text"
                       x-model="query"
                       @input="onSearch($event.target.value)"
                       placeholder="Type vendor name, code, or phone..."
                       autofocus
                       class="h-10 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-4 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">
            </div>
        </div>

        <div x-show="loading" class="flex items-center gap-2 py-4 text-xs font-semibold text-slate-500">
            <i data-lucide="loader-2" class="h-4 w-4 animate-spin text-emerald-600"></i>
            <span>Loading vendors...</span>
        </div>

        <div x-show="errorMessage" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700" x-text="errorMessage"></div>

        <div x-show="!loading && !errorMessage && vendors.length === 0" x-cloak class="py-8 text-center text-xs text-slate-500">
            No matching vendors found.
        </div>

        <div x-show="!loading && vendors.length > 0" class="divide-y divide-slate-100 rounded-xl border border-slate-200 overflow-hidden">
            <template x-for="vendor in vendors" :key="vendor.id">
                <a :href="`${createBaseUrl}?supplier=${encodeURIComponent(vendor.public_uuid)}`"
                   class="flex items-center justify-between px-4 py-3 hover:bg-emerald-50/60 transition">
                    <div>
                        <div class="text-xs font-bold text-slate-900" x-text="vendor.name"></div>
                        <div class="mt-0.5 text-[11px] text-slate-500">
                            <span x-show="vendor.primary_phone" x-text="vendor.primary_phone"></span>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700">
                        <span>Select</span>
                        <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                    </span>
                </a>
            </template>
        </div>
    </div>
</div>

@push('scripts')
<script>
function vendorPicker(config) {
    return {
        searchUrl: config.searchUrl,
        createBaseUrl: config.createBaseUrl,
        query: '',
        vendors: [],
        loading: false,
        errorMessage: '',
        timer: null,
        seq: 0,
        controller: null,

        onSearch(val) {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.fetchVendors(val), 280);
        },

        async fetchVendors(q) {
            const mySeq = ++this.seq;
            if (this.controller) {
                this.controller.abort();
            }
            this.controller = new AbortController();
            this.loading = true;
            this.errorMessage = '';

            try {
                const res = await fetch(`${this.searchUrl}?q=${encodeURIComponent(q)}&limit=20`, {
                    headers: { 'Accept': 'application/json' },
                    signal: this.controller.signal
                });
                if (!res.ok) {
                    throw new Error('Unable to search vendors.');
                }
                const data = await res.json();
                if (mySeq === this.seq) {
                    this.vendors = data.items || [];
                    this.$nextTick(() => window.lucide?.createIcons());
                }
            } catch (e) {
                if (e.name !== 'AbortError' && mySeq === this.seq) {
                    this.errorMessage = 'Failed to load vendors. Please try again.';
                    this.vendors = [];
                }
            } finally {
                if (mySeq === this.seq) {
                    this.loading = false;
                }
            }
        }
    };
}
</script>
@endpush
@endsection
