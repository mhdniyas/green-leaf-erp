<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ $startDate }} to {{ $endDate }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white p-8 text-slate-900">
    @include('admin.cashbook.reports.gl_bills_pdf_content')
    <script>
        window.addEventListener('load', () => window.print());
    </script>
</body>
</html>
