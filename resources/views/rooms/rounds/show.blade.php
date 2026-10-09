@extends('layouts.rooms')
@section('title', 'รายละเอียดรอบนัดหมาย')
@section('content')
<div class="wa-page">
    <div class="wa-page-header">
        <div>
            <a class="wa-back" href="{{ route('rooms.show', compact('room')) }}"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>กลับห้อง</a>
            <div class="wa-kicker mt-3">{{ $room->name }}</div>
            <h1>รอบนัดหมายที่ {{ $round->round_no }}</h1>
            <p>ดูเงื่อนไข รอบเวลา และสถานะการเข้าร่วมของสมาชิก</p>
        </div>
        <span class="badge {{ $round->status === 'active' ? 'text-bg-primary' : ($round->status === 'cancelled' ? 'text-bg-danger' : 'text-bg-secondary') }} align-self-start mt-2">
            {{ $round->status === 'active' ? 'กำลังดำเนินการ' : ($round->status === 'cancelled' ? 'ยกเลิกแล้ว' : $round->status) }}
        </span>
    </div>
    <div class="wa-notice mb-4">
        <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
        ปิดรับสมาชิก {{ $round->review_starts_at->format('d/m/Y H:i') }} น. · ระบบเริ่มดึงสำเนาเวลาไม่ว่างได้ตั้งแต่ {{ $round->snapshotDueAt()->format('d/m/Y H:i') }} น. (ตรวจทุกนาที)
        <div class="small mt-1">การเข้าร่วมรอบหมายถึงยินยอมให้ระบบใช้เฉพาะช่วงเวลาที่ไม่ว่างของคุณโดยอัตโนมัติ</div>
    </div>
    <div class="wa-grid mb-4">
        <section class="wa-panel" aria-labelledby="round-detail-title">
            <h2 class="wa-panel-title" id="round-detail-title">รายละเอียดรอบ</h2>
            <p class="wa-muted small">เงื่อนไขที่กำหนดไว้สำหรับรอบนัดหมายนี้</p>
            <dl class="wa-detail-grid mb-0">
                <div class="wa-detail-field"><dt>ห้อง</dt><dd>{{ $room->name }}</dd></div>
                <div class="wa-detail-field"><dt>สถานะ</dt><dd>{{ $round->status }}</dd></div>
                <div class="wa-detail-field"><dt>วันที่ค้นหา</dt><dd>{{ $round->search_start_date->format('d/m/Y') }} – {{ $round->search_end_date->format('d/m/Y') }}</dd></div>
                <div class="wa-detail-field"><dt>เวลาในแต่ละวัน</dt><dd>{{ substr($round->daily_start_time, 0, 5) }} – {{ substr($round->daily_end_time, 0, 5) }}</dd></div>
                <div class="wa-detail-field"><dt>ระยะเวลานัด</dt><dd>{{ $round->duration_minutes }} นาที</dd></div>
                <div class="wa-detail-field"><dt>ขั้นตอนปัจจุบัน</dt><dd>{{ ['join' => 'รับสมาชิก', 'review' => 'ตรวจข้อมูล', 'voting' => 'โหวต', 'final' => 'สรุปผล'][$round->phase] ?? $round->phase }}</dd></div>
                <div class="wa-detail-field" style="grid-column:1 / -1;"><dt>เงื่อนไขอาจารย์</dt><dd>@if ($round->professor_rule === 'all') อาจารย์ทุกคนในรอบ @else อย่างน้อย {{ $round->min_professors }} คน @endif</dd></div>
            </dl>
        </section>
        <aside class="wa-panel" aria-labelledby="round-milestones-title">
            <h2 class="wa-panel-title" id="round-milestones-title">กำหนดการ</h2>
            <p class="wa-muted small">เวลาของแต่ละขั้นตอนในรอบ</p>
            <div class="wa-steps">
                @foreach ([
                    'join_starts_at' => 'เริ่มให้เข้าร่วม',
                    'review_starts_at' => 'ตรวจตัวเลือก',
                    'voting_starts_at' => 'เริ่มโหวต',
                    'final_starts_at' => 'สรุปผล',
                ] as $field => $label)
                    <div class="wa-step"><span class="wa-step-index"><i class="bi bi-clock" aria-hidden="true"></i></span><span><strong class="d-block">{{ $label }}</strong><span class="wa-muted">{{ $round->$field->format('d/m/Y H:i') }} น.</span></span></div>
                @endforeach
            </div>
        </aside>
    </div>

    <section class="wa-panel mb-4" aria-labelledby="round-actions-title">
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
            <div><h2 class="wa-panel-title mb-1" id="round-actions-title">การเข้าร่วมรอบ</h2><p class="wa-muted small mb-0">ตรวจสถานะและจัดการข้อมูลตารางเวลาของคุณ</p></div>
            <i class="bi bi-person-check fs-4 text-success" aria-hidden="true"></i>
        </div>
        @if ($round->snapshot_taken_at)
            <div class="wa-stat mb-3"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>ดึงข้อมูลเวลาไม่ว่างแล้ว เมื่อ {{ $round->snapshot_taken_at->format('d/m/Y H:i') }} น.</span></div>
        @elseif ($round->phase !== 'join' || now()->gte($round->review_starts_at))
            <div class="wa-notice mb-3">กำลังรวบรวมตารางเวลา</div>
        @endif
        @auth
            <div class="mb-3"><a href="{{ route('activities.index') }}" class="btn btn-outline-success"><i class="bi bi-calendar-week me-1" aria-hidden="true"></i>ตารางของฉัน</a></div>
        @endauth
        @if ($hasJoined && $round->status === 'active')
            <div class="wa-stat"><i class="bi bi-check-circle" aria-hidden="true"></i><span>คุณเข้าร่วมรอบนี้แล้ว</span></div>
        @elseif ($canJoin)
            <form method="POST" action="{{ route('rooms.rounds.members.store', ['room' => $room, 'round' => $round]) }}">
                @csrf
                <button type="submit" class="btn btn-success"><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>เข้าร่วมรอบนี้</button>
            </form>
        @elseif ($round->status !== 'cancelled')
            <p class="wa-muted mb-0">ไม่สามารถเข้าร่วมได้ กรุณาตรวจสอบช่วงเวลาเปิดรับและสถานะสมาชิกห้อง</p>
        @endif
        @if ($round->cancelled_at)
            <p class="wa-muted small mt-3 mb-0">ยกเลิกเมื่อ {{ $round->cancelled_at }}</p>
        @endif
        @auth
            @if ((int) auth()->id() === (int) $room->owner_id && $round->status === 'active')
                <hr class="my-4">
                <h3 class="h6 fw-semibold">เครื่องมือเจ้าของห้อง</h3>
                <div class="wa-action-row mt-3">
                    <a href="{{ route('rooms.rounds.members.edit', [$room, $round]) }}" class="btn btn-outline-success">แก้ไขสมาชิกในรอบ</a>
                    <a href="{{ route('rooms.rounds.reset.edit', [$room, $round]) }}" class="btn btn-outline-secondary">รีเซ็ตรอบกลับขั้นตอนเข้าร่วม</a>
                    <form method="POST" action="{{ route('rooms.rounds.cancel', ['room' => $room, 'round' => $round]) }}" onsubmit="return confirm('ต้องการยกเลิกรอบนี้หรือไม่?')">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-outline-danger">ยกเลิกรอบ</button>
                    </form>
                </div>
            @endif
        @endauth
    </section>

    @if ($roundMembers !== null)
        <section class="wa-panel" aria-labelledby="round-members-title">
            <h2 class="wa-panel-title mb-1" id="round-members-title">สมาชิกในรอบ ({{ $roundMembers->total() }})</h2>
            <p class="wa-muted small">แสดงช่วงเวลาที่ไม่ว่างตามเงื่อนไขและขั้นตอนปัจจุบัน</p>
            @forelse ($roundMembers as $roundMember)
                <div class="wa-member-row align-items-start">
                    <div class="w-100">
                        <div class="fw-semibold account-name">{{ $roundMember->roomMember->user->name }}</div>
                        <div class="wa-muted small mt-1">{{ $roundMember->role === 'professor' ? 'อาจารย์' : 'นักศึกษา' }} · น้ำหนัก {{ $roundMember->weight }} · เข้าร่วมเมื่อ {{ $roundMember->joined_at->format('d/m/Y H:i') }}</div>
                        <div class="mt-2">@include('rooms.rounds.busy-periods', ['participant' => $roundMember])</div>
                    </div>
                </div>
            @empty
                <p class="wa-muted mb-0">ยังไม่มีสมาชิกเข้าร่วมรอบนี้</p>
            @endforelse
            <div class="mt-3">{{ $roundMembers->links('pagination::bootstrap-5') }}</div>
        </section>
    @endif
</div>
@endsection
