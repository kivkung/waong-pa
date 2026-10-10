<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'หน้าหลัก') · Waongpa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #f5f7fb; color: #21392e; font-family: 'Kanit', sans-serif; }
        .hero { background: #e6f2eb; }
        .card { border-color: #e0e7e3; }
        .room-description { white-space: pre-line; overflow-wrap: anywhere; }
        .card-title, .account-name { overflow-wrap: anywhere; }
        .form-page { max-width: 680px; }
        .round-card { position: relative; z-index: 1; background: transparent !important; }
        .round-card::before { content: ''; position: absolute; inset: 0; background: #fff; border-top: 1px solid rgba(0,0,0,.125); border-bottom: 1px solid rgba(0,0,0,.125); border-radius: 0; z-index: -1; }
        .round-card:hover::before, .round-card:focus-within::before { box-shadow: var(--bs-box-shadow); }
        .room-card:hover, .room-card:focus-within { box-shadow: var(--bs-box-shadow); }
        .pagination { --bs-pagination-color:#198754; --bs-pagination-hover-color:#146c43; --bs-pagination-hover-bg:#d1e7dd; --bs-pagination-active-bg:#198754; --bs-pagination-active-border-color:#198754; --bs-pagination-focus-box-shadow:0 0 0 .25rem rgba(25,135,84,.25); }
    </style>
    {{-- Global UI presentation layer. Business logic stays in existing controllers/routes. --}}
    <link rel="stylesheet" href="{{ asset('css/waongpa-theme.css') }}">
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/no-motion.css') }}">
</head>
<body class="d-flex flex-column min-vh-100">
    @include('partials.header')
    <main class="container py-4 py-md-5 flex-grow-1">
        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-warning">{{ session('error') }}</div>
        @endif
        @yield('content')
    </main>
    <footer class="container border-top py-4 text-secondary small">Waongpa · หาช่วงเวลาที่ลงตัว แล้วนัดกัน</footer>
    @stack('scripts')
</body>
</html>
