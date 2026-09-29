@extends('layouts.rooms')
@section('title', 'เข้าร่วมห้อง')
@section('content')
    <div class="form-page mx-auto">
        <a href="{{ route('dashboard') }}" class="text-success">← กลับ Dashboard</a>
        <h1 class="h2 mt-4">เข้าร่วมห้องด้วยรหัส</h1>
        <p class="text-secondary">ขอรหัส 12 ตัวอักษรจากเจ้าของห้อง ใช้ได้ทั้งห้อง Public และ Private</p>
        <form method="POST" action="{{ route('rooms.join.store') }}" class="card rounded-4 p-4 mt-4">
            @csrf
            <label for="join_code" class="form-label">รหัสเข้าห้อง</label>
            <input id="join_code" name="join_code" class="form-control @error('join_code') is-invalid @enderror" value="{{ old('join_code') }}" maxlength="100" autocomplete="off" spellcheck="false" required>
            @error('join_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text mb-4">พิมพ์ตัวเล็กหรือตัวใหญ่ได้ ระบบจะปรับให้เอง</div>
            <button class="btn btn-success" type="submit">เข้าร่วมห้อง</button>
        </form>
    </div>
@endsection
