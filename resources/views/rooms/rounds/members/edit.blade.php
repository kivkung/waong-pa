@extends('layouts.rooms')

@section('title', 'แก้ไขสมาชิกในรอบ')

@section('content')
    <div class="form-page mx-auto">
        <a href="{{ route('rooms.rounds.show', [$room, $round]) }}" class="text-success">← กลับรอบ</a>
        <section class="card rounded-4 p-4 mt-4">
            <h1 class="h3">แก้ไขสมาชิกในรอบที่ {{ $round->round_no }}</h1>
            <p class="room-description text-secondary">ห้อง: {{ $room->name }}</p>
            <p class="small text-secondary">ติ๊กเพื่อให้อยู่ในรอบ เอาติ๊กออกเพื่อนำออกจากรอบ ผู้ใช้ยังกลับเข้าร่วมเองได้ในช่วง Join</p>
            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form method="POST" action="{{ route('rooms.rounds.members.update', [$room, $round]) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="selection_submitted" value="1">
                <fieldset>
                    <legend class="h5">เลือกสมาชิก</legend>
                    @forelse ($members as $member)
                        @php($joined = in_array($member->id, $joinedIds))
                        <label class="d-flex align-items-center gap-3 border-bottom py-3" for="member-{{ $member->id }}">
                            <input id="member-{{ $member->id }}" type="checkbox" name="member_ids[]"
                                value="{{ $member->id }}" class="form-check-input flex-shrink-0 mt-0"
                                @checked(in_array($member->id, (array) (old('selection_submitted') ? old('member_ids', []) : $joinedIds)))>
                            <span class="account-name flex-grow-1">{{ $member->user->name }}</span>
                            @if ($joined)
                                <span class="badge text-bg-success">อยู่ในรอบแล้ว</span>
                            @endif
                        </label>
                    @empty
                        <p class="text-secondary">ไม่มีสมาชิกให้เลือก</p>
                    @endforelse
                </fieldset>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button class="btn btn-success" type="submit">บันทึกสมาชิกในรอบ</button>
                    <a class="btn btn-outline-secondary" href="{{ route('rooms.rounds.show', [$room, $round]) }}">ยกเลิก</a>
                </div>
            </form>
        </section>
    </div>
@endsection
