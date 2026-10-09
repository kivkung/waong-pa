@extends('layouts.rooms')
@section('title', $room->name)
@section('content')
<div class="wa-page">
    <div class="wa-page-header">
        <div>
            <a href="{{ route('dashboard') }}" class="wa-back"><i class="bi bi-arrow-left" aria-hidden="true"></i> กลับหน้าหลัก</a>
            <div class="mt-3"><span class="wa-kicker">รายละเอียดห้องนัดหมาย</span></div>
            <h1 class="room-description">{{ $room->name }}</h1>
            <p class="room-description">{{ $room->description ?: 'ยังไม่มีรายละเอียดห้องนัดหมาย' }}</p>
        </div>
        <span class="badge {{ $room->visibility === 'public' ? 'text-bg-success' : 'text-bg-secondary' }} align-self-start mt-2">
            <i class="bi {{ $room->visibility === 'public' ? 'bi-globe2' 
            
            : 'bi-lock' }} me-1" aria-hidden="true"></i>
            {{ $room->visibility === 'public' ? 'สาธารณะ' : 'ส่วนตัว' }}
        </span>
    </div>
    <div class="wa-grid mb-4">
        <section class="wa-panel" aria-labelledby="room-overview-title">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="wa-panel-title" id="room-overview-title">ข้อมูลห้อง</h2>
                    <p class="wa-muted small mb-0">ภาพรวมและการเข้าร่วม</p>
                </div>
                    @if ($isOwner)
                        <form method="POST" action="{{ route('rooms.destroy', ['room' => $room]) }}" onsubmit="return confirm('ต้องการลบห้องนี้หรือไม่?')">
                            @csrf
                            @method('DELETE')
                            <div class="d-flex align-items-center">
                                <button type="submit" class="btn btn-outline-danger mx-3">ลบห้อง</button>
                                <i class="bi bi-door-open fs-4 text-success" aria-hidden="true"></i>
                            </div>
                        </form>
                    @endif
            </div>
            <dl class="wa-detail-grid mb-3">
                <div class="wa-detail-field">
                    <dt>ประเภทห้อง</dt>
                    <dd>{{ $room->visibility === 'public' ? 'ห้องสาธารณะ' : 'ห้องส่วนตัว' }}</dd>
                </div>
                <div class="wa-detail-field">
                    <dt>วันที่สร้าง</dt>
                    <dd>{{ $room->created_at->format('d/m/Y') }}</dd>
                </div>
            </dl>
            @if ($isOwner)
                <div class="wa-notice mb-3">
                    <div class="fw-semibold mb-1"><i class="bi bi-shield-check me-1" aria-hidden="true"></i> คุณเป็นเจ้าของห้อง</div>
                    <div class="small mb-2">ส่งรหัสเชิญให้ผู้ที่ต้องการเข้าร่วมห้อง</div>
                    <div class="d-flex justify-content-between">
                        <code class="d-inline-block fs-5 text-success bg-white border rounded-3 px-3 py-2 user-select-all">{{ $room->join_code }}</code>
                        <button class="btn border btn-outline-success"
                            value="{{$room->join_code}}"
                            id="join-code" readonly
                            onclick="navigator.clipboard.writeText(document.getElementById('join-code').value)"
                            >
                            คัดลอก
                        </button>
                    </div>
                </div>
                <a class="btn btn-success" href="{{ route('rooms.rounds.create', $room) }}">
                    <i class="bi bi-plus-circle me-1" aria-hidden="true"></i> สร้างรอบนัดหมาย
                </a>
            @elseif ($isMember)
                <div class="wa-stat"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <span>คุณเป็นสมาชิกห้องนี้แล้ว</span></div>
            @else
                @auth
                    <p class="wa-muted">ต้องการเข้าร่วมห้องนี้? ขอรหัสจากเจ้าของห้องก่อน</p>
                    <a class="btn btn-success" href="{{ route('rooms.join') }}">กรอกรหัสเข้าห้อง</a>
                @else
                    <p class="wa-muted">เข้าสู่ระบบก่อนเข้าร่วมห้อง</p>
                    <a class="btn btn-success" href="{{ route('login') }}">เข้าสู่ระบบ</a>
                @endauth
            @endif
        </section>
        <aside class="wa-panel wa-side-panel">
            <h2 class="wa-panel-title mb-3">การใช้งานห้องนี้</h2>
            <div class="wa-steps">
                <div class="wa-step"><span class="wa-step-index">01</span><span>สมาชิกเข้าร่วมห้องด้วยรหัสเชิญ</span></div>
                <div class="wa-step"><span class="wa-step-index">02</span><span>เจ้าของสร้างรอบและกำหนดช่วงเวลานัดหมาย</span></div>
                <div class="wa-step"><span class="wa-step-index">03</span><span>สมาชิกเข้าร่วมรอบ ระบบนำเวลาไม่ว่างส่วนตัวไปใช้ตามขั้นตอนของรอบ</span></div>
            </div>
            @auth
                <a href="{{ route('activities.index') }}" class="btn btn-outline-success btn-sm mt-3">
                    <i class="bi bi-calendar-week me-1" aria-hidden="true"></i> ไปตารางของฉัน
                </a>
            @endauth
        </aside>
    </div>

    <section class="wa-panel mb-4" aria-labelledby="round-list-title">
        <div class="d-flex justify-content-between gap-2 flex-wrap align-items-center mb-3">
            <div>
                <h2 class="wa-panel-title mb-1" id="round-list-title">รอบนัดหมาย</h2>
                <p class="wa-muted small mb-0">เลือกรอบเพื่อดูช่วงเวลาและสถานะการนัดหมาย</p>
            </div>
            <i class="bi bi-calendar3 text-success fs-4" aria-hidden="true"></i>
        </div>
        <div class="d-grid gap-2">
            @forelse ($rounds as $round)
                <a class="wa-round-card" href="{{ route('rooms.rounds.show', compact('room', 'round')) }}">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <strong>รอบที่ {{ $round->round_no }}</strong>
                        @switch($round->status)
                            @case('active')
                                <span class="badge text-bg-primary">กำลังดำเนินการ</span>
                                @break
                            @case('cancelled')
                                <span class="badge text-bg-danger">ยกเลิกแล้ว</span>
                                @break
                            @case('completed')
                                <span class="badge text-bg-success">เสร็จสิ้น</span>
                                @break
                            @default
                                <span class="badge text-bg-secondary">{{ $round->status }}</span>
                        @endswitch
                    </div>
                    <div class="small wa-muted mt-2"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>{{ $round->search_start_date->format('d/m/Y') }} – {{ $round->search_end_date->format('d/m/Y') }} · {{ $round->duration_minutes }} นาที · ขั้นตอน {{ ['join' => 'รับสมาชิก', 'review' => 'ตรวจข้อมูล', 'voting' => 'โหวต', 'final' => 'สรุปผล'][$round->phase] ?? $round->phase }}</div>
                </a>
            @empty
                <div class="text-center py-4">
                    <i class="bi bi-calendar2-plus fs-2 text-success" aria-hidden="true"></i>
                    <p class="wa-muted mb-0 mt-2">ยังไม่มีรอบนัดหมาย</p>
                </div>
            @endforelse
        </div>
        <div class="mt-3">{{ $rounds->links('pagination::bootstrap-5') }}</div>
    </section>

    @if ($members !== null)
        <section class="wa-panel" aria-labelledby="members-heading">
            <div class="mb-3">
                <h2 class="wa-panel-title mb-1" id="members-heading">สมาชิกในห้อง ({{ $members->total() }})</h2>
                <p class="wa-muted small mb-0">รายชื่อสมาชิกที่ยังอยู่ในห้อง บทบาทและน้ำหนักใช้ตามเงื่อนไขระบบ</p>
            </div>
            @foreach ($members as $member)
                <div class="wa-member-row">
                    <div class="min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <span class="wa-member-name account-name">{{ $member->user->name }}</span>
                            @if ((int) $member->user_id === (int) $room->owner_id)
                                <span class="badge text-bg-success">เจ้าของห้อง</span>
                            @endif
                            @if ((int) $member->user_id === (int) auth()->id())
                                <span class="badge text-bg-secondary">คุณ</span>
                            @endif
                        </div>
                        <div class="wa-muted small">{{ $member->role === 'professor' ? 'อาจารย์' : 'นักศึกษา' }} · น้ำหนัก {{ $member->weight }}</div>
                    </div>
                    <div class="wa-action-row">
                        @if ($isOwner)
                            <a href="{{ route('rooms.members.edit', [$room, $member]) }}" class="btn btn-outline-success btn-sm">แก้ไข<span class="visually-hidden">บทบาทและน้ำหนักของ {{ $member->user->name }}</span></a>
                        @endif
                        @if (((int) $member->user_id === (int) auth()->id() || $isOwner) && ((int) $member->user_id != (int) $room->owner_id))
                            @php($locked = $room->rounds()->where('status', 'active')->whereHas('members', fn ($q) => $q->where('room_member_id', $member->id))->get()->contains(fn ($r) => $r->membershipLocked()))
                            @if ($locked)
                                <span class="wa-muted small">{{ $isOwner ? 'รีเซ็ตรอบกลับ Join ก่อนนำสมาชิกออก' : 'ติดต่อเจ้าของห้องแทน' }}</span>
                            @else
                                <form method="POST" action="{{ route('room.members.left', ['room' => $room, 'member' => $member]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        {{ (int) $member->user_id === (int) auth()->id() ? 'ออกจากห้อง' : 'นำสมาชิกออก' }}
                                    </button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
            <div class="mt-3">{{ $members->links('pagination::bootstrap-5') }}</div>
        </section>
    @endif
</div>
@endsection
