@extends('layouts.rooms')

@section('title', 'รายละเอียดรอบ')

@section('content')
    <div class="form-page mx-auto gap-1">
        <a href="{{ route('rooms.show', compact('room')) }}" class="text-success">← กลับห้อง</a>

        <h1 class="mt-3">รอบที่ {{ $round->round_no }}</h1>
        <p>ห้อง: {{ $room->name }}</p>
        <p>เริ่ม Join: {{ $round->join_starts_at->format('d/m/Y H:i') }}</p>
    </div>
@endsection