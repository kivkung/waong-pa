@extends('layouts.rooms')
@section('title', $room->name)
@section('content')
    <div class="form-page mx-auto">
        <div class="d-flex flex-wrap justify-content-between align-middle gap-2">
            <a href="{{ route('dashboard') }}" class="text-success">← กลับ Dashboard</a>
            <button class="bi bi-gear-fill btn btn btn-outline-secondary"> ตั้งค่า</button>
        </div>
        <article class="card rounded-4 p-4 p-md-5 mt-4">
            <div class="d-inline-flex gap-3">
                <div><span class="badge text-bg-success">{{ $room->visibility === 'public' ? 'Public' : 'Private' }}</span>
                </div>
                <div><span class="badge bg-warning text-dark">Create at : {{ $room->created_at }}</span></div>
            </div>

            <h1 class="h2 mt-3 room-description">{{ $room->name }}</h1>
            <p class="room-description text-secondary">{{ $room->description ?: 'ยังไม่มีรายละเอียด' }}</p>
            @if ($isOwner)
                <div class="alert alert-success mt-3">
                    <p class="fw-bold mb-2">คุณเป็นเจ้าของห้อง</p>
                    <p class="mb-1">รหัสสำหรับเชิญสมาชิก</p>
                    <code class="fs-5">{{ $room->join_code }}</code>
                    <p class="small mb-0 mt-2">ส่งรหัสนี้ให้คนที่คุณต้องการเชิญเข้าห้อง</p>
                </div>
                <a class="btn btn-success" href="{{ route('rooms.rounds.create', $room) }}">
                    สร้างรอบนัดหมาย
                </a>
            @elseif ($isMember)
                <p class="text-success fw-semibold mt-3">คุณเป็นสมาชิกห้องนี้แล้ว</p>
            @else
                @auth
                    <p>หากต้องการเข้าร่วม กรุณาขอรหัสจากเจ้าของห้อง</p>
                    <a class="btn btn-success" href="{{ route('rooms.join') }}">กรอกรหัสเข้าห้อง</a>
                @else
                    <a class="btn btn-success" href="{{ route('login') }}">เข้าสู่ระบบเพื่อเข้าร่วมห้อง</a>
                @endauth
            @endif
        </article>

        
        <div class="list-group list-group-flush card rounded-4 p-4 mt-4">
            <h2 class="h4">รอบนัดหมาย</h2>
            <p class="text-secondary small">คลิ๊กเพื่อเพื่อดูรายละเอียดรอบ</p>
            @forelse ($rounds as $round)
                <a href="{{ route('rooms.rounds.show', compact('room', 'round')) }}"
                    class="list-group-item list-group-item-action px-0 py-3 card rounded-4 p-4 mb-2 transition-card round-card">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
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
                    <div class="small text-secondary mt-2">
                        {{ $round->search_start_date->format('d/m/Y') }}
                        –
                        {{ $round->search_end_date->format('d/m/Y') }}
                        · {{ $round->duration_minutes }} นาที
                        · เฟส {{ $round->phase }}
                    </div>

                </a>
            @empty
                <p class="text-secondary mb-0">ยังไม่มีรอบนัดหมาย</p>
            @endforelse
        </div>

        <div class="mt-3">
            {{ $rounds->links('pagination::bootstrap-5') }}
        </div>
        </section>


        @if ($members !== null)
            <section class="card rounded-4 p-4 mt-4" aria-labelledby="members-heading">
                <h2 class="h4" id="members-heading">สมาชิกในห้อง ({{ $members->total() }})</h2>
                <p class="text-secondary small">แสดงสมาชิกที่ยังอยู่ในห้อง บทบาทและน้ำหนักใช้สำหรับการหาเวลานัดในอนาคต</p>
                <ul class="list-group list-group-flush">
                    @foreach ($members as $member)
                        <li class="list-group-item px-0 py-3">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <div class="mw-100">
                                    <div class="head-name d-inline-flex">
                                        <div class="fw-semibold account-name me-2">{{ $member->user->name }}</div>
                                        @if ((int) $member->user_id === (int) $room->owner_id)
                                            <span class="badge text-bg-success me-2">เจ้าของห้อง</span>
                                        @endif
                                        @if ((int) $member->user_id === (int) auth()->id())
                                            <span class="badge text-bg-secondary me-2">คุณ</span>
                                        @endif
                                    </div>
                                    <div class="small text-secondary mt-1">
                                        {{ $member->role === 'professor' ? 'อาจารย์ (professor)' : 'นักศึกษา (student)' }}
                                        · น้ำหนัก {{ $member->weight }}
                                    </div>
                                </div>

                                <div class="d-inline-flex gap-3">
                                    @if ($isOwner)
                                        <a class="btn btn-outline-success btn-sm"
                                            href="{{ route('rooms.members.edit', [$room, $member]) }}">
                                            แก้ไข<span class="visually-hidden">บทบาทและน้ำหนักของ {{ $member->user->name }}</span>
                                        </a>
                                    @endif

                                    @if (((int) $member->user_id === (int) auth()->id() || $isOwner) && ((int) $member->user_id != (int) $room->owner_id))
                                        @php($locked = $room->rounds()->where('status', 'active')->whereHas('members', fn ($q) => $q->where('room_member_id', $member->id))->get()->contains(fn ($r) => $r->membershipLocked()))
                                        @if ($locked)
                                            <span class="text-secondary">{{ $isOwner ? 'รีเซ็ตรอบกลับ Join ก่อนนำสมาชิกออก' : 'ติดต่อเจ้าของห้องแทน' }}</span>
                                        @else
                                        <form method="POST"
                                            action="{{ route('room.members.left', ['room' => $room, 'member' => $member]) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                {{ (int) $member->user_id === (int) auth()->id() ? 'ออกจากห้อง' : 'นำสมาชิกออก' }}
                                            </button>
                                        </form>
                                        @endif
                                    @endif
                                </div>

                            </div>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-3">{{ $members->links('pagination::bootstrap-5') }}</div>
            </section>
        @endif
    </div>
@endsection
