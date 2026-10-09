@extends('layouts.rooms')
@section('title', 'แก้ไขกิจกรรมส่วนตัว')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/schedule-ui.css') }}">
@endpush
@section('content')
<div class="schedule-page schedule-edit-page">
    <a class="schedule-back" href="{{ route('activities.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> กลับตารางของฉัน</a>
    <header class="schedule-heading">
        <div>
            <span class="schedule-eyebrow">ตารางเวลาส่วนตัว</span>
            <h1>แก้ไขกิจกรรม</h1>
            <p>อัปเดตชื่อและช่วงเวลาที่ไม่สะดวกของคุณ</p>
        </div>
        <span class="schedule-heading-icon" aria-hidden="true"><i class="bi bi-pencil-square"></i></span>
    </header>
    <section class="schedule-panel" aria-label="แก้ไขกิจกรรม">
        <form method="POST" action="{{ route('activities.update', $activity) }}" class="schedule-form">
            @csrf
            @method('PATCH')
            @include('activities.fields')
            <div class="schedule-edit-actions">
                <button class="btn btn-success" type="submit">บันทึกการเปลี่ยนแปลง</button>
                <a class="btn btn-outline-secondary" href="{{ route('activities.index') }}">ยกเลิก</a>
            </div>
        </form>
    </section>
</div>
@endsection
