<header class="bg-white border-bottom">
    <nav class="navbar navbar-expand-md py-3" aria-label="เมนูหลัก">
        <div class="container">
            <a class="navbar-brand fw-bold text-success" href="{{ route('home') }}">Waongpa</a>
            <div class="d-flex flex-wrap align-items-center gap-3 flex-grow-1">
                <div class="navbar-nav flex-row flex-wrap gap-3 me-auto">
                    <a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a>
                    @auth
                        <a class="nav-link" href="{{ route('rooms.create') }}">สร้างห้อง</a>
                        <a class="nav-link" href="{{ route('rooms.join') }}">เข้าร่วมห้อง</a>
                    @endauth
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-3 mt-md-0">
                    @auth
                        <span class="small text-secondary account-name">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm">ออกจากระบบ</button>
                        </form>
                    @else
                        <a class="btn btn-outline-success" href="{{ route('login') }}">เข้าสู่ระบบ</a>
                        @if (Route::has('register'))
                            <a class="btn btn-success" href="{{ route('register') }}">สมัครสมาชิก</a>
                        @endif
                    @endauth
                </div>
            </div>
        </div>
    </nav>
</header>
