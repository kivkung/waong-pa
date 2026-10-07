@if ($errors->any())
    <div class="alert alert-danger" role="alert"><ul>
        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul></div>
@endif
<label for="name" class="form-label">ชื่อกิจกรรม (แสดงเฉพาะคุณ)</label>
<input id="name" name="name" class="form-control mb-3" maxlength="150" required value="{{ old('name', isset($activity) ? $activity->name : '') }}">
@foreach (['start_at' => 'เริ่ม', 'end_at' => 'สิ้นสุด'] as $field => $label)
    <label for="{{ $field }}" class="form-label">{{ $label }}</label>
    <input id="{{ $field }}" name="{{ $field }}" type="datetime-local" required class="form-control mb-3"
        value="{{ old($field, isset($activity) ? $activity->$field->format('Y-m-d\TH:i') : '') }}">
@endforeach
