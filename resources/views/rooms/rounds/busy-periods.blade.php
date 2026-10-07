@php
    $preview = $round->phase === 'join' && now()->lt($round->review_starts_at);
    $periods = $preview
        ? \App\Models\UserBusySlot::periodsFor($round, $participant->roomMember->user_id)
        : $participant->busyPeriods()->orderBy('start_at')->get();
@endphp
@if (!$preview && $round->snapshot_taken_at === null)
    <p class="text-secondary">กำลังรวบรวมตาราง</p>
@else
    @if (!$preview)
        <p class="small text-secondary">ข้อมูลนี้ถูกดึงเมื่อ {{ $round->snapshot_taken_at->format('d/m/Y H:i') }}</p>
    @endif
    <ul class="small">
        @forelse ($periods as $period)
            <li>{{ $period['start_at']->format('d/m/Y H:i') }} – {{ $period['end_at']->format('d/m/Y H:i') }}</li>
        @empty
            <li>{{ $preview ? 'คุณโชคที่ไม่มีกิจกรรมในช่วงรอบนี้เลย หรือว่าคุณลืม !! อะไรไป~' : 'ไม่มีช่วงไม่ว่างในกรอบค้นหา (ถือว่าว่างตลอดกรอบค้นหา)' }}</li>
        @endforelse
    </ul>
@endif
