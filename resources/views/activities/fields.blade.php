@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<div class="mb-3">
    <label for="name" class="form-label">ชื่อกิจกรรม <span class="schedule-optional">(เห็นเฉพาะคุณ)</span></label>
    <input id="name" name="name" class="form-control" maxlength="150" required placeholder="เช่น เรียน Web Application" value="{{ old('name', isset($activity) ? $activity->name : '') }}">
</div>
@php
    $frequency = old('frequency', isset($activity) ? ($activity->recurrenceRule->frequency ?? 'none') : 'none');
@endphp
<div class="mb-3">
    <label for="frequency" class="form-label">การเกิดซ้ำ</label>
    <select id="frequency" name="frequency" class="form-select" data-recurrence-count="occurrence_count">
        <option value="none" @selected($frequency === 'none')>ไม่ซ้ำ</option>
        <option value="weekly" @selected($frequency === 'weekly')>ทุกสัปดาห์</option>
        <option value="monthly" @selected($frequency === 'monthly')>ทุกเดือน</option>
    </select>
</div>
<div class="mb-3">
    <label for="occurrence_count" class="form-label">จำนวนครั้งทั้งหมด</label>
    <input id="occurrence_count" name="occurrence_count" type="number" min="2" step="1" class="form-control"
        aria-describedby="recurrence-help"
        value="{{ old('occurrence_count', isset($activity) ? ($activity->recurrenceRule->occurrence_count ?? '') : '') }}" @disabled($frequency === 'none') @required($frequency !== 'none')>
    <p id="recurrence-help" class="small text-secondary mt-2">กรอกเมื่อเลือกกิจกรรมซ้ำ อย่างน้อย 2 ครั้ง และนับรวมครั้งแรกด้วย เช่น 5 ครั้ง = ครั้งแรกและอีก 4 ครั้ง หากวันนั้นไม่มีในเดือนถัดไป จะใช้วันสุดท้ายของเดือน</p>
</div>
<div>
    @foreach (['start_at' => 'เริ่ม', 'end_at' => 'สิ้นสุด'] as $field => $label)
        <div class="mb-3">
            <label for="{{ $field }}" class="form-label">{{ $label }}</label>
            <input id="{{ $field }}" name="{{ $field }}" type="datetime-local" required class="form-control"
                value="{{ old($field, isset($activity) ? $activity->$field->format('Y-m-d\TH:i') : '') }}">
        </div>
    @endforeach
</div>
