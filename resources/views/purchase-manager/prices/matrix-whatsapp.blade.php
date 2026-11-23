<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WhatsApp Export - Selling Price Matrix - {{ \Illuminate\Support\Carbon::parse($targetBusinessDate)->format('d M Y') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: #0b141a;
            color: #e9edef;
            min-height: 100vh;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .container {
            width: 100%;
            max-width: 820px;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .header-card {
            background: #111b21;
            border: 1px solid #202c33;
            border-radius: 1rem;
            padding: 1.25rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 0.75rem;
        }
        .title-wrap {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .wa-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.75rem;
            background: #00a884;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .title-text h1 {
            font-size: 1.125rem;
            font-weight: 800;
            color: #e9edef;
            line-height: 1.2;
        }
        .title-text p {
            font-size: 0.75rem;
            color: #8696a0;
            margin-top: 0.2rem;
        }
        .badge-strip {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 700;
            background: #202c33;
            color: #d1d7db;
            border: 1px solid #2a3942;
        }
        .badge.accent {
            background: rgba(0, 168, 132, 0.15);
            color: #00a884;
            border-color: rgba(0, 168, 132, 0.3);
        }
        .badge.warning {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border-color: rgba(245, 158, 11, 0.3);
        }
        .action-card {
            background: #111b21;
            border: 1px solid #202c33;
            border-radius: 1rem;
            padding: 1rem 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.875rem;
        }
        .action-grid {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            height: 2.375rem;
            padding: 0 1rem;
            border-radius: 0.625rem;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 120ms ease;
            text-decoration: none;
            touch-action: manipulation;
            border: 1px solid transparent;
            white-space: nowrap;
        }
        .btn:active {
            transform: scale(0.97);
        }
        .btn-primary {
            background: #00a884;
            color: #111b21;
        }
        .btn-primary:hover {
            background: #06cf9c;
        }
        .btn-secondary {
            background: #202c33;
            color: #e9edef;
            border-color: #2a3942;
        }
        .btn-secondary:hover {
            background: #2a3942;
        }
        .btn-ghost {
            background: transparent;
            color: #8696a0;
            border-color: #2a3942;
        }
        .btn-ghost:hover {
            background: #202c33;
            color: #e9edef;
        }
        .filter-tabs {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem;
            background: #0c1317;
            border-radius: 0.5rem;
            border: 1px solid #202c33;
            width: fit-content;
        }
        .tab-btn {
            height: 1.75rem;
            padding: 0 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.6875rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 120ms ease;
            color: #8696a0;
            background: transparent;
        }
        .tab-btn.active {
            background: #202c33;
            color: #00a884;
        }
        .preview-card {
            background: #111b21;
            border: 1px solid #202c33;
            border-radius: 1rem;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .preview-header {
            background: #182229;
            padding: 0.75rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #202c33;
            font-size: 0.6875rem;
            color: #8696a0;
            font-weight: 600;
        }
        .preview-content {
            padding: 1.25rem;
            background: #0b141a;
            max-height: 480px;
            overflow-y: auto;
            position: relative;
        }
        .wa-bubble {
            background: #005c4b;
            color: #e9edef;
            padding: 1rem 1.125rem;
            border-radius: 0.75rem 0.75rem 0.75rem 0.25rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-word;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
            user-select: text;
        }
        .toast {
            position: fixed;
            bottom: 1.5rem;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #00a884;
            color: #111b21;
            font-weight: 800;
            font-size: 0.8125rem;
            padding: 0.625rem 1.25rem;
            border-radius: 9999px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
            transition: transform 200ms cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 999;
            pointer-events: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .toast.show {
            transform: translateX(-50%) translateY(0);
        }
        .notice {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.25);
            color: #fbbf24;
            border-radius: 0.625rem;
            padding: 0.625rem 0.875rem;
            font-size: 0.6875rem;
            line-height: 1.4;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        {{-- Header card --}}
        <div class="header-card">
            <div class="top-bar">
                <div class="title-wrap">
                    <div class="wa-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                    </div>
                    <div class="title-text">
                        <h1>Selling Price Matrix — WhatsApp</h1>
                        <p>{{ $companyName }} · {{ \Illuminate\Support\Carbon::parse($targetBusinessDate)->format('d M Y') }}</p>
                    </div>
                </div>
                <a href="{{ route('purchasing.prices.matrix.index', ['date' => $targetBusinessDate, 'matrix_category' => $matrixCategory, 'quality_grade' => $qualityGrade ?? 'A']) }}" class="btn btn-ghost">
                    ← Back to Matrix
                </a>
            </div>

            <div class="badge-strip">
                <span class="badge accent">Price Group {{ strtoupper($matrixCategory) }}</span>
                <span class="badge accent">Grade {{ $qualityGrade ?? 'A' }}</span>
                <span class="badge">{{ match($exportScope) { 'today' => 'Today Scope', 'today_changed' => 'Today Changed Only', default => 'Full Week Scope' } }}</span>
                <span class="badge" id="badge-count">{{ $totalItemsCount }} items</span>
            </div>
        </div>

        {{-- Action & Controls card --}}
        <div class="action-card">
            @if ($totalItemsCount > 100)
                <div class="notice">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0; margin-top:2px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><strong>Large Catalog ({{ $totalItemsCount }} items):</strong> Web browsers and WhatsApp API limit single URL query strings to 2,000 characters. Use <strong>"Copy to Clipboard"</strong> below to copy the complete matrix and paste directly into any WhatsApp chat or group.</span>
                </div>
            @endif

            <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;">
                {{-- Filter toggle --}}
                @if ($pricedItemsCount > 0 && $pricedItemsCount < $totalItemsCount)
                    <div class="filter-tabs">
                        <button type="button" class="tab-btn active" id="tab-all" onclick="switchView('all')">
                            All Items ({{ $totalItemsCount }})
                        </button>
                        <button type="button" class="tab-btn" id="tab-priced" onclick="switchView('priced')">
                            Priced Only ({{ $pricedItemsCount }})
                        </button>
                    </div>
                @else
                    <div></div>
                @endif

                {{-- Action buttons --}}
                <div class="action-grid">
                    <button type="button" class="btn btn-primary" onclick="copyActiveText()">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                        Copy All Text
                    </button>

                    <button type="button" class="btn btn-secondary" onclick="openWhatsApp()">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        Open WhatsApp
                    </button>

                    <button type="button" class="btn btn-ghost" onclick="downloadTxt()">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                        .txt
                    </button>
                </div>
            </div>
        </div>

        {{-- Preview card --}}
        <div class="preview-card">
            <div class="preview-header">
                <span>Message Preview</span>
                <span id="char-count">{{ strlen($shareText) }} characters</span>
            </div>
            <div class="preview-content">
                <div class="wa-bubble" id="wa-text">{{ $shareText }}</div>
            </div>
        </div>
    </div>

    {{-- Toast feedback --}}
    <div class="toast" id="toast">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
        </svg>
        <span id="toast-msg">Copied to clipboard!</span>
    </div>

    <script>
        const fullText = @json($shareText);
        const pricedText = @json($pricedShareText ?? $shareText);
        let activeText = fullText;
        const totalCount = {{ (int) $totalItemsCount }};
        const pricedCount = {{ (int) $pricedItemsCount }};

        function showToast(msg) {
            const toast = document.getElementById('toast');
            const msgEl = document.getElementById('toast-msg');
            msgEl.textContent = msg;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2800);
        }

        function switchView(mode) {
            const waEl = document.getElementById('wa-text');
            const countEl = document.getElementById('char-count');
            const badgeCount = document.getElementById('badge-count');
            const tabAll = document.getElementById('tab-all');
            const tabPriced = document.getElementById('tab-priced');

            if (mode === 'priced') {
                activeText = pricedText;
                if (tabAll) tabAll.classList.remove('active');
                if (tabPriced) tabPriced.classList.add('active');
                if (badgeCount) badgeCount.textContent = pricedCount + ' items';
            } else {
                activeText = fullText;
                if (tabAll) tabAll.classList.add('active');
                if (tabPriced) tabPriced.classList.remove('active');
                if (badgeCount) badgeCount.textContent = totalCount + ' items';
            }

            waEl.textContent = activeText;
            countEl.textContent = activeText.length + ' characters';
        }

        async function copyActiveText() {
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(activeText);
                } else {
                    const textArea = document.createElement('textarea');
                    textArea.value = activeText;
                    textArea.style.position = 'fixed';
                    textArea.style.left = '-9999px';
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    document.execCommand('copy');
                    textArea.remove();
                }
                showToast('Copied to clipboard! Ready to paste into WhatsApp.');
            } catch (err) {
                console.error('Clipboard copy failed:', err);
                showToast('Failed to copy. Please select and copy text manually.');
            }
        }

        async function openWhatsApp() {
            // First copy text to clipboard for user convenience
            await copyActiveText();

            // Try Web Share on mobile if supported
            if (navigator.share && /mobile|android|iphone|ipad/i.test(navigator.userAgent)) {
                try {
                    await navigator.share({
                        title: 'Selling Price Matrix',
                        text: activeText,
                    });
                    return;
                } catch (e) {
                    if (e.name === 'AbortError') return;
                }
            }

            // For desktop or if text is small, try wa.me link
            if (activeText.length <= 1800) {
                window.open('https://api.whatsapp.com/send?text=' + encodeURIComponent(activeText), '_blank', 'noopener,noreferrer');
            } else {
                showToast('Text copied! Opening WhatsApp Web — press Paste (Ctrl+V) in chat.');
                setTimeout(() => {
                    window.open('https://web.whatsapp.com/', '_blank', 'noopener,noreferrer');
                }, 400);
            }
        }

        function downloadTxt() {
            const blob = new Blob([activeText], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'selling-price-matrix-{{ $exportScope }}-{{ $targetBusinessDate }}.txt';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
            showToast('Downloaded as .txt file');
        }
    </script>
</body>
</html>
