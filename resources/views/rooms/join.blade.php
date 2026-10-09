@extends('layouts.rooms')
@section('title', 'เข้าร่วมห้อง')
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
            <span class="wr-kicker">เข้าร่วมการนัดหมาย</span>
            <h1>เข้าร่วมห้อง</h1>
            <p>กรอกรหัสเชิญจากเจ้าของห้อง เพื่อเข้าร่วมและดูรอบนัดหมาย</p>
        </div>
        <div class="wr-heading-icon" aria-hidden="true"><i class="bi bi-box-arrow-in-right"></i></div>
    </header>
    <div class="wr-join-layout">
        <section class="wr-panel" aria-labelledby="wr-join-form-title">
            <div class="wr-section-head">
                <span class="wr-icon"><i class="bi bi-key" aria-hidden="true"></i></span>
                <div><h2 id="wr-join-form-title">รหัสเข้าร่วมห้อง</h2><p>ใช้รหัสที่ได้รับจากเจ้าของห้อง</p></div>
            </div>
            <form method="POST" action="{{ route('rooms.join.store') }}" class="wr-form">
                @csrf
                <div class="mb-4">
                    <label for="join_code" class="form-label">รหัสเข้าห้อง <span class="wr-required">*</span></label>
                    <input id="join_code" name="join_code" class="form-control wr-code-input @error('join_code') is-invalid @enderror" value="{{ old('join_code') }}" maxlength="100" autocomplete="off" spellcheck="false" placeholder="กรอกรหัสเชิญ 12 ตัวอักษร" required aria-describedby="wr-code-help">
                    @error('join_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <p id="wr-code-help" class="wr-field-help"><i class="bi bi-info-circle" aria-hidden="true"></i> พิมพ์ตัวเล็กหรือตัวใหญ่ได้ ระบบจะปรับให้เอง</p>
                </div>
                <div class="wr-actions">
                    <button class="btn btn-success wr-primary" type="submit"><i class="bi bi-box-arrow-in-right me-2" aria-hidden="true"></i>เข้าร่วมห้อง</button>
                    <a class="btn btn-outline-secondary wr-secondary" href="{{ route('dashboard') }}">ยกเลิก</a>
                </div>
            </form>
        </section>
        <aside class="wr-side">
            <div class="wr-info-card">
                <span class="wr-side-icon" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
                <h2>ข้อมูลเกี่ยวกับรหัสเชิญ</h2>
                <p>รหัสเชิญใช้เข้าร่วมได้ทั้งห้องสาธารณะและห้องส่วนตัว หากยังไม่มีรหัส กรุณาขอจากเจ้าของห้อง</p>
                <div class="wr-note"><i class="bi bi-lock" aria-hidden="true"></i> ห้องส่วนตัว แสดงรายละเอียดให้สมาชิกที่มีสิทธิ์เท่านั้น</div>
            </div>
        </aside>
    </div>
</div>
@endsection
