@extends('layouts.rooms')
@section('title', 'สร้างรอบนัดหมาย')
@section('content')
<div class="wa-page">
    <div class="wa-page-header">
        <div>
            <a class="wa-back" href="{{ route('rooms.show', $room) }}"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>กลับห้อง</a>
            <div class="mt-3 wa-kicker">จัดการรอบนัดหมาย</div>
            <h1>สร้างรอบนัดหมาย</h1>
            <p>ห้อง: {{ $room->name }} · ระบุเงื่อนไขและช่วงเวลาในแต่ละขั้นตอน</p>
        </div>
    </div>
    <form method="POST" action="{{ route('rooms.rounds.store', $room) }}">
        @csrf
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <div class="fw-semibold mb-2">กรุณาตรวจสอบข้อมูลอีกครั้ง</div>
                <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <div class="wa-grid">
            <div>
                <section class="wa-panel wa-form-section" aria-labelledby="round-window-title">
                    <div class="wa-kicker mb-1">ขั้นตอนที่ 1</div>
                    <fieldset>
                        <legend id="round-window-title">กรอบเวลาที่ต้องการนัด</legend>
                        <p>ระบบจะค้นหาเฉพาะวันและช่วงเวลาที่ระบุ</p>
                        <div class="wa-input-grid">
                            <div>
                                <label class="form-label" for="search_start_date">วันแรก</label>
                                <input id="search_start_date" name="search_start_date" type="date" class="form-control" value="{{ old('search_start_date') }}" required>
                            </div>
                            <div>
                                <label class="form-label" for="search_end_date">วันสุดท้าย</label>
                                <input id="search_end_date" name="search_end_date" type="date" class="form-control" value="{{ old('search_end_date') }}" required>
                            </div>
                            <div>
                                <label class="form-label" for="daily_start_time">เวลาเริ่มค้นหาแต่ละวัน</label>
                                <input id="daily_start_time" name="daily_start_time" type="time" class="form-control" value="{{ old('daily_start_time', '08:00') }}" step="1800" required>
                            </div>
                            <div>
                                <label class="form-label" for="daily_end_time">เวลาสิ้นสุดแต่ละวัน</label>
                                <input id="daily_end_time" name="daily_end_time" type="time" class="form-control" value="{{ old('daily_end_time', '18:00') }}" step="1800" required>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label" for="duration_minutes">ระยะเวลานัด (นาที)</label>
                            <input id="duration_minutes" name="duration_minutes" type="number" class="form-control" value="{{ old('duration_minutes', 60) }}" min="30" max="600" step="30" required>
                        </div>
                    </fieldset>
                </section>
                <section class="wa-panel mt-3" aria-labelledby="round-professor-title">
                    <div class="wa-kicker mb-1">ขั้นตอนที่ 2</div>
                    <fieldset class="wa-form-section mb-0 pb-0">
                        <legend id="round-professor-title">เงื่อนไขอาจารย์</legend>
                        <p>กำหนดจำนวนอาจารย์ที่ต้องว่างสำหรับช่วงเวลาที่ระบบจะพิจารณา</p>
                        <div class="mb-3">
                            <label for="professor_rule" class="form-label">เงื่อนไขการว่างของอาจารย์</label>
                            <select id="professor_rule" name="professor_rule" class="form-select" required>
                                <option value="at_least" @selected(old('professor_rule', 'at_least') === 'at_least')>อย่างน้อยตามจำนวนที่กำหนด</option>
                                <option value="all" @selected(old('professor_rule', 'at_least') === 'all')>อาจารย์ทุกคนในรอบ</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="min_professors">จำนวนอาจารย์ขั้นต่ำ</label>
                            <input id="min_professors" name="min_professors" type="number" class="form-control" value="{{ old('min_professors', 1) }}" min="1" max="100" step="1" aria-describedby="professor-help">
                            <div id="professor-help" class="form-text">ใช้เฉพาะกรณีเลือก “อย่างน้อยตามจำนวนที่กำหนด”</div>
                        </div>
                    </fieldset>
                </section>
            </div>
            <div>
                <section class="wa-panel" aria-labelledby="round-phases-title">
                    <div class="wa-kicker mb-1">ขั้นตอนที่ 3</div>
                    <fieldset class="wa-form-section mb-0 pb-0">
                        <legend id="round-phases-title">กำหนดเวลาแต่ละขั้นตอน</legend>
                        <p>ตั้งเวลาตามลำดับ เข้าร่วม → ตรวจข้อมูล → โหวต → สรุปผล โดยสรุปผลก่อนเวลานัดแรก</p>
                        @foreach ([
                            'join_starts_at' => 'เริ่มให้เข้าร่วมรอบ',
                            'review_starts_at' => 'ปิดรับสมาชิก / เริ่มตรวจข้อมูล',
                            'voting_starts_at' => 'เริ่มโหวต',
                            'final_starts_at' => 'เริ่มสรุปผล',
                        ] as $field => $label)
                            <div class="mb-3">
                                <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                                <input id="{{ $field }}" name="{{ $field }}" type="datetime-local" class="form-control form-input-starts" value="{{ old($field) }}" required>
                            </div>
                        @endforeach
                    </fieldset>
                </section>
                <div class="wa-notice mt-3"><i class="bi bi-info-circle me-1" aria-hidden="true"></i> เมื่อสร้างรอบแล้ว สมาชิกสามารถเข้าร่วมได้ตามช่วงเวลาที่กำหนด</div>
                <div class="wa-action-row mt-3">
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>สร้างรอบ</button>
                    <a href="{{ route('rooms.show', $room) }}" class="btn btn-outline-secondary">ยกเลิก</a>
                </div>
            </div>
        </div>
    </form>
    @env('local')
        <button type="button" class="btn btn-outline-secondary btn-sm mt-3" onclick="autoFillTest()">เติมข้อมูลทดสอบ</button>
        <script>
            function autoFillTest() {
                const pad = n => String(n).padStart(2, '0');
                const formatDate = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
                const formatDT = d => `${formatDate(d)}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
                const now = new Date();
                const setVal = (id, val) => { const el = document.getElementById(id); if (el) { el.value = val; el.dispatchEvent(new Event('change', { bubbles: true })); } };
                const startDate = new Date(); startDate.setDate(now.getDate() + 1);
                const endDate = new Date(); endDate.setDate(now.getDate() + 1);
                setVal('search_start_date', formatDate(startDate));
                setVal('search_end_date', formatDate(endDate));
                setVal('daily_start_time', '09:00');
                setVal('daily_end_time', '17:00');
                setVal('duration_minutes', '60');
                setVal('professor_rule', 'at_least');
                setVal('min_professors', '2');
                setVal('join_starts_at', formatDT(new Date(now.getTime() + 5 * 60000)));
                setVal('review_starts_at', formatDT(new Date(now.getTime() + 10 * 60000)));
                setVal('voting_starts_at', formatDT(new Date(now.getTime() + 15 * 60000)));
                setVal('final_starts_at', formatDT(new Date(now.getTime() + 20 * 60000)));
            }
        </script>
    @endenv
</div>
@endsection
