@extends('layouts.rooms')
@section('title', 'ตารางของฉัน')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/schedule-ui.css') }}">
@endpush
@section('content')
<div class="schedule-page">
    <div class="schedule-topline">
        <a class="schedule-back" href="{{ route('home') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> กลับหน้าหลัก</a>
        <span class="schedule-eyebrow">ตารางเวลาส่วนตัว</span>
    </div>

    <header class="schedule-heading">
        <div>
            <h1>ตารางของฉัน</h1>
            <p>จัดการช่วงเวลาที่คุณไม่สะดวก ระบบจะนำเวลาเหล่านี้ไปใช้กับรอบนัดหมายที่คุณเข้าร่วมโดยอัตโนมัติ โดยไม่เปิดเผยชื่อกิจกรรมส่วนตัว</p>
        </div>
        <span class="schedule-heading-icon" aria-hidden="true"><i class="bi bi-calendar-week"></i></span>
    </header>

    <section class="schedule-panel schedule-week-calendar" aria-labelledby="calendar-title">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h2 id="calendar-title" class="h5 mb-1">ปฏิทินรายสัปดาห์</h2>
                <p class="small text-secondary mb-0">
                    {{ $weekStart->format('d/m/Y') }} – {{ $weekEnd->subDay()->format('d/m/Y') }}
                </p>
            </div>
            <nav class="d-flex flex-wrap gap-2" aria-label="เลือกสัปดาห์">
                <a class="btn btn-outline-success btn-sm"
                   aria-label="สัปดาห์ก่อนหน้า"
                   href="{{ route('activities.index', ['week' => $weekStart->subWeek()->toDateString()]) }}">
                    ← ก่อนหน้า
                </a>
                <a class="btn btn-success btn-sm" href="{{ route('activities.index') }}">สัปดาห์นี้</a>
                <a class="btn btn-outline-success btn-sm"
                   aria-label="สัปดาห์ถัดไป"
                   href="{{ route('activities.index', ['week' => $weekStart->addWeek()->toDateString()]) }}">
                    ถัดไป →
                </a>
            </nav>
        </div>

        <div class="calendar-scroll" tabindex="0" role="region" aria-label="ปฏิทินกิจกรรมรายสัปดาห์">
            <table class="calendar-table">
                <colgroup>
                    @for ($column = 0; $column < 7; $column++)
                        <col style="width: {{ 100 / 7 }}%">
                    @endfor
                </colgroup>
                <thead>
                    <tr>
                        @for ($column = 0; $column < 7; $column++)
                            @php
                                $calendarDay = $weekStart->addDays($column);
                            @endphp
                            <th scope="col">
                                {{ $calendarDay->format('D') }}
                                <small class="d-block">{{ $calendarDay->format('d/m') }}</small>
                            </th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @forelse ($calendarActivities as $calendarActivity)
                        @php
                            // จำกัดแถบให้อยู่ใน 7 วันของสัปดาห์ที่เลือก
                            $startsBefore = $calendarActivity->start_at->lt($weekStart);
                            $endsAfter = $calendarActivity->end_at->gt($weekEnd);
                            $startColumn = $startsBefore ? 0 : $calendarActivity->start_at->dayOfWeekIso - 1;
                            // เวลาสิ้นสุด 00:00 ไม่ถือว่ากินพื้นที่วันใหม่
                            $endColumn = $calendarActivity->end_at->gte($weekEnd)
                                ? 6
                                : $calendarActivity->end_at->copy()->subMicrosecond()->dayOfWeekIso - 1;
                            $span = $endColumn - $startColumn + 1;
                        @endphp
                        <tr>
                            @for ($column = 0; $column < $startColumn; $column++)
                                <td></td>
                            @endfor
                            <td colspan="{{ $span }}">
                                <a class="activity-box" href="{{ route('activities.edit', $calendarActivity) }}">
                                    <strong>{{ $calendarActivity->name }}</strong>
                                    <small>
                                        {{ $calendarActivity->start_at->format('d/m/Y H:i') }}
                                        – {{ $calendarActivity->end_at->format('d/m/Y H:i') }}
                                    </small>
                                    @if ($startsBefore)
                                        <small>← ต่อจากสัปดาห์ก่อน</small>
                                    @endif
                                    @if ($endsAfter)
                                        <small>ต่อสัปดาห์ถัดไป →</small>
                                    @endif
                                </a>
                            </td>
                            @for ($column = $endColumn + 1; $column < 7; $column++)
                                <td></td>
                            @endfor
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-5">ยังไม่มีกิจกรรมในสัปดาห์นี้</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="schedule-grid">
        <section class="schedule-panel schedule-create" aria-labelledby="schedule-create-heading">
            <div class="schedule-panel-heading">
                <span class="schedule-panel-icon"><i class="bi bi-plus-lg" aria-hidden="true"></i></span>
                <div>
                    <h2 id="schedule-create-heading">เพิ่มเวลาที่ไม่สะดวก</h2>
                    <p>ระบุชื่อกิจกรรมและช่วงเวลา</p>
                </div>
            </div>
            <form method="POST" action="{{ route('activities.store') }}" class="schedule-form">
                @csrf
                @include('activities.fields')
                <button class="btn btn-success schedule-submit" type="submit"><i class="bi bi-plus-circle me-2" aria-hidden="true"></i>เพิ่มกิจกรรม</button>
            </form>
            <div class="schedule-tip"><i class="bi bi-shield-lock me-2" aria-hidden="true"></i>ชื่อกิจกรรมของคุณจะไม่ถูกแสดงให้สมาชิกคนอื่นเห็น</div>
        </section>

        <section class="schedule-panel schedule-events" aria-labelledby="schedule-events-heading">
            <div class="schedule-panel-heading schedule-panel-heading-spread">
                <div>
                    <h2 id="schedule-events-heading">รายการกิจกรรมของฉัน</h2>
                    <p>ตรวจสอบ แก้ไข หรือลบกิจกรรมที่บันทึกไว้</p>
                </div>
                <span class="schedule-section-label">รายการที่บันทึก</span>
            </div>
            <div class="schedule-event-list">
                @forelse ($activities as $activity)
                    <article class="schedule-event">
                        <div class="schedule-event-main">
                            <span class="schedule-event-symbol" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
                            <div class="schedule-event-info">
                                <h3>{{ $activity->name }}</h3>
                                <p><i class="bi bi-calendar3 me-1" aria-hidden="true"></i>
                                    @if ($activity->start_at->isSameDay($activity->end_at))
                                        {{ $activity->start_at->format('d/m/Y') }} · {{ $activity->start_at->format('H:i') }}–{{ $activity->end_at->format('H:i') }} น.
                                    @else
                                        {{ $activity->start_at->format('d/m/Y H:i') }} – {{ $activity->end_at->format('d/m/Y H:i') }} น.
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="schedule-event-actions">
                            <a class="btn btn-outline-success btn-sm" href="{{ route('activities.edit', $activity) }}"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>แก้ไข</a>
                            <form method="POST" action="{{ route('activities.destroy', $activity) }}" onsubmit="return confirm('ลบกิจกรรมนี้?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash me-1" aria-hidden="true"></i>ลบ</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="schedule-empty">
                        <span class="schedule-empty-icon" aria-hidden="true"><i class="bi bi-calendar2-check"></i></span>
                        <h3>ยังไม่มีกิจกรรม</h3>
                        <p>เพิ่มช่วงเวลาที่คุณไม่สะดวกจากฟอร์มด้านซ้ายเพื่อเริ่มจัดการตารางของคุณ</p>
                    </div>
                @endforelse
            </div>
            <div class="schedule-pagination">{{ $activities->links('pagination::bootstrap-5') }}</div>
        </section>
    </div>
</div>
@endsection
