@extends('layouts.rooms')
@section('title', 'แก้ไขสมาชิกในรอบ')
@section('content')
<div class="wa-page" style="max-width:850px">
    <a class="wa-back" href="{{ route('rooms.rounds.show', [$room, $round]) }}"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>กลับรอบนัดหมาย</a>
    <div class="wa-page-header mt-3"><div><span class="wa-kicker">จัดการสมาชิก</span><h1>สมาชิกในรอบที่ {{ $round->round_no }}</h1><p class="room-description">ห้อง: {{ $room->name }}</p></div></div>
    <section class="wa-panel">
        <p class="wa-notice mb-4">เลือกสมาชิกที่ต้องการให้อยู่ในรอบ หรือนำเครื่องหมายถูกออกเพื่อนำออกจากรอบ ผู้ใช้ยังกลับเข้าร่วมเองได้ในช่วงเปิดรับสมาชิก</p>
        @if ($errors->any())
            <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form method="POST" action="{{ route('rooms.rounds.members.update', [$room, $round]) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="selection_submitted" value="1">
            <fieldset>
                <legend class="wa-panel-title mb-2">เลือกสมาชิก</legend>
                @forelse ($members as $member)
                    @php($joined = in_array($member->id, $joinedIds))
                    <label class="d-flex align-items-center gap-3 border-bottom py-3" for="member-{{ $member->id }}">
                        <input id="member-{{ $member->id }}" type="checkbox" name="member_ids[]" value="{{ $member->id }}" class="form-check-input flex-shrink-0 mt-0"
                            @checked(in_array($member->id, (array) (old('selection_submitted') ? old('member_ids', []) : $joinedIds)))>
                        <span class="account-name flex-grow-1">{{ $member->user->name }}</span>
                        @if ($joined)<span class="badge text-bg-success">อยู่ในรอบแล้ว</span>@endif
                    </label>
                @empty
                    <p class="wa-muted">ไม่มีสมาชิกให้เลือก</p>
                @endforelse
            </fieldset>
            <div class="wa-action-row mt-4"><button class="btn btn-success" type="submit">บันทึกสมาชิกในรอบ</button><a class="btn btn-outline-secondary" href="{{ route('rooms.rounds.show', [$room, $round]) }}">ยกเลิก</a></div>
        </form>
    </section>
</div>
@endsection
