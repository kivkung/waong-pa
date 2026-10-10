@extends('layouts.rooms')
@section('title', 'Dashboard')
@section('content')
    <section class="hero rounded-4 p-4 p-md-5 mb-5">
        <div class="row align-items-center g-4">
            <div class="col-md-7">
            <p class="text-success fw-semibold">WAONGPA / ROOMS</p>
            <h1 class="display-6 fw-bold">นัดหมายให้ลงตัว เริ่มจากห้องของคุณ</h1>
            <p class="text-secondary mb-4">ดูห้องสาธารณะได้ทันที หรือเข้าสู่ระบบเพื่อสร้างและเข้าร่วมห้อง</p>
            @auth
                <a class="btn btn-success" href="{{ route('rooms.create') }}">สร้างห้องใหม่</a>
                <a class="btn btn-outline-success" href="{{ route('rooms.join') }}">เข้าร่วมด้วยรหัส</a>
            @else
                <a class="btn btn-success" href="{{ route('login') }}">เข้าสู่ระบบ</a>
            @endauth
            </div>
            <div class="col-md-5 text-center">
                @include('partials.lecture-chair')
            </div>
        </div>
    </section>

    @auth
        <section class="mb-5" aria-labelledby="my-rooms-heading">
            <h2 id="my-rooms-heading" class="h4 mb-3">ห้องของฉัน</h2>
            <div class="row g-3">
                @forelse ($myRooms as $room)
                    @include('partials.room-card', ['room' => $room])
                @empty
                    <p class="text-secondary">คุณยังไม่ได้เข้าร่วมห้อง ลองสร้างห้องหรือขอรหัสจากเจ้าของห้อง</p>
                @endforelse
            </div>
            <div class="mt-3">{{ $myRooms->links('pagination::bootstrap-5') }}</div>
        </section>
    @endauth

    <section aria-labelledby="public-rooms-heading">
        <h2 id="public-rooms-heading" class="h4 mb-3">ห้องสาธารณะ</h2>
        <div class="row g-3">
            @forelse ($rooms as $room)
                @include('partials.room-card', ['room' => $room])
            @empty
                <p class="text-secondary">ยังไม่มีห้องสาธารณะ</p>
            @endforelse
        </div>
        <div class="mt-3">{{ $rooms->links('pagination::bootstrap-5') }}</div>
    </section>
@endsection
