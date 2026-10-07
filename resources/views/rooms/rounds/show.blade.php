@extends('layouts.rooms')

@section('title', 'รายละเอียดรอบ')

@section('content')

    <div class="alert alert-info">
        ปิดรับสมาชิก: {{ $round->review_starts_at->format('d/m/Y H:i') }}
        · ระบบเริ่มดึงสำเนาได้ตั้งแต่ {{ $round->snapshotDueAt()->format('d/m/Y H:i') }} (ตรวจทุกนาที)
        <p class="mb-0">เข้ารอบหมายถึงยินยอมให้ระบบใช้เวลาไม่ว่างของคุณโดยอัตโนมัติ</p>
    </div>

    <div class="form-page mx-auto gap-1">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <a href="{{ route('rooms.show', compact('room')) }}" class="text-success">← กลับห้อง</a>
            @auth
                @if ((int) auth()->id() === (int) $room->owner_id && $round->status === 'active')
                    <a href="{{ route('rooms.rounds.reset.edit', [$room, $round]) }}"
                        class="btn btn-outline-warning mb-3">รีเซ็ตรอบกลับ
                        Join</a>
                @endif
            @endauth
        </div>

        <h1 class="mt-3">รอบที่ {{ $round->round_no }}</h1>

        <h2 class="h5 mt-3">ข้อมูลทั่วไป</h2>

        <dl class="row ">
            <dt class="col-sm-4">ห้อง</dt>
            <dd class="col-sm-8">{{ $room->name }}</dd>

            <dt class="col-sm-4">สถานะ</dt>
            <dd class="col-sm-8">{{ $round->status }}</dd>

            <dt class="col-sm-4">ช่วงวันที่ค้นหา</dt>
            <dd class="col-sm-8">
                {{ $round->search_start_date->format('d/m/Y') }}
                –
                {{ $round->search_end_date->format('d/m/Y') }}
            </dd>

            <dt class="col-sm-4">เวลาในแต่ละวัน</dt>
            <dd class="col-sm-8">
                {{ substr($round->daily_start_time, 0, 5) }}
                –
                {{ substr($round->daily_end_time, 0, 5) }}
            </dd>

            <dt class="col-sm-4">ระยะเวลานัด</dt>
            <dd class="col-sm-8">{{ $round->duration_minutes }} นาที</dd>

            <dt class="col-sm-4">เงื่อนไขอาจารย์</dt>
            <dd class="col-sm-8">
                @if ($round->professor_rule === 'all')
                    อาจารย์ทุกคนในรอบ
                @else
                    อย่างน้อย {{ $round->min_professors }} คน
                @endif
            </dd>

            <dt class="col-sm-4">เฟสปัจจุบัน</dt>
            <dd class="col-sm-8">{{ $round->phase }}</dd>
        </dl>

        <h2 class="h5">กำหนดเวลาแต่ละเฟส</h2>

        <dl class="row">
            @foreach ([
                    'join_starts_at' => 'เปิดรับข้อมูล (Join)',
                    'review_starts_at' => 'ตรวจตัวเลือก (Review)',
                    'voting_starts_at' => 'เริ่มโหวต (Voting)',
                    'final_starts_at' => 'สรุปผล (Final)',
                ] as $field => $label)
                <dt class="col-sm-4">{{ $label }}</dt>
                <dd class="col-sm-8">
                    {{ $round->$field->format('d/m/Y H:i') }}
                </dd>
            @endforeach
        </dl>

        <dl>
            <h2 class="h5">log</h2>
            @if ($round->snapshot_taken_at)
            <div class="row text-secondary">
                <p>ข้อมูลนี้ถูกดึงเมื่อ {{ $round->snapshot_taken_at->format('d/m/Y H:i') }}</p>
            </div>
            @elseif ($round->phase !== 'join' || now()->gte($round->review_starts_at))
            <div class="row text-secondary">
                    <p>กำลังรวบรวมตาราง</p>
                </div>
            @endif
        </dl>

        @auth
            <div class="row">
                <a href="{{ route('activities.index') }}" class="btn btn-success mb-3">จัดการกิจกรรมส่วนตัว</a>
            </div>
        @endauth

        <p class="small text-secondary">
            ระบบปิด Join อัตโนมัติตามกำหนด และดึงเวลาไม่ว่างหลังระยะรอที่แสดงด้านบน
        </p>

        @if ($hasJoined && $round->status === "active")
            <p class="text-success">
                คุณเข้าร่วมรอบนี้แล้ว
            </p>
        @elseif ($canJoin)
            <form method="POST" action="{{ route('rooms.rounds.members.store', [
                'room' => $room,
                'round' => $round,
            ]) }}" class="d-flex justify-content-center">
                @csrf
                <button type="submit" class="btn btn-success w-100">
                    เข้าร่วมรอบนี้
                </button>
            </form>
        @elseif ($round->status !== 'cancelled')
            <p class="text-secondary">
                <b>ไม่สามารถเข้าร่วมได้</b> กรุณาตรวจสอบช่วงเวลาเปิดรับ
                และสถานะสมาชิกห้อง
            </p>
        @endif

        @if ($round->cancelled_at)
            <p>
                ยกเลิกเมื่อ:
                {{ $round->cancelled_at->format('d/m/Y H:i') }}
            </p>
        @endif

        @auth
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                @if (((int) auth()->id() === (int) $room->owner_id) && ($round->status === "active"))
                    <a href="{{ route('rooms.rounds.members.edit', [$room, $round]) }}" class="btn 
                                                            @if ($round->status === "active")
                                                                btn-outline-secondary
                                                            @else
                                                                btn-outline-warning
                                                            @endif
                                                            my-3">แก้ไขสมาชิกในรอบ</a>
                @endif
                @if ((int) auth()->id() === (int) $room->owner_id && $round->status === 'active')
                    <form method="POST" action="{{ route('rooms.rounds.cancel', ['room' => $room, 'round' => $round]) }}"
                        onsubmit="return confirm('ต้องการยกเลิกรอบนี้หรือไม่?')">
                        @csrf
                        @method('PATCH')

                        <button type="submit" class="btn btn-outline-danger">
                            ยกเลิกรอบ
                        </button>
                    </form>
                @endif
            </div>
        @endauth

        @if ($roundMembers !== null)
            <section class="card rounded-4 p-4 mt-4">
                <h2 class="h5">
                    สมาชิกในรอบ ({{ $roundMembers->total() }})
                </h2>

                <ul class="list-group list-group-flush">
                    @forelse ($roundMembers as $roundMember)
                        <li class="list-group-item px-0 py-3">
                            <div class="fw-semibold">
                                {{ $roundMember->roomMember->user->name }}
                            </div>

                            <div class="small text-secondary">
                                {{ $roundMember->role === 'professor' ? 'อาจารย์' : 'นักศึกษา' }}
                                · น้ำหนัก {{ $roundMember->weight }}
                            </div>

                            <div class="small text-secondary">
                                เข้าร่วมเมื่อ
                                {{ $roundMember->joined_at->format('d/m/Y H:i') }}
                            </div>

                            @include('rooms.rounds.busy-periods', ['participant' => $roundMember])
                        </li>
                    @empty
                        <li class="list-group-item px-0 text-secondary">
                            ยังไม่มีสมาชิกเข้าร่วมรอบนี้
                        </li>
                    @endforelse
                </ul>

                <div class="mt-3">
                    {{ $roundMembers->links('pagination::bootstrap-5') }}
                </div>
            </section>
        @endif
    </div>

@endsection
