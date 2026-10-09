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
<div>
    @foreach (['start_at' => 'เริ่ม', 'end_at' => 'สิ้นสุด'] as $field => $label)
        <div class="mb-3">
            <label for="{{ $field }}" class="form-label">{{ $label }}</label>
            <input id="{{ $field }}" name="{{ $field }}" type="datetime-local" required class="form-control"
                value="{{ old($field, isset($activity) ? $activity->$field->format('Y-m-d\TH:i') : '') }}">
        </div>
    @endforeach
</div>
