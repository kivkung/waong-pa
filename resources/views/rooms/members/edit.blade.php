{{-- ใช้โครงหน้าจาก layouts.rooms; section title/content จะถูกวางในตำแหน่ง yield ของ layout --}}
@extends('layouts.rooms')
@section('title', 'แก้ไขสมาชิก')
@section('content')
    <div class="form-page mx-auto">
        <a href="{{ route('rooms.show', $room) }}" class="text-success">← กลับห้อง</a>
        <section class="card rounded-4 p-4 p-md-5 mt-4">
            <h1 class="h3">แก้ไขบทบาทและน้ำหนัก</h1>
            <p class="account-name">สมาชิก: {{ $member->user->name }}</p>
            <p class="small text-secondary">บทบาท student/professor แยกจากความเป็นเจ้าของห้อง การเปลี่ยนบทบาทไม่เปลี่ยนเจ้าของ</p>
            {{--
                การรับ error จาก backend ของหน้านี้:
                1. RoomMemberController::edit() ส่ง $room และ $member มาให้แสดงข้อมูลเดิม
                2. เมื่อกดบันทึก ฟอร์มส่ง role/weight ไปยัง RoomMemberController::update()
                3. หาก $request->validate() ไม่ผ่าน Laravel จะหยุดทำงานก่อนบันทึก
                   แล้ว redirect กลับหน้าฟอร์ม พร้อม flash errors และข้อมูลที่กรอกไว้ใน session
                4. เมื่อโหลดหน้านี้ใหม่ Laravel ทำให้เข้าถึง errors ผ่าน $errors
                   ส่วน old() อ่านข้อมูลที่กรอกครั้งก่อนจาก session
                5. error directive ด้านล่างตรวจชื่อฟิลด์ และให้ $message เป็นข้อความผิดพลาดแรก
                   เช่น weight.required ใน Controller ให้ข้อความ "กรุณากรอกน้ำหนัก"
                ถ้าตรวจผ่าน Controller จะบันทึกแล้ว redirect ไปหน้าห้อง ไม่กลับมาหน้านี้

                คอมเมนต์ Blade เหล่านี้ใช้ให้อ่านในไฟล์เท่านั้น ไม่ถูกส่งไปแสดงใน HTML
            --}}
            {{-- route() สร้าง URL สำหรับ update โดยใส่ ID ห้องและสมาชิกลงใน URL --}}
            <form method="POST" action="{{ route('rooms.members.update', [$room, $member]) }}">
                {{-- csrf สร้าง hidden input ชื่อ _token ให้ backend ตรวจว่าคำขอมี CSRF token ตรงกับ session --}}
                @csrf
                {{--
                    HTML form ส่งได้โดยตรงแค่ GET/POST จึงใช้ POST ที่ form
                    method('PATCH') สร้าง hidden input ชื่อ _method ค่า PATCH
                    Laravel อ่านค่านี้แล้วจับคู่กับ Route::patch() เพื่อแก้ไขข้อมูล
                --}}
                @method('PATCH')
                <div class="mb-3">
                    <label class="form-label" for="role">บทบาท</label>
                    {{--
                        name="role" คือชื่อข้อมูลที่ส่งให้ backend และชื่อที่ใช้ค้นหา error
                        required ตรวจใน browser; backend ยังต้อง validate ซ้ำเสมอ
                        error('role') ทำงานเฉพาะเมื่อ $errors มีข้อผิดพลาดของ role:
                        - เพิ่ม is-invalid เพื่อให้ Bootstrap แสดงช่องผิดพลาด
                        - เพิ่ม aria-invalid และ aria-describedby เพื่อให้โปรแกรมอ่านหน้าจอรู้และอ้างถึงข้อความ error
                    --}}
                    <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required @error('role') aria-invalid="true" aria-describedby="role-error" @enderror>
                        {{--
                            old('role', $member->role) ใช้ค่าที่ส่งครั้งก่อน หากไม่มีจึงใช้ค่าจากฐานข้อมูล
                            selected(เงื่อนไข) จะพิมพ์ attribute selected เมื่อเงื่อนไขเป็นจริง
                            จึงคงบทบาทที่เลือกไว้ แม้ส่งฟอร์มแล้วช่องอื่น เช่น weight จะไม่ผ่าน validation
                        --}}
                        <option value="student" @selected(old('role', $member->role) === 'student')>นักศึกษา (student)</option>
                        <option value="professor" @selected(old('role', $member->role) === 'professor')>อาจารย์ (professor)</option>
                    </select>
                    {{-- error/enderror ครอบส่วนที่แสดงเมื่อ role มี error; $message คือข้อความจาก backend --}}
                    @error('role')
                        <div class="invalid-feedback" id="role-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-4">
                    <label class="form-label" for="weight">น้ำหนัก</label>
                    {{--
                        value ใช้ old() คืนค่าที่กรอกผิดเพื่อให้แก้ต่อได้; เปิดครั้งแรกใช้ $member->weight
                        min/max/step/required เป็นข้อกำหนดฝั่ง browser ไม่ได้แทน validation ใน Controller
                        error('weight') เพิ่มรูปแบบ error และข้อมูลสำหรับโปรแกรมอ่านหน้าจอเหมือนช่อง role
                        การแสดงค่าด้วยวงเล็บปีกกาคู่ของ Blade จะ escape HTML เพื่อไม่ให้ข้อมูลกลายเป็นโค้ดบนหน้าเว็บ
                    --}}
                    <input class="form-control @error('weight') is-invalid @enderror" id="weight" name="weight" type="number" min="0.01" max="100" step="0.01" value="{{ old('weight', $member->weight) }}" required aria-describedby="weight-help @error('weight') weight-error @enderror" @error('weight') aria-invalid="true" @enderror>
                    <div class="form-text" id="weight-help">ค่าเริ่มต้น 1 กำหนดได้ตั้งแต่ 0.01–100 ทศนิยมไม่เกิน 2 ตำแหน่ง ใช้เป็นคะแนนเมื่อสมาชิกว่างในช่วงเวลาที่ค้นหา</div>
                    {{-- ตัวอย่าง: weight ว่าง -> กฎ required ไม่ผ่าน -> $message เป็น "กรุณากรอกน้ำหนัก" --}}
                    @error('weight')
                        <div class="invalid-feedback" id="weight-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-success" type="submit">บันทึกการเปลี่ยนแปลง</button>
                    <a class="btn btn-outline-secondary" href="{{ route('rooms.show', $room) }}">ยกเลิก</a>
                </div>
            </form>
        </section>
    </div>
@endsection
