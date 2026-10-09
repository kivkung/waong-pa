@extends('layouts.rooms')
@section('title', 'สร้างห้อง')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rooms-ui.css') }}">
@endpush
@section('content')
<div class="waongpa-room-page">
    <div class="wr-topline">
        <a href="{{ route('dashboard') }}" class="wr-back"><i class="bi bi-arrow-left" aria-hidden="true"></i> กลับหน้าหลัก</a>
        <span class="wr-eyebrow">ห้องนัดหมายของฉัน</span>
    </div>
    <header class="wr-heading">
        <div>
            <span class="wr-kicker">จัดการห้องนัดหมาย</span>
            <h1>สร้างห้องใหม่</h1>
            <p>ตั้งชื่อและรายละเอียดห้องเพื่อเริ่มนัดหมายร่วมกับสมาชิกของคุณ</p>
        </div>
    </header>
    <div class="wr-form-layout">
        <section class="wr-panel" aria-labelledby="wr-create-form-title">
            <div class="wr-section-head">
                <span class="wr-icon"><i class="bi bi-plus-lg" aria-hidden="true"></i></span>
                <div><h2 id="wr-create-form-title">ข้อมูลห้อง</h2><p>กรอกข้อมูลที่จำเป็นสำหรับการสร้างห้อง</p></div>
            </div>
            <form method="POST" action="{{ route('rooms.store') }}" class="wr-form">
                @csrf
                <div class="mb-4">
                    <label for="name" class="form-label">ชื่อห้อง <span class="wr-required">*</span></label>
                    <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" maxlength="150" placeholder="เช่น ประชุมโปรเจกต์ Web Application" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-4">
                    <label for="description" class="form-label">รายละเอียด <span class="wr-optional">(ไม่บังคับ)</span></label>
                    <textarea id="description" name="description" rows="4" maxlength="2000" placeholder="อธิบายวัตถุประสงค์ของห้องนัดหมาย" class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-4">
                    <label for="visibility" class="form-label">การมองเห็นห้อง <span class="wr-required">*</span></label>
                    <select id="visibility" name="visibility" class="form-select @error('visibility') is-invalid @enderror" required>
                        <option value="private" @selected(old('visibility', 'private') === 'private')>ส่วนตัว — เฉพาะสมาชิก</option>
                        <option value="public" @selected(old('visibility', 'private') === 'public')>สาธารณะ — ทุกคนดูข้อมูลทั่วไปได้</option>
                    </select>
                    @error('visibility') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <p class="wr-field-help"><i class="bi bi-info-circle" aria-hidden="true"></i> สมาชิกจะเข้าร่วมห้องผ่านรหัสเชิญจากเจ้าของห้อง</p>
                </div>
                <div class="wr-actions">
                    <button class="btn btn-success wr-primary" type="submit"><i class="bi bi-plus-circle me-2" aria-hidden="true"></i>สร้างห้อง</button>
                    <a class="btn btn-outline-secondary wr-secondary" href="{{ route('dashboard') }}">ยกเลิก</a>
                </div>
            </form>
        </section>
        <aside class="wr-side">
            <div class="wr-info-card">
                <span class="wr-side-icon" aria-hidden="true"><i class="bi bi-people"></i></span>
                <h2>สร้างห้องแล้วทำอะไรต่อ?</h2>
                <p>คุณจะเป็นเจ้าของและสมาชิกของห้องโดยอัตโนมัติ ไม่ต้องเข้าร่วมห้องตัวเองอีกครั้ง</p>
                <div class="wr-mini-step"><span>01</span> สร้างห้องและรับรหัสเชิญ</div>
                <div class="wr-mini-step"><span>02</span> ส่งรหัสให้สมาชิกคนอื่น</div>
                <div class="wr-mini-step"><span>03</span> สร้างรอบนัดหมายภายในห้อง</div>
            </div>
        </aside>
    </div>
</div>
@endsection
