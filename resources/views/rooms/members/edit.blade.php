@extends('layouts.rooms')
@section('title', 'แก้ไขสมาชิก')
@section('content')
<div class="wa-page" style="max-width:790px">
    <a class="wa-back" href="{{ route('rooms.show', $room) }}"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>กลับห้อง</a>
    <div class="wa-page-header mt-3"><div><span class="wa-kicker">จัดการสมาชิก</span><h1>แก้ไขบทบาทและน้ำหนัก</h1><p>สมาชิก: {{ $member->user->name }}</p></div></div>
    <section class="wa-panel">
        <p class="wa-notice mb-4">บทบาทนักศึกษา/อาจารย์แยกจากความเป็นเจ้าของห้อง การเปลี่ยนบทบาทจะไม่เปลี่ยนเจ้าของห้อง</p>
        <form method="POST" action="{{ route('rooms.members.update', [$room, $member]) }}">
            @csrf @method('PATCH')
            <div class="mb-4">
                <label class="form-label" for="role">บทบาท</label>
                <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required @error('role') aria-invalid="true" aria-describedby="role-error" @enderror>
                    <option value="student" @selected(old('role', $member->role) === 'student')>นักศึกษา</option>
                    <option value="professor" @selected(old('role', $member->role) === 'professor')>อาจารย์</option>
                </select>
                @error('role')<div class="invalid-feedback" id="role-error">{{ $message }}</div>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label" for="weight">น้ำหนัก</label>
                <input class="form-control @error('weight') is-invalid @enderror" id="weight" name="weight" type="number" min="0.01" max="100" step="0.01" value="{{ old('weight', $member->weight) }}" required aria-describedby="weight-help @error('weight') weight-error @enderror" @error('weight') aria-invalid="true" @enderror>
                <div class="form-text" id="weight-help">ค่าเริ่มต้น 1 กำหนดได้ตั้งแต่ 0.01–100 ทศนิยมไม่เกิน 2 ตำแหน่ง</div>
                @error('weight')<div class="invalid-feedback" id="weight-error">{{ $message }}</div>@enderror
            </div>
            <div class="wa-action-row"><button class="btn btn-success" type="submit">บันทึกการเปลี่ยนแปลง</button><a class="btn btn-outline-secondary" href="{{ route('rooms.show', $room) }}">ยกเลิก</a></div>
        </form>
    </section>
</div>
@endsection
