@extends('layouts.rooms')
@section('title', 'รีเซ็ตรอบกลับ Join')
@section('content')
<div class="form-page mx-auto">
    <a href="{{ route('rooms.rounds.show', [$room, $round]) }}">← กลับรอบ</a>
    <h1 class="h3 mt-3">รีเซ็ตรอบที่ {{ $round->round_no }} กลับ Join</h1>
    <div class="alert alert-warning">สำเนาตารางของทุกคนในรอบจะถูกลบและดึงใหม่ตามกำหนดเวลาใหม่ สมาชิกในรอบยังอยู่เหมือนเดิม</div>
    <form method="POST" action="{{ route('rooms.rounds.reset.update', [$room, $round]) }}">
        @csrf @method('PATCH')
        @if ($errors->any())<div class="alert alert-danger"><ul>
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul></div>@endif
        @foreach (['search_start_date' => 'วันแรกที่ค้นหา', 'search_end_date' => 'วันสุดท้ายที่ค้นหา'] as $field => $label)
            <label for="{{ $field }}">{{ $label }}</label>
            <input class="form-control mb-3" id="{{ $field }}" name="{{ $field }}" type="date" required value="{{ old($field, $round->$field->format('Y-m-d')) }}">
        @endforeach
        @foreach (['daily_start_time' => 'เวลาเริ่มรายวัน', 'daily_end_time' => 'เวลาสิ้นสุดรายวัน'] as $field => $label)
            <label for="{{ $field }}">{{ $label }}</label>
            <input class="form-control mb-3" id="{{ $field }}" name="{{ $field }}" type="time" step="1800" required value="{{ old($field, substr($round->$field, 0, 5)) }}">
        @endforeach
        <label for="duration_minutes">ระยะเวลานัด (นาที)</label>
        <input class="form-control mb-3" id="duration_minutes" name="duration_minutes" type="number" min="30" max="600" step="30" required value="{{ old('duration_minutes', $round->duration_minutes) }}">
        <label for="professor_rule">เงื่อนไขอาจารย์</label>
        <select class="form-select mb-3" id="professor_rule" name="professor_rule">
            <option value="at_least" @selected(old('professor_rule', $round->professor_rule) === 'at_least')>อย่างน้อยตามจำนวน</option>
            <option value="all" @selected(old('professor_rule', $round->professor_rule) === 'all')>ทุกคน</option>
        </select>
        <label for="min_professors">จำนวนขั้นต่ำ (ใช้เมื่อเลือกอย่างน้อย)</label>
        <input class="form-control mb-3" id="min_professors" name="min_professors" type="number" min="1" max="100" value="{{ old('min_professors', $round->min_professors) }}">
        <p>กรอกกำหนดเวลาใหม่ทุกครั้ง: Join &lt; Review &lt; Voting &lt; Final</p>
        @foreach (['join_starts_at' => 'Join', 'review_starts_at' => 'Review', 'voting_starts_at' => 'Voting', 'final_starts_at' => 'Final'] as $field => $label)
            <label for="{{ $field }}">{{ $label }}</label>
            <input class="form-control mb-3" id="{{ $field }}" name="{{ $field }}" type="datetime-local" required value="{{ old($field) }}">
        @endforeach
        <label class="d-block mb-3"><input type="checkbox" name="confirm_reset" value="1" required> ยืนยันล้างสำเนาของทุกคนและรีเซ็ตรอบกลับ Join</label>
        <button class="btn btn-warning">ยืนยันรีเซ็ตรอบ</button>
    </form>
</div>
@endsection
