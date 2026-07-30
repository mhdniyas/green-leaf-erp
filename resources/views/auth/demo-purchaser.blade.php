<x-layouts.auth title="Purchaser Login">
    <div class="min-h-screen bg-[radial-gradient(circle_at_top,rgba(22,163,74,0.18),transparent_40%),linear-gradient(180deg,#f0faf2_0%,#e8f5eb_100%)]">
        <div class="mx-auto flex min-h-screen w-full max-w-sm flex-col justify-center px-4 py-10">

            {{-- Header --}}
            <div class="mb-8 text-center">
                <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-white px-4 py-1.5 text-[11px] font-black uppercase tracking-[0.2em] text-emerald-700 shadow-sm">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    Green Leaf
                </span>
                <h1 class="mt-4 text-3xl font-black tracking-tight text-slate-950">Who are you?</h1>
                <p class="mt-2 text-sm font-medium text-slate-500">Tap your name to sign in.</p>
            </div>

            {{-- Error --}}
            @if ($errors->any())
                <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-center">
                    @foreach ($errors->all() as $error)
                        <p class="text-sm font-bold text-red-700">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- Purchaser cards --}}
            @if ($purchasers->isEmpty())
                <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center shadow-sm">
                    <p class="text-sm font-bold text-slate-700">No purchaser accounts found.</p>
                    <p class="mt-2 text-xs text-slate-400">Run EssentialUserSeeder first.</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($purchasers as $purchaser)
                        <form method="POST" action="{{ route('login.demo', $purchaser) }}">
                            @csrf
                            <button
                                type="submit"
                                class="group flex w-full items-center gap-4 rounded-3xl border border-slate-200 bg-white px-5 py-4 text-left shadow-sm transition active:scale-[0.98] hover:border-emerald-300 hover:bg-emerald-50 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-400/30"
                            >
                                {{-- Avatar --}}
                                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-xl font-black text-emerald-700 group-hover:bg-emerald-200">
                                    {{ strtoupper(substr($purchaser->name, 0, 1)) }}
                                </span>

                                {{-- Info --}}
                                <span class="min-w-0 flex-1">
                                    <span class="block text-lg font-black text-slate-950">{{ $purchaser->name }}</span>
                                    <span class="block truncate text-xs font-semibold text-slate-400">{{ $purchaser->email }}</span>
                                </span>

                                {{-- Arrow --}}
                                <svg class="h-5 w-5 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </button>
                        </form>
                    @endforeach
                </div>
            @endif

            {{-- Footer link --}}
            <div class="mt-8 text-center">
                <a href="{{ route('login') }}" class="text-sm font-semibold text-slate-400 transition hover:text-slate-700">
                    Use password instead →
                </a>
            </div>

        </div>
    </div>
</x-layouts.auth>
