@extends('layouts.rooms')
@section('title', 'รีเซ็ตรอบนัดหมาย')
@section('content')
<div class="wa-page" style="max-width:890px">
    <a class="wa-back" href="{{ route('rooms.rounds.show', [$room, $round]) }}"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>กลับรอบนัดหมาย</a>
    <div class="wa-page-header mt-3"><div><span class="wa-kicker">จัดการรอบนัดหมาย</span><h1>รีเซ็ตรอบที่ {{ $round->round_no }}</h1><p>กลับไปขั้นตอนเข้าร่วมและกำหนดช่วงเวลาใหม่</p></div></div>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>สำเนาตารางของทุกคนในรอบจะถูกลบและดึงใหม่ตามกำหนดเวลาใหม่ สมาชิกในรอบยังอยู่เหมือนเดิม</div>
    <section class="wa-panel">
        <form method="POST" action="{{ route('rooms.rounds.reset.update', [$room, $round]) }}">
            @csrf @method('PATCH')
            @if ($errors->any())
                <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <fieldset class="wa-form-section"><legend>กรอบวันที่และเวลา</legend>
                <div class="wa-input-grid">
                    @foreach (['search_start_date' => 'วันแรกที่ค้นหา', 'search_end_date' => 'วันสุดท้ายที่ค้นหา'] as $field => $label)
                        <div><label class="form-label" for="{{ $field }}">{{ $label }}</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" type="date" required value="{{ old($field, $round->$field->format('Y-m-d')) }}"></div>
                    @endforeach
                    @foreach (['daily_start_time' => 'เวลาเริ่มรายวัน', 'daily_end_time' => 'เวลาสิ้นสุดรายวัน'] as $field => $label)
                        <div><label class="form-label" for="{{ $field }}">{{ $label }}</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" type="time" step="1800" required value="{{ old($field, substr($round->$field, 0, 5)) }}"></div>
                    @endforeach
                </div>
                <div class="mt-3"><label class="form-label" for="duration_minutes">ระยะเวลานัด (นาที)</label><input class="form-control" id="duration_minutes" name="duration_minutes" type="number" min="30" max="600" step="30" required value="{{ old('duration_minutes', $round->duration_minutes) }}"></div>
            </fieldset>
            <fieldset class="wa-form-section"><legend>เงื่อนไขอาจารย์</legend>
                <div class="wa-input-grid">
                    <div><label class="form-label" for="professor_rule">เงื่อนไขอาจารย์</label><select class="form-select" id="professor_rule" name="professor_rule"><option value="at_least" @selected(old('professor_rule', $round->professor_rule) === 'at_least')>อย่างน้อยตามจำนวน</option><option value="all" @selected(old('professor_rule', $round->professor_rule) === 'all')>ทุกคน</option></select></div>
                    <div><label class="form-label" for="min_professors">จำนวนขั้นต่ำ (ใช้เมื่อเลือกอย่างน้อย)</label><input class="form-control" id="min_professors" name="min_professors" type="number" min="1" max="100" value="{{ old('min_professors', $round->min_professors) }}"></div>
                </div>
            </fieldset>
            <fieldset class="wa-form-section"><legend>กำหนดเวลาใหม่ทุกขั้นตอน</legend><p>ต้องเรียงลำดับ: เริ่มเข้าร่วม → ตรวจข้อมูล → โหวต → สรุปผล</p>
                <div class="wa-input-grid">
                    @foreach (['join_starts_at' => 'เริ่มเข้าร่วม', 'review_starts_at' => 'เริ่มตรวจข้อมูล', 'voting_starts_at' => 'เริ่มโหวต', 'final_starts_at' => 'เริ่มสรุปผล'] as $field => $label)
                        <div><label class="form-label" for="{{ $field }}">{{ $label }}</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" type="datetime-local" required value="{{ old($field) }}"></div>
                    @endforeach
                </div>
            </fieldset>
            <label class="d-flex gap-2 align-items-start mb-3"><input class="form-check-input mt-1" type="checkbox" name="confirm_reset" value="1" required> <span>ยืนยันล้างสำเนาของทุกคนและรีเซ็ตรอบกลับขั้นตอนเข้าร่วม</span></label>
            <div class="wa-action-row"><button class="btn btn-warning" type="submit"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>ยืนยันรีเซ็ตรอบ</button><a class="btn btn-outline-secondary" href="{{ route('rooms.rounds.show', [$room, $round]) }}">ยกเลิก</a></div>
        </form>
    </section>
</div>
@endsection
