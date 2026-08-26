<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 8px; text-align: left; }
        th { background: #f8fafc; font-size: 10px; text-transform: uppercase; }
        .right { text-align: right; }
        .muted { color: #64748b; }
        .filter-card { border: 1px solid #cbd5e1; margin-top: 10px; padding: 10px; }
        .filter-card div { display: inline-block; width: 32%; vertical-align: top; }
        .filter-label { color: #64748b; display: block; font-size: 9px; font-weight: bold; letter-spacing: .04em; text-transform: uppercase; }
        .summary { margin: 18px 0; }
        .summary td { border: 1px solid #e2e8f0; }
        .shop-spacer td { border-bottom: 0; height: 14px; padding: 0; }
        .shop-heading td { background: #ecfdf5; border: 1px solid #86efac; color: #064e3b; font-size: 12px; }
        .shop-heading span { color: #047857; float: right; font-size: 10px; }
    </style>
</head>
<body>
    @include('admin.cashbook.reports.gl_bills_pdf_content')
</body>
</html>
