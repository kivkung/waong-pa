@extends('layouts.rooms')
@section('title', 'แก้ไขกิจกรรมส่วนตัว')
@section('content')
<div class="form-page mx-auto">
    <a href="{{ route('activities.index') }}">← กิจกรรมส่วนตัว</a>
    <h1 class="h3 mt-3">แก้ไขกิจกรรม</h1>
    <form method="POST" action="{{ route('activities.update', $activity) }}" class="card p-4">
        @csrf @method('PATCH')
        @include('activities.fields')
        <button class="btn btn-success">บันทึก</button>
    </form>
</div>
@endsection
