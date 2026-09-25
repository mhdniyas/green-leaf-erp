@extends('admin.cashbook.layouts.app')

@section('title', $currentShop->name.' - Payments Settings')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    {{-- Main Cashbook Settings Navigation --}}
    @include('admin.cashbook.settings.partials.tabs', [
        'activeTab' => 'payments',
        'shopKey' => $shopKey,
        'currentShop' => $currentShop
    ])

    {{-- Page Header --}}
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex items-center gap-4">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-100 text-violet-800 border border-violet-200 shadow-2xs shrink-0">
                <i data-lucide="wallet" class="h-6 w-6"></i>
            </span>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-black uppercase tracking-widest text-violet-700">Financial Hub</span>
                    <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-700 border border-emerald-200">9 Core Bridges</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-950 tracking-tight mt-0.5">PAYMENTS SETTINGS</h1>
                <p class="text-xs text-slate-500 font-medium">Shop ↔ Company ↔ Bank ↔ Petty ↔ Settlement ↔ Allocation ↔ Reports</p>
            </div>
        </div>

        {{-- Month Selector Form --}}
        <form method="GET" action="{{ route('admin.cashbook.settings.shop.payments.index', $shopKey) }}" class="flex items-center gap-2 bg-slate-50 border border-slate-200 p-1.5 rounded-2xl">
            <input type="hidden" name="tab" id="active-tab-param" value="{{ request('tab', 'overview') }}">
            <span class="text-xs font-bold text-slate-500 pl-2">Period:</span>
            <input type="month" name="month" value="{{ $month }}" onchange="this.form.submit()"
                class="text-xs font-bold bg-white border border-slate-300 rounded-xl px-2.5 py-1 text-slate-800 focus:ring-violet-500">
        </form>
    </div>

    {{-- 9 Section Navigation Tabs --}}
    <div class="border-b border-slate-200 bg-white rounded-2xl p-1.5 shadow-2xs">
        <nav class="flex items-center gap-1 overflow-x-auto text-xs font-bold scrollbar-none" id="payments-tabs-nav">
            <button type="button" onclick="switchPaymentsTab('overview')" data-tab="overview"
                class="payments-tab-btn px-3 py-2 rounded-xl transition cursor-pointer shrink-0 flex items-center gap-1.5">
                <i data-lucide="layout-dashboard" class="h-3.5 w-3.5"></i>
                <span>Overview</span>
            </button>
            <button type="button" onclick="switchPaymentsTab('company-collections')" data-tab="company-collections"
                class="payments-tab-btn px-3 py-2 rounded-xl transition cursor-pointer shrink-0 flex items-center gap-1.5">
                <i data-lucide="building-2" class="h-3.5 w-3.5"></i>
                <span>Company Collections</span>
            </button>
            <button type="button" onclick="switchPaymentsTab('shop-to-company')" data-tab="shop-to-company"
                class="payments-tab-btn px-3 py-2 rounded-xl transition cursor-pointer shrink-0 flex items-center gap-1.5">
                <i data-lucide="arrow-up-right" class="h-3.5 w-3.5"></i>
                <span>Shop → Company</span>
            </button>
            <button type="button" onclick="switchPaymentsTab('company-to-shop')" data-tab="company-to-shop"
                class="payments-tab-btn px-3 py-2 rounded-xl transition cursor-pointer shrink-0 flex items-center gap-1.5">
                <i data-lucide="arrow-down-left" class="h-3.5 w-3.5"></i>
                <span>Company → Shop</span>
            </button>
            <button type="button" onclick="switchPaymentsTab('petty')" data-tab="petty"
                class="payments-tab-btn px-3 py-2 rounded-xl transition cursor-pointer shrink-0 flex items-center gap-1.5">
                <i data-lucide="coins" class="h-3.5 w-3.5"></i>
                <span>Petty</span>
            </button>
            <button type="button" onclick="switchPaymentsTab('settlement')" data-tab="settlement"
                class="payments-tab-btn px-3 py-2 rounded-xl transition cursor-pointer shrink-0 flex items-center gap-1.5">
                <i data-lucide="scale" class="h-3.5 w-3.5"></i>
                <span>Settlement</span>
            </button>
            <button type="button" onclick="switchPaymentsTab('allocation')" data-tab="allocation"
                class="payments-tab-btn px-3 py-2 rounded-xl transition cursor-pointer shrink-0 flex items-center gap-1.5">
                <i data-lucide="split" class="h-3.5 w-3.5"></i>
                <span>Allocation</span>
            </button>
            <button type="button" onclick="switchPaymentsTab('reports')" data-tab="reports"
                class="payments-tab-btn px-3 py-2 rounded-xl transition cursor-pointer shrink-0 flex items-center gap-1.5">
                <i data-lucide="file-text" class="h-3.5 w-3.5"></i>
                <span>Reports</span>
            </button>
            <button type="button" onclick="switchPaymentsTab('advanced')" data-tab="advanced"
                class="payments-tab-btn px-3 py-2 rounded-xl transition cursor-pointer shrink-0 flex items-center gap-1.5">
                <i data-lucide="sliders-horizontal" class="h-3.5 w-3.5"></i>
                <span>Advanced</span>
            </button>
        </nav>
    </div>

    {{-- Section Tab Panels --}}
    <div class="tab-panels space-y-6">
        {{-- 1. Overview --}}
        <div id="panel-overview" class="payments-panel hidden">
            @include('admin.cashbook.settings.payments.partials.overview')
        </div>

        {{-- 2. Company Collections --}}
        <div id="panel-company-collections" class="payments-panel hidden">
            @include('admin.cashbook.settings.payments.partials.company-collections')
        </div>

        {{-- 3. Shop to Company --}}
        <div id="panel-shop-to-company" class="payments-panel hidden">
            @include('admin.cashbook.settings.payments.partials.shop-to-company')
        </div>

        {{-- 4. Company to Shop --}}
        <div id="panel-company-to-shop" class="payments-panel hidden">
            @include('admin.cashbook.settings.payments.partials.company-to-shop')
        </div>

        {{-- 5. Petty --}}
        <div id="panel-petty" class="payments-panel hidden">
            @include('admin.cashbook.settings.payments.partials.petty')
        </div>

        {{-- 6. Settlement --}}
        <div id="panel-settlement" class="payments-panel hidden">
            @include('admin.cashbook.settings.payments.partials.settlement')
        </div>

        {{-- 7. Allocation --}}
        <div id="panel-allocation" class="payments-panel hidden">
            @include('admin.cashbook.settings.payments.partials.allocation')
        </div>

        {{-- 8. Reports --}}
        <div id="panel-reports" class="payments-panel hidden">
            @include('admin.cashbook.settings.payments.partials.reports')
        </div>

        {{-- 9. Advanced --}}
        <div id="panel-advanced" class="payments-panel hidden">
            @include('admin.cashbook.settings.payments.partials.advanced')
        </div>
    </div>
</div>

{{-- Toast Container --}}
<div id="payment-toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 pointer-events-none max-w-sm w-full"></div>

<script>
// ==========================================
// 1. TAB SWITCHING ENGINE
// ==========================================
function switchPaymentsTab(tabName) {
    const validTabs = [
        'overview',
        'company-collections',
        'shop-to-company',
        'company-to-shop',
        'petty',
        'settlement',
        'allocation',
        'reports',
        'advanced'
    ];

    if (!validTabs.includes(tabName)) {
        tabName = 'overview';
    }

    // Update Tab Buttons UI
    document.querySelectorAll('.payments-tab-btn').forEach(btn => {
        if (btn.getAttribute('data-tab') === tabName) {
            btn.className = 'payments-tab-btn px-3.5 py-2 rounded-xl transition cursor-pointer shrink-0 flex items-center gap-1.5 bg-violet-700 text-white shadow-xs font-black';
        } else {
            btn.className = 'payments-tab-btn px-3.5 py-2 rounded-xl transition cursor-pointer shrink-0 flex items-center gap-1.5 text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-bold';
        }
    });

    // Toggle Panels
    document.querySelectorAll('.payments-panel').forEach(panel => {
        panel.classList.add('hidden');
    });

    const activePanel = document.getElementById('panel-' + tabName);
    if (activePanel) {
        activePanel.classList.remove('hidden');
    }

    // Update URL hash without scroll
    if (history.pushState) {
        history.pushState(null, null, '#' + tabName);
    } else {
        location.hash = '#' + tabName;
    }

    const activeInput = document.getElementById('active-tab-param');
    if (activeInput) {
        activeInput.value = tabName;
    }

    if (window.lucide) {
        window.lucide.createIcons();
    }
}

// ==========================================
// 2. REUSABLE VANILLA JS MODAL SYSTEM
// ==========================================
let activeModalId = null;
let lastFocusedElement = null;

function openPaymentModal(modalId) {
    const modal = document.querySelector(`[data-payment-modal="${modalId}"]`);
    if (!modal) return;

    // Modals are declared inside tab panels. Move the requested modal out of a hidden panel before opening it.
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    lastFocusedElement = document.activeElement;
    activeModalId = modalId;

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');

    // Trigger smooth enter transitions
    requestAnimationFrame(() => {
        modal.classList.remove('opacity-0');
        modal.classList.add('opacity-100');

        const panel = modal.querySelector('.payment-modal-panel');
        if (panel) {
            panel.classList.remove('scale-95', 'opacity-0');
            panel.classList.add('scale-100', 'opacity-100');
        }
    });

    // Body scroll lock
    document.body.style.overflow = 'hidden';

    // Focus first input or button inside modal
    setTimeout(() => {
        const focusTarget = modal.querySelector('input:not([type="hidden"]), button, select');
        if (focusTarget) focusTarget.focus();
    }, 100);

    if (window.lucide) window.lucide.createIcons();
}

function closePaymentModal(modalId) {
    const targetId = modalId || activeModalId;
    if (!targetId) return;

    const modal = document.querySelector(`[data-payment-modal="${targetId}"]`);
    if (!modal) return;

    modal.classList.remove('opacity-100');
    modal.classList.add('opacity-0');

    const panel = modal.querySelector('.payment-modal-panel');
    if (panel) {
        panel.classList.remove('scale-100', 'opacity-100');
        panel.classList.add('scale-95', 'opacity-0');
    }

    setTimeout(() => {
        modal.classList.remove('flex');
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (activeModalId === targetId) activeModalId = null;

        if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
            lastFocusedElement.focus();
        }
    }, 200);
}

// Global modal click & escape handlers
document.addEventListener('click', (e) => {
    // Open trigger
    const openBtn = e.target.closest('[data-payment-modal-open]');
    if (openBtn) {
        e.preventDefault();
        const modalId = openBtn.getAttribute('data-payment-modal-open');
        openPaymentModal(modalId);
        return;
    }

    // Close trigger
    const closeBtn = e.target.closest('[data-payment-modal-close]');
    if (closeBtn) {
        e.preventDefault();
        const modal = closeBtn.closest('[data-payment-modal]');
        if (modal) {
            closePaymentModal(modal.getAttribute('data-payment-modal'));
        }
        return;
    }

    // Backdrop click
    if (e.target.classList.contains('payment-modal-backdrop')) {
        closePaymentModal(e.target.getAttribute('data-payment-modal'));
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        // If a custom-select dropdown is open, close that first
        const openMenu = document.querySelector('.custom-select-menu:not(.hidden)');
        if (openMenu) {
            closeAllCustomSelects();
            return;
        }

        if (activeModalId) {
            closePaymentModal(activeModalId);
        }
    }
});

// ==========================================
// 3. REUSABLE CUSTOM TAILWIND SELECT ENGINE
// ==========================================
function closeAllCustomSelects() {
    document.querySelectorAll('[data-custom-select]').forEach(wrapper => {
        const menu = wrapper.querySelector('.custom-select-menu');
        const chevron = wrapper.querySelector('.custom-select-chevron');
        const trigger = wrapper.querySelector('.custom-select-trigger');
        if (menu && !menu.classList.contains('hidden')) {
            menu.classList.remove('opacity-100', 'scale-100');
            menu.classList.add('opacity-0', 'scale-95');
            setTimeout(() => menu.classList.add('hidden'), 150);
            if (chevron) chevron.classList.remove('rotate-180');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        }
    });
}

document.addEventListener('click', (e) => {
    const trigger = e.target.closest('.custom-select-trigger');
    const option = e.target.closest('.custom-select-option');
    const isInsideSelect = e.target.closest('[data-custom-select]');

    // Toggle custom select menu
    if (trigger) {
        e.preventDefault();
        const wrapper = trigger.closest('[data-custom-select]');
        const menu = wrapper.querySelector('.custom-select-menu');
        const chevron = wrapper.querySelector('.custom-select-chevron');
        const isCurrentlyOpen = menu && !menu.classList.contains('hidden');

        closeAllCustomSelects();

        if (!isCurrentlyOpen && menu) {
            menu.classList.remove('hidden');
            requestAnimationFrame(() => {
                menu.classList.remove('opacity-0', 'scale-95');
                menu.classList.add('opacity-100', 'scale-100');
            });
            if (chevron) chevron.classList.add('rotate-180');
            trigger.setAttribute('aria-expanded', 'true');

            // Focus search input if present
            const searchInput = menu.querySelector('.custom-select-search');
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
                // Reset hidden options
                menu.querySelectorAll('.custom-select-option').forEach(opt => opt.classList.remove('hidden'));
            }
        }
        return;
    }

    // Option selected
    if (option) {
        e.preventDefault();
        if (option.getAttribute('data-disabled') === 'true') return;

        const wrapper = option.closest('[data-custom-select]');
        const input = wrapper.querySelector('.custom-select-input') || wrapper.querySelector('input[type="hidden"]');
        const labelSpan = wrapper.querySelector('.custom-select-label');
        const val = option.getAttribute('data-value') || '';
        const label = option.getAttribute('data-label') || '';

        // Update hidden input
        if (input) {
            input.value = val;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }

        // Update trigger label
        if (labelSpan) {
            labelSpan.textContent = label;
            if (val === '') {
                labelSpan.className = 'custom-select-label truncate text-slate-400 font-normal';
            } else {
                labelSpan.className = 'custom-select-label truncate text-slate-900 font-bold';
            }
        }

        // Update selected checkmark UI inside menu
        wrapper.querySelectorAll('.custom-select-option').forEach(opt => {
            const isMatch = (opt.getAttribute('data-value') || '') === val;
            if (isMatch) {
                opt.className = 'custom-select-option flex items-center justify-between rounded-xl px-3 py-2 text-xs bg-violet-50 text-violet-800 font-black cursor-pointer';
                if (!opt.querySelector('.custom-option-check')) {
                    const check = document.createElement('i');
                    check.setAttribute('data-lucide', 'check');
                    check.className = 'custom-option-check h-3.5 w-3.5 text-violet-700 shrink-0';
                    opt.appendChild(check);
                }
            } else {
                opt.className = 'custom-select-option flex items-center justify-between rounded-xl px-3 py-2 text-xs hover:bg-violet-50/80 hover:text-violet-900 text-slate-800 font-semibold cursor-pointer';
                const check = opt.querySelector('.custom-option-check');
                if (check) check.remove();
            }
        });

        closeAllCustomSelects();
        if (window.lucide) window.lucide.createIcons();
        return;
    }

    // Clicked outside any select -> close all
    if (!isInsideSelect) {
        closeAllCustomSelects();
    }
});

// Search filtering
document.addEventListener('input', (e) => {
    if (e.target.classList.contains('custom-select-search')) {
        const query = e.target.value.toLowerCase().trim();
        const menu = e.target.closest('.custom-select-menu');
        const options = menu.querySelectorAll('.custom-select-option');
        const noResults = menu.querySelector('.custom-select-no-results');
        let visibleCount = 0;

        options.forEach(opt => {
            const text = (opt.textContent || '').toLowerCase();
            if (text.includes(query)) {
                opt.classList.remove('hidden');
                visibleCount++;
            } else {
                opt.classList.add('hidden');
            }
        });

        if (noResults) {
            if (visibleCount === 0) {
                noResults.classList.remove('hidden');
            } else {
                noResults.classList.add('hidden');
            }
        }
    }
});

// ==========================================
// 4. REUSABLE TAILWIND TOAST NOTIFICATION
// ==========================================
window.showPaymentToast = function(message, type = 'success') {
    const container = document.getElementById('payment-toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    const isSuccess = type === 'success';

    toast.className = `pointer-events-auto flex items-center gap-3 rounded-2xl p-4 shadow-2xl border transition-all duration-300 transform translate-y-5 opacity-0 ${
        isSuccess ? 'bg-slate-900 text-white border-slate-800' : 'bg-rose-900 text-white border-rose-800'
    }`;

    toast.innerHTML = `
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl ${isSuccess ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-300'}">
            <i data-lucide="${isSuccess ? 'check-circle' : 'alert-circle'}" class="h-4 w-4"></i>
        </div>
        <div class="flex-1 text-xs font-bold">${message}</div>
        <button type="button" class="text-slate-400 hover:text-white p-1" onclick="this.closest('.pointer-events-auto').remove()">
            <i data-lucide="x" class="h-3.5 w-3.5"></i>
        </button>
    `;

    container.appendChild(toast);
    if (window.lucide) window.lucide.createIcons();

    // Slide in
    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-5', 'opacity-0');
        toast.classList.add('translate-y-0', 'opacity-100');
    });

    // Auto-remove after 3.5s
    setTimeout(() => {
        toast.classList.remove('translate-y-0', 'opacity-100');
        toast.classList.add('translate-y-5', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 3500);
};

// ==========================================
// 5. INITIALIZATION ON DOM READY
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
    // Resolve initial tab from URL hash or query param or default to overview
    let initialTab = 'overview';
    if (window.location.hash) {
        initialTab = window.location.hash.replace('#', '');
    } else {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('tab')) {
            initialTab = urlParams.get('tab');
        }
    }
    switchPaymentsTab(initialTab);
});
</script>
@endsection
