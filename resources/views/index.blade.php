@extends('layouts.rooms')
@section('title', 'Dashboard')
@push('styles')
    @vite('resources/css/lecture-chair.css')
@endpush
@push('scripts')
    @vite('resources/js/lecture-chair.js')
@endpush
@section('content')
    {{-- Idle delay in milliseconds: 1000 = 1 second (preview), 90000 = 1.5 minutes. --}}
    <section class="hero lecture-hero rounded-4 p-4 p-md-5 mb-5" data-lecture-hero data-chair-idle-ms="20000">
        <div class="lecture-hero-copy">
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
        <div class="lecture-scene">
            <span class="lecture-seat-label"><span></span> มีที่ให้คุณเสมอ</span>
            <div class="lecture-chair-motion" data-lecture-chair>
                @include('partials.lecture-chair')
            </div>
            <div class="lecture-scene-caption">
                <span class="lecture-seat-number">SEAT / 01</span>
                <p>ขยับเมาส์ แล้วทักเก้าอี้ตัวนี้ดู</p>
                <button class="lecture-preview" type="button" data-chair-preview hidden>ลองเรียกเก้าอี้ <span aria-hidden="true">↗</span></button>
            </div>
        </div>
    </section>

    <aside class="lecture-dialog" data-chair-dialog aria-labelledby="chair-dialog-title" hidden>
        <div class="lecture-dialog-card">
            <button class="lecture-dialog-close" type="button" aria-label="ปิดข้อความ" data-chair-dismiss>×</button>
            <div class="lecture-dialog-chair" aria-hidden="true">@include('partials.lecture-chair')</div>
            <div class="lecture-dialog-copy">
                <p class="lecture-dialog-eyebrow">A SEAT FOR YOU</p>
                <h2 id="chair-dialog-title" role="status">ว่างป่ะ ตรงนี้ว่างนะ</h2>
                <p id="chair-dialog-description">มีที่ให้เสมอ พร้อมเมื่อไหร่ค่อยมา</p>
                <button class="lecture-dialog-reply" type="button" data-chair-dismiss>โอเค ไว้เจอกัน <span aria-hidden="true">↗</span></button>
            </div>
        </div>
    </aside>

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
