# Waongpa Role 1 — Dashboard, สร้างห้อง และเข้าร่วมห้อง

ส่วนนี้ใช้ Laravel Controller + Blade แบบพื้นฐานร่วมกับ Login/Register/Logout ของ Livewire Starter Kit มีระบบห้อง รอบนัดหมาย และกิจกรรมส่วนตัวแล้ว

## อัปเดต 2 ตุลาคม 2026 — สำเนาตารางอัตโนมัติ

มีระบบกิจกรรมส่วนตัว, ปิด Join อัตโนมัติ, ดึงสำเนาเวลาไม่ว่างหลังระยะรอใน config, ล็อกการออก/นำสมาชิกออก และ reset รอบกลับ Join แล้ว อ่านกติกา migration และวิธีเปิด scheduler ใน [คู่มือสำเนาตาราง](docs/round-snapshots.md)

ส่วนรายละเอียดเดิมด้านล่างเป็นบันทึกเฟสห้องก่อนเพิ่มระบบรอบ ให้ยึดคู่มือล่าสุดสำหรับกติกาตารางและสมาชิกที่เปลี่ยนไป

## ฟีเจอร์และสิทธิ์
- Guest ดู Dashboard และรายละเอียดทั่วไปของห้อง Public ได้
- Login แล้วเห็น Public และส่วน “ห้องของฉัน” เฉพาะสมาชิก active รวม Private ของตนเอง
- สร้างห้อง Public/Private ได้ ผู้สร้างเป็น owner และสมาชิก role=student, weight=1
- เข้าห้อง Public/Private ด้วยรหัส 12 ตัว รองรับตัวพิมพ์เล็กและช่องว่างหัวท้าย
- เข้าซ้ำไม่สร้างสมาชิกซ้ำ; left กลับเข้าด้วยรหัสได้; removed เข้าใหม่ไม่ได้
- หน้า Private ตรวจสิทธิ์ฝั่งเซิร์ฟเวอร์ด้วย ไม่ใช่แค่ซ่อนลิงก์ หากไม่มีสิทธิ์ได้ 404
- รหัสเชิญแสดงเฉพาะเจ้าของ แม้ห้อง Public ก็ไม่แสดงรหัสให้ Guest หรือสมาชิกทั่วไป
- สมาชิก active ดูรายชื่อ บทบาท และน้ำหนักของสมาชิก active ในห้องได้ แบ่งหน้าละ 15 คน; Guest และคนนอกไม่เห็นรายชื่อ
- เจ้าของแก้บทบาท student/professor และน้ำหนัก 0.01–100 (ทศนิยมไม่เกิน 2 ตำแหน่ง) ได้ รวมถึงของตนเอง โดยไม่เปลี่ยนเจ้าของห้อง
- ยังไม่มี UI ออกจากห้อง/นำสมาชิกออก หรือแก้ไขห้อง

## โครงสร้างฐานข้อมูลที่ใช้อยู่
ใช้ migration เดิม ไม่เปลี่ยนชื่อหรือล้างตาราง:
- users: บัญชี Starter Kit
- rooms: owner_id, name, description, visibility, join_code
- room_members: room_id, user_id, role, weight, status, joined_at, left_at

ในโปรเจกต์นี้ชื่อจริงคือ RoomMember / room_members (ไม่ใช่ room_user ที่เคยยกตัวอย่าง)
unique(room_id, user_id) กันสมาชิกซ้ำ และ unique(join_code) กันรหัสห้องซ้ำ
owner_id กับ role คนละหน้าที่: student ก็เป็นเจ้าของได้

## เริ่มใช้งาน
โปรเจกต์นี้ติดตั้ง dependencies ไว้แล้ว ไม่ต้อง setup ใหม่หรือสร้าง APP_KEY ใหม่

จากโฟลเดอร์โปรเจกต์:
~~~bash
php artisan migrate:status
php artisan migrate
php artisan route:clear
php artisan view:clear
~~~
migrate รันเฉพาะ migration ที่ค้างอยู่ อย่าใช้ migrate:fresh กับฐานข้อมูลที่มีข้อมูลต้องเก็บ

เปิด http://waongpa_role1.test ผ่าน Herd หรือใช้ php artisan serve แล้วเปิด URL ที่แสดง
Dashboard ใช้ Bootstrap 5.3.8 ผ่าน CDN ต้องมีอินเทอร์เน็ต ส่วน Login ของ Starter Kit ยังใช้ Vite ตามเดิม หาก asset ยังไม่พร้อมใช้ npm run dev หรือ npm run build

## อ่านโค้ดตามลำดับนี้
1. routes/web.php: URL ไหนเรียก Controller และเมธอดใด
2. app/Http/Controllers/PublicDashboardController.php: query Public และห้องที่เราเป็นสมาชิก
3. app/Http/Controllers/RoomController.php: create/store/joinForm/join/show
4. app/Models/Room.php และ RoomMember.php: ความสัมพันธ์ ต้อง return hasMany/belongsTo
5. resources/views/index.blade.php: รายการห้อง
6. resources/views/rooms/create.blade.php: ฟอร์มสร้างห้อง
7. resources/views/rooms/join.blade.php: ฟอร์มใส่รหัส
8. resources/views/rooms/show.blade.php: รายละเอียดและรหัสสำหรับเจ้าของ
9. resources/views/layouts/rooms.blade.php และ partials/header.blade.php: Bootstrap และ Header
10. app/Http/Controllers/RoomMemberController.php: edit/update บทบาทและน้ำหนักสมาชิก
11. resources/views/rooms/members/edit.blade.php: ฟอร์มแก้สมาชิก แสดงข้อมูลเดิมและ validation errors

## การแก้บทบาทและน้ำหนักสมาชิก
- GET /rooms/{room}/members/{member}/edit -> ตรวจเจ้าของ -> ค้นหาสมาชิก active ของห้องนี้ -> แสดงฟอร์ม
- PATCH /rooms/{room}/members/{member} -> ตรวจเจ้าของและสมาชิก -> validate -> บันทึกเฉพาะ role/weight -> กลับหน้าห้อง
- ใช้ auth middleware ทั้งสอง route และตรวจสิทธิ์ใน Controller อีกครั้ง คนที่ไม่ใช่เจ้าของได้ 403 สำหรับ Public และ 404 สำหรับ Private
- ค้นหาผ่าน $room->members() เพื่อป้องกันการเปลี่ยน URL ไปแก้สมาชิกของห้องอื่น
- ใช้ with('user') โหลดชื่อสมาชิกพร้อมกัน ลดการ query แยกทีละคน และไม่แสดงอีเมลในรายชื่อ
- แยกหน้า edit ออกจากรายชื่อ เพื่อให้แต่ละฟอร์มมี validation และ old() ของสมาชิกเพียงคนเดียว
- ฟอร์มใช้ @csrf และ @method('PATCH'); บันทึกแค่แถวเดียวจึงไม่ต้องเพิ่ม transaction
- กติกาน้ำหนักใช้ช่วงเดียวกับโปรเจกต์เดิม ยังไม่มีการคำนวณเวลาหรือโหวตในเวอร์ชันนี้
- ยังไม่มี Round จึงยังไม่มีกติกาล็อกการแก้สมาชิกตามเฟส ต้องเพิ่มการตรวจนี้เมื่อพัฒนา Round

ทดลอง: เจ้าของเปิดห้อง -> กดแก้ไขข้างชื่อสมาชิก -> เปลี่ยน role/weight -> บันทึก จากนั้นใช้บัญชีสมาชิกตรวจว่าดูรายชื่อได้แต่ไม่มีปุ่มแก้ไข
ทดสอบเฉพาะส่วนนี้ด้วย `php artisan test --filter=RoomMemberTest`

## เส้นทางการทำงานพื้นฐาน
GET /rooms/create -> create() -> view('rooms.create')
POST /rooms -> store() -> validate -> บันทึก Room และ RoomMember -> redirect พร้อม flash message
GET /rooms/join -> joinForm() -> view('rooms.join')
POST /rooms/join -> join() -> หาห้องจากรหัส -> เพิ่มสมาชิก -> redirect
GET /rooms/{room} -> show() -> ตรวจสิทธิ์ -> แสดงรายละเอียด

เครื่องหมาย {room} ใช้ Route Model Binding: Laravel โหลด Room ตาม ID และตอบ 404 หากไม่มี
/dashboard และ / ใช้หน้าเดียวกัน ชื่อ route คือ dashboard และ home ตามลำดับ
routes สำหรับสร้างและเข้าร่วมอยู่ใน auth middleware ส่วนหน้าดูห้องตรวจสิทธิ์ใน show()
ยังไม่บังคับ verified สำหรับสร้างและเข้าห้อง เพื่อให้ทดลองได้หลังสมัครบัญชี ส่วน settings ใช้กติกา Starter Kit เดิม

## เทคนิคที่ใช้และเหตุผล
- index/create/store เป็นชื่อเมธอดธรรมดา ไม่ใช้ __invoke เพื่อให้อ่าน route ตรง ๆ
- request->validate ตรวจฝั่งเซิร์ฟเวอร์ แม้ HTML จะมี required แล้ว
- owner_id/user_id ใช้บัญชีที่ Login ไม่รับจากฟอร์ม
- DB::transaction ทำให้ห้องและสมาชิกเจ้าของสำเร็จพร้อมกัน หากส่วนหนึ่งล้มเหลวจะย้อนกลับ
- Room กำหนดค่าแต่ละช่องแล้ว save() เพื่อเห็นว่าเขียนข้อมูลอะไร
- RoomMember ใช้ firstOrCreate เมื่อเข้าครั้งแรก: ค้นหาคู่ room_id/user_id ก่อน หากไม่มีจึงเพิ่ม ช่วยจัดการคำขอซ้ำพร้อม unique constraint จึงกำหนด fillable เฉพาะช่องที่ใช้
- รหัสสุ่มตรวจซ้ำก่อนบันทึก และมี unique ใน DB เป็นด่านสุดท้าย กรณีสุ่มชนพร้อมกันอย่างหายากจะบันทึกไม่สำเร็จ (ยังไม่มีระบบ retry รหัสแบบเฉพาะข้อผิดพลาด)
- @csrf ทุก POST ป้องกัน CSRF; {{ ... }} escape ข้อมูลผู้ใช้
- old()/@error คืนข้อมูลและแจ้ง validation error
- POST join จำกัด 10 คำขอต่อนาที ลดการเดารหัส
- paginate(9) แบ่งหน้า Public กับ My Rooms คนละชื่อ query parameter
- ห้อง Public ของตัวเองอาจแสดงทั้งสองส่วน เป็นคนละมุมมอง ไม่ใช่ข้อมูลซ้ำใน DB
- ไม่เพิ่ม repository/service abstraction เพื่อให้เริ่มเรียนจาก Controller ได้ง่าย

## ทดลองใช้งานด้วยสองบัญชี
1. เปิดหน้าต่างส่วนตัว ยังไม่ Login: ดู Public ได้ แต่เข้า /rooms/create และ /rooms/join จะถูกพาไป Login
2. บัญชี A สร้าง Private: ต้องเห็นห้องใน “ห้องของฉัน” และเห็นรหัสบนหน้ารายละเอียด
3. Guest และบัญชี B ยังไม่เข้าห้อง: ต้องไม่เห็น Private และเปิด URL โดยตรงได้ 404
4. บัญชี B ใส่รหัสจาก A: เข้าห้องได้และเห็นใน Dashboard แต่ไม่เห็นรหัสเชิญ
5. B ใส่รหัสเดิมซ้ำ: ต้องไม่มีแถวสมาชิกซ้ำ
6. ใส่รหัสผิด: เห็นข้อความ error
7. สร้าง Public: Guest เห็นรายละเอียด แต่ไม่เห็นรหัส
8. ลองชื่อว่าง/visibility ผิด: ต้องไม่บันทึก
9. ทดสอบเมนูเรียงบรรทัดบนมือถือและ Logout

## Automated checks
~~~bash
php artisan test --filter=DashboardTest
composer test
~~~
Feature tests ใช้ RefreshDatabase กับ SQLite :memory: ตาม phpunit.xml ไม่ใช้ข้อมูลจริง
ครอบคลุมสิทธิ์ Public/Private, owner/member, validation, transaction rollback, join ซ้ำ, รหัสผิด, left/removed และ HTML escaping

## ผลการตรวจล่าสุดหลังเพิ่มการแก้สมาชิก — 28 กันยายน 2026
- Feature/Unit tests: 62 tests, 279 assertions ผ่าน
- RoomMemberTest: 19 tests, 127 assertions ผ่าน ครอบคลุมสิทธิ์ การแก้ข้ามห้อง สมาชิก left/removed การตรวจข้อมูล การแสดง error หลัง redirect การ escape ชื่อ และ pagination
- PHPStan level 7 (--memory-limit=512M) และ Pint --test ผ่าน
- ไม่มี migration ใหม่ และไม่ได้เปลี่ยนข้อมูลในฐานข้อมูลจริง
- ฟีเจอร์สมาชิกตรวจด้วย HTTP Feature tests บน SQLite :memory: ยังไม่ได้ตรวจหน้าจอฟีเจอร์นี้ผ่าน browser

## ผลการตรวจเดิมก่อนเพิ่มการแก้สมาชิก — 28 กันยายน 2026
- Feature/Unit tests: 42 tests, 144 assertions ผ่าน
- PHPStan level 7: ผ่าน (ใช้ --memory-limit=512M เพราะค่าเริ่มต้น 128M ไม่พอ)
- Pint --test: ผ่าน
- Dashboard เปิดผ่าน Herd ได้จริงในสถานะ Guest
- Header ใช้ flex-wrap ให้เมนูขึ้นบรรทัดใหม่บนจอเล็ก ไม่ต้องใช้ JavaScript
- ยังไม่ได้ Login/สร้างข้อมูลจริงผ่าน browser; เส้นทางเหล่านี้ทดสอบด้วย HTTP Feature tests บน SQLite ในหน่วยความจำ

หาก composer test หยุดเพราะ PHPStan memory limit ให้ใช้:
~~~bash
php vendor/bin/pint --test
php vendor/bin/phpstan analyse --memory-limit=512M
php artisan test
~~~
