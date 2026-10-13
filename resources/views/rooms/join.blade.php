@extends('layouts.rooms')
@section('title', 'เข้าร่วมห้อง')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/join-room.css') }}">
@endpush

@section('content')
    <div class="join-room-page">

        <div class="join-top-bar">
            <a class="join-back-link" href="{{ route('dashboard') }}">
                <i class="bi bi-arrow-left"></i>
                กลับหน้าหลัก
            </a>
            <span class="join-top-label">ห้องนัดหมายของฉัน</span>
        </div>

        <header class="join-page-header">
            <div>
                <span class="join-page-subtitle">เข้าร่วมการนัดหมาย</span>
                <h1>เข้าร่วมห้อง</h1>
                <p>กรอกรหัสเชิญจากเจ้าของห้อง เพื่อเข้าร่วมและดูรอบนัดหมาย</p>
            </div>

            <div class="join-page-icon">
                <i class="bi bi-box-arrow-in-right"></i>
            </div>
        </header>

        <div class="join-content-layout">
            <section class="join-form-card" aria-labelledby="join-form-title">
                <div class="join-form-header">
                    <span class="join-form-icon">
                        <i class="bi bi-key"></i>
                    </span>

                    <div>
                        <h2 id="join-form-title">รหัสเข้าร่วมห้อง</h2>
                        <p>ใช้รหัสที่ได้รับจากเจ้าของห้อง</p>
                    </div>
                </div>

                <form class="join-form" method="POST" action="{{ route('rooms.join.store') }}">
                    @csrf

                    <div class="mb-4">
                        <label class="form-label" for="join_code">
                            รหัสเข้าห้อง <span class="join-required">*</span>
                        </label>

                        <input
                            id="join_code"
                            name="join_code"
                            class="form-control join-code-input @error('join_code') is-invalid @enderror"
                            value="{{ old('join_code') }}"
                            maxlength="100"
                            autocomplete="off"
                            spellcheck="false"
                            placeholder="กรอกรหัสเชิญ 12 ตัวอักษร"
                            required
                            aria-describedby="join-code-help"
                        >

                        @error('join_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror

                        <p id="join-code-help" class="join-field-help">
                            <i class="bi bi-info-circle"></i>
                            พิมพ์ตัวเล็กหรือตัวใหญ่ได้ ระบบจะปรับให้เอง
                        </p>
                    </div>

                    <div class="join-form-actions">
                        <button class="btn btn-success join-submit-button" type="submit">
                            <i class="bi bi-box-arrow-in-right me-2"></i>เข้าร่วมห้อง
                        </button>
                        <a class="btn btn-outline-secondary join-cancel-button" href="{{ route('dashboard') }}">
                            ยกเลิก
                        </a>
                    </div>
                </form>
            </section>

            <aside class="join-info-sidebar">
                <div class="join-info-card">
                    <span class="join-info-icon">
                        <i class="bi bi-shield-check"></i>
                    </span>

                    <h2>ข้อมูลเกี่ยวกับรหัสเชิญ</h2>
                    <p>
                        รหัสเชิญใช้เข้าร่วมได้ทั้งห้องสาธารณะและห้องส่วนตัว
                        หากยังไม่มีรหัส กรุณาขอจากเจ้าของห้อง
                    </p>

                    <div class="join-privacy-note">
                        <i class="bi bi-lock"></i>
                        ห้องส่วนตัว แสดงรายละเอียดให้สมาชิกที่มีสิทธิ์เท่านั้น
                    </div>
                </div>
            </aside>
        </div>
    </div>
@endsection
