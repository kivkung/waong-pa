@extends('layouts.rooms')
@section('title', 'กิจกรรมส่วนตัว')
@section('content')
<div class="form-page mx-auto">
    <a href="{{ route('home') }}">← หน้าหลัก</a>
    <h1 class="h3 mt-3">กิจกรรมส่วนตัว</h1>
    <p>ระบบใช้เฉพาะเวลาไม่ว่างในรอบที่คุณเข้าร่วม โดยไม่เปิดเผยชื่อกิจกรรม</p>
    <form method="POST" action="{{ route('activities.store') }}" class="card p-4 mb-4">
        @csrf
        @include('activities.fields')
        <button class="btn btn-success">เพิ่มกิจกรรม</button>
    </form>
    @forelse ($activities as $activity)
        <article class="card p-3 mb-3">
            <strong>{{ $activity->name }}</strong>
            <p>{{ $activity->start_at->format('d/m/Y H:i') }} – {{ $activity->end_at->format('d/m/Y H:i') }}</p>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-success" href="{{ route('activities.edit', $activity) }}">แก้ไข</a>
                <form method="POST" action="{{ route('activities.destroy', $activity) }}" onsubmit="return confirm('ลบกิจกรรมนี้?')">
                    @csrf @method('DELETE') <button class="btn btn-outline-danger">ลบ</button>
                </form>
            </div>
        </article>
    @empty
        <p>ยังไม่มีกิจกรรม</p>
    @endforelse
    {{ $activities->links('pagination::bootstrap-5') }}
</div>
@endsection
