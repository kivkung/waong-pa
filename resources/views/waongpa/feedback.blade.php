@if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="error" role="alert"><strong>ยังทำรายการไม่สำเร็จ</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div wire:loading.delay role="status" class="loading">กำลังดำเนินการ…</div>
<div wire:offline class="error">ขาดการเชื่อมต่อ กรุณาตรวจอินเทอร์เน็ตแล้วลองใหม่</div>
