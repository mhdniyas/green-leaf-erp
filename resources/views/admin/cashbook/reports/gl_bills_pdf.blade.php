<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ $startDate }} to {{ $endDate }}</title>
    @vite(['resources/css/app.css'])
    <style>
        .filter-card { border: 1px solid #cbd5e1; border-radius: 12px; display: grid; gap: 12px; grid-template-columns: repeat(3, minmax(0, 1fr)); margin-top: 12px; padding: 12px; }
        .filter-label { color: #64748b; display: block; font-size: 10px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
        .shop-spacer td { border-bottom: 0; height: 16px; padding: 0; }
        .shop-heading td { background: #ecfdf5; border: 1px solid #86efac; color: #064e3b; font-size: 13px; }
        .shop-heading span { color: #047857; float: right; font-size: 11px; }
    </style>
</head>
<body class="bg-white p-8 text-slate-900">
    @include('admin.cashbook.reports.gl_bills_pdf_content')
    <script>
        window.addEventListener('load', () => window.print());
    </script>
</body>
</html>
