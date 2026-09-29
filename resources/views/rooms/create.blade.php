@extends('layouts.rooms')
@section('title', 'สร้างห้อง')
@section('content')
    <div class="form-page mx-auto">
        <a href="{{ route('dashboard') }}" class="text-success">← กลับ Dashboard</a>
        <h1 class="h2 mt-4">สร้างห้องใหม่</h1>
        <p class="text-secondary">คุณจะเป็นเจ้าของและสมาชิกของห้องโดยอัตโนมัติ</p>
        <form method="POST" action="{{ route('rooms.store') }}" class="card rounded-4 p-4 mt-4">
            @csrf
            <div class="mb-3">
                <label for="name" class="form-label">ชื่อห้อง</label>
                <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" maxlength="150" required>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">รายละเอียด (ไม่บังคับ)</label>
                <textarea id="description" name="description" rows="4" maxlength="2000" class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-4">
                <label for="visibility" class="form-label">การมองเห็น</label>
                <select id="visibility" name="visibility" class="form-select @error('visibility') is-invalid @enderror" required>
                    <option value="private" @selected(old('visibility', 'private') === 'private')>Private — เฉพาะสมาชิก</option>
                    <option value="public" @selected(old('visibility', 'private') === 'public')>Public — ทุกคนดูข้อมูลทั่วไปได้</option>
                </select>
                @error('visibility') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <button class="btn btn-success" type="submit">สร้างห้อง</button>
        </form>
    </div>
@endsection
