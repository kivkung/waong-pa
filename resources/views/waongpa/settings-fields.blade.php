<div class="grid two">
<label>วันที่เริ่มค้นหา<input type="date" wire:model="settings.search_start_date" required></label>
<label>วันที่สิ้นสุดค้นหา<input type="date" wire:model="settings.search_end_date" required></label>
<label>เวลาเริ่มของวัน<input type="time" step="1800" wire:model="settings.daily_start_time" required></label>
<label>เวลาสิ้นสุดของวัน<input type="time" step="1800" wire:model="settings.daily_end_time" required></label>
<label>ระยะนัดเป็นนาที<input type="number" min="30" max="600" step="30" wire:model="settings.duration_minutes" required></label>
<label>อาจารย์ที่ต้องว่าง<select wire:model="settings.professor_rule"><option value="at_least">อย่างน้อยตามจำนวนที่ระบุ</option><option value="all">อาจารย์ทุกคน</option></select></label>
<label>จำนวนอาจารย์ขั้นต่ำ<input type="number" min="1" max="100" wire:model="settings.min_professors"><small>ไม่ใช้ค่านี้เมื่อเลือกอาจารย์ทุกคน</small></label>
@foreach(['join'=>'เริ่ม Join','review'=>'เริ่ม Review','voting'=>'เริ่ม Voting','final'=>'เริ่ม Final'] as $key=>$label)
<label>{{ $label }}<input type="datetime-local" wire:model="settings.{{ $key }}_starts_at" required></label>
@endforeach
</div>
