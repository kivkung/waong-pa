<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · Waongpa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            background: #f5f7fb;
            color: #21392e;
            font-family: 'Kanit', sans-serif;
        }

        .hero {
            background: #e6f2eb;
        }

        .card {
            border-color: #e0e7e3;
        }

        .room-description {
            white-space: pre-line;
            overflow-wrap: anywhere;
        }

        .card-title,
        .account-name {
            overflow-wrap: anywhere;
        }

        .form-page {
            max-width: 680px;
        }

        .transition-card {
            transition: box-shadow 0.5s ease, transform 0.3s ease;
        }

        .round-card {
            position: relative;
            z-index: 1;
            background: transparent !important;
            /* ให้แบคกราวด์ไปอยู่ที่ ::before แทน */
        }

        /* สร้างกรอบ/พื้นหลังซ้อนไว้ข้างหลัง */
        .round-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: #fff;
            /* ใส่สีพื้นหลังของการ์ด */
            border-top: 1px solid rgba(0, 0, 0, .125);
            border-bottom: 1px solid rgba(0, 0, 0, .125);
            /* ใส่ขอบเดิมของการ์ด (ถ้ามี) */
            border-radius: 0;
            /* ให้ความโค้งมนเท่าการ์ดหลัก */
            z-index: -1;
            transition: box-shadow 0.2s ease;
        }

        /* เมื่อ Hover ให้ขยายเฉพาะกรอบด้านหลัง */
        .round-card:hover::before,
        .round-card:focus-within::before {
            box-shadow: var(--bs-box-shadow);
            transform: scaleX(1.02);
        }

        .room-card:hover,
        .room-card:focus-within {
            box-shadow: var(--bs-box-shadow);
            transform: rotate(2deg);
        }

        .pagination {
            --bs-pagination-color: #198754;
            --bs-pagination-hover-color: #146c43;
            --bs-pagination-hover-bg: #d1e7dd;
            --bs-pagination-active-bg: #198754;
            --bs-pagination-active-border-color: #198754;
            --bs-pagination-focus-box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
        }
    </style>
    @stack('styles')
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