@extends('admin.cashbook.layouts.app')

@section('title', 'Select Invoice — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="file-plus" class="w-5 h-5 text-emerald-600"></i> Add Credit Note
@endsection

@section('header_subtitle')
    Independent price, quantity, shortage, or return adjustments for shop invoices.
@endsection

@section('content')
<div class="mx-auto max-w-2xl py-12 px-4 sm:px-6 lg:px-8">
    <div id="reselect-card" class="rounded-3xl border border-slate-200/90 bg-white p-8 text-center shadow-xs space-y-6">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 border border-amber-200/70">
            <i data-lucide="file-question" class="h-8 w-8"></i>
        </div>

        <div class="space-y-2">
            <h3 class="text-lg font-black text-slate-900">
                Invoice Selection Required
            </h3>
            <p class="text-xs font-medium text-slate-600 max-w-md mx-auto leading-relaxed">
                {{ $message ?? 'Please select an invoice from the Invoices Report to create a credit note.' }}
            </p>
        </div>

        <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="{{ route('admin.cashbook.reports.shops.invoices') }}"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 text-xs font-bold text-white hover:bg-emerald-700 transition shadow-2xs">
                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                <span>Return to Invoices Report</span>
            </a>
            <a href="{{ route('admin.cashbook.reports.shops.credit-notes') }}"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-2xs">
                <i data-lucide="file-text" class="h-4 w-4 text-slate-400"></i>
                <span>View Credit Notes</span>
            </a>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const hasExpired = @json($hasExpiredToken ?? false);
        if (hasExpired) {
            try {
                sessionStorage.removeItem('credit_note_selection_token');
            } catch (e) {}
            return;
        }

        try {
            const token = sessionStorage.getItem('credit_note_selection_token');
            if (token && typeof token === 'string' && token.trim().length > 0) {
                const currentUrl = new URL(window.location.href);
                if (currentUrl.searchParams.get('selection_token') !== token) {
                    currentUrl.searchParams.set('selection_token', token);
                    window.location.replace(currentUrl.toString());
                }
            }
        } catch (e) {}
    });
</script>
@endsection

