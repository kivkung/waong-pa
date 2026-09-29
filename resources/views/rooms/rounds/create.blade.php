@extends('layouts.rooms')

@section('title', 'สร้างรอบนัดหมาย')

@section('content')
    <div class="form-page mx-auto">
        <a href="{{ route('rooms.show', $room) }}" class="text-success">
            ← กลับห้อง
        </a>


        <section class="card rounded-4 p-4 mt-4">
            <h1 class="h3">สร้างรอบนัดหมาย</h1>
            <p class="room-description">ห้อง: {{ $room->name }}</p>

            <form method="POST" action="{{ route('rooms.rounds.store', $room) }}">
                @csrf

                {{-- แสดงข้อผิดพลาดจาก backend เมื่อกลับมาที่ฟอร์ม --}}
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert mb-7">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <fieldset class="mb-5">
                    <legend class="h5 border-start border-success border-4 ps-2">กรอบเวลาที่ต้องการนัด</legend>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="search_start_date" class="form-label">วันแรก</label>
                            <input id="search_start_date" name="search_start_date" type="date" class="form-control"
                                value="{{ old('search_start_date') }}" required>
                        </div>

                        <div class="col-md-6">
                            <label for="search_end_date" class="form-label">วันสุดท้าย</label>
                            <input id="search_end_date" name="search_end_date" type="date" class="form-control"
                                value="{{ old('search_end_date') }}" required>
                        </div>

                        <div class="col-md-6">
                            <label for="daily_start_time" class="form-label">
                                เวลาเริ่มค้นหาในแต่ละวัน
                            </label>
                            <input id="daily_start_time" name="daily_start_time" type="time" class="form-control"
                                value="{{ old('daily_start_time', '08:00') }}" step="1800" required>
                        </div>

                        <div class="col-md-6">
                            <label for="daily_end_time" class="form-label">
                                เวลาสิ้นสุดในแต่ละวัน
                            </label>
                            <input id="daily_end_time" name="daily_end_time" type="time" class="form-control"
                                value="{{ old('daily_end_time', '18:00') }}" step="1800" required>
                        </div>

                        <div class="col-12">
                            <label for="duration_minutes" class="form-label">
                                ระยะเวลานัด (นาที)
                            </label>
                            <input id="duration_minutes" name="duration_minutes" type="number" class="form-control"
                                value="{{ old('duration_minutes', 60) }}" min="30" max="600" step="30" required>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="mb-5">
                    <legend class="h5 border-start border-success border-4 ps-2">เงื่อนไขอาจารย์</legend>

                    <div class="mb-3">
                        <label for="professor_rule" class="form-label">
                            อาจารย์ที่ต้องว่าง
                        </label>
                        <select id="professor_rule" name="professor_rule" class="form-select" required>
                            <option value="at_least" @selected(old('professor_rule', 'at_least') === 'at_least')>
                                อย่างน้อยตามจำนวนที่กำหนด
                            </option>
                            <option value="all" @selected(old('professor_rule', 'at_least') === 'all')>
                                อาจารย์ทุกคนในรอบ
                            </option>
                        </select>
                    </div>

                    <div>
                        <label for="min_professors" class="form-label">
                            จำนวนอาจารย์ขั้นต่ำ
                        </label>
                        <input id="min_professors" name="min_professors" type="number" class="form-control"
                            value="{{ old('min_professors', 1) }}" min="1" max="100" step="1"
                            aria-describedby="professor-help">
                        <div id="professor-help" class="form-text">
                            ใช้เฉพาะเมื่อเลือกจำนวนขั้นต่ำ หากเลือกทุกคน ระบบจะไม่ใช้ค่านี้
                        </div>
                    </div>
                </fieldset>

                <fieldset class="mb-5">
                    <legend class="h5 border-start border-success border-4 ps-2">กำหนดเวลาแต่ละเฟส</legend>
                    <p class="small text-secondary">
                        ตั้งเวลาตามลำดับ Join → Review → Voting → Final
                        และสรุปผลก่อนเวลาแรกที่ค้นหานัด
                    </p>

                    @foreach ([
                            'join_starts_at' => 'เปิดรับข้อมูล (Join)',
                            'review_starts_at' => 'เริ่มตรวจตัวเลือก (Review)',
                            'voting_starts_at' => 'เริ่มโหวต (Voting)',
                            'final_starts_at' => 'กำหนดสรุปผล (Final)',
                        ] as $field => $label)
                        <div class="mb-3">
                            <label for="{{ $field }}" class="form-label">
                                {{ $label }}
                            </label>
                            <input id="{{ $field }}" name="{{ $field }}" type="datetime-local"
                                class="form-control form-input-starts" value="{{ old($field) }}" required>
                        </div>
                    @endforeach
                </fieldset>

                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-success">สร้างรอบ</button>
                    <a href="{{ route('rooms.show', $room) }}" class="btn btn-outline-secondary">
                        ยกเลิก
                    </a>
                </div>
            </form>
        </section>
    </div>

    {{-- for test input form --}}
    @env('local')
        <button type="button" class="btn btn-warning" onclick="autoFillTest()">เติมข้อมูลทดสอบ</button>

        <script>
            function autoFillTest() {
                const pad = n => String(n).padStart(2, '0');
                const formatDate = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
                const formatDT = d => `${formatDate(d)}T${pad(d.getHours())}:${pad(d.getMinutes())}`;

                const now = new Date();
                const setVal = (id, val) => {
                    const el = document.getElementById(id);
                    if (el) { el.value = val; el.dispatchEvent(new Event('change', { bubbles: true })); }
                };

                // วันที่ค้นหา (วันพรุ่งนี้ ถึง +5 วัน)
                const startDate = new Date(); startDate.setDate(now.getDate() + 1);
                const endDate = new Date(); endDate.setDate(now.getDate() + 1);

                setVal('search_start_date', formatDate(startDate));
                setVal('search_end_date', formatDate(endDate));
                setVal('daily_start_time', '09:00');
                setVal('daily_end_time', '17:00');
                setVal('duration_minutes', '60');
                setVal('professor_rule', 'at_least');
                setVal('min_professors', '2');

                // แต่ละเฟส
                setVal('join_starts_at', formatDT(new Date(now.getTime() + 5 * 60000)));
                setVal('review_starts_at', formatDT(new Date(now.getTime() + 10 * 60000)));
                setVal('voting_starts_at', formatDT(new Date(now.getTime() + 15 * 60000)));
                setVal('final_starts_at', formatDT(new Date(now.getTime() + 20 * 60000)));
            }
        </script>
    @endenv


@endsection