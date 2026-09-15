# ผลตรวจสอบ skeleton

ตรวจสอบ 15 กันยายน 2026 ในโปรเจกต์ทดสอบแยกจากไฟล์ส่งมอบ

- PHP 8.3.32 พร้อม pdo_sqlite
- Laravel 13.32.0 / Livewire 4.4.5 / Fortify 1.39.0
- Official Laravel Livewire Starter Kit จาก main ณ วันที่ตรวจ ใช้ authentication เดิมของ Starter Kit
- PHPUnit 12.5.35, SQLite :memory: สำหรับ feature tests
- `php artisan test --filter=WaongpaTest`: 21 passed, 66 assertions
- `php artisan migrate`: สำเร็จ รวม FK และ unique constraints
- `php artisan db:seed --class=WaongpaDemoSeeder`: สำเร็จ
- `php artisan route:list --path=waongpa`: พบ 6 routes

## สิ่งที่ tests ตรวจ

Guest/Public/Private และข้อมูลที่ซ่อน, auth redirect, render หน้าหลักฝั่ง server, Schedule CRUD และการแก้ของคนอื่น, สำเนาหลังลบต้นฉบับ, ยืนยันตาราง, นัดต่อเนื่องและขอบเขตเวลาชนกัน, น้ำหนัก, ไม่มีอาจารย์ว่าง, เปลี่ยนโหวต, เสมอ/ไม่มีโหวต, นัดซ้ำ, Reset และ version เก่า, สิทธิ์ข้ามเฟส, โหวตข้ามรอบ/หลังเส้นตาย, scheduler เรียกซ้ำ, phase validation, รอบ active ซ้ำ และ ICS UTC/สิทธิ์ดาวน์โหลด

Tests ปิด Vite ด้วย withoutVite เพื่อทดสอบฝั่ง PHP โดยไม่ต้องสร้าง asset ก่อน จึงไม่ได้ยืนยัน JavaScript ใน browser หรือหน้าตาบนทุกอุปกรณ์ ทีมควรทดลองตาม README หลัง npm run build อีกครั้ง

ยังไม่ทดสอบ concurrent requests หลาย process, performance ข้อมูลจำนวนมาก, email delivery, passkeys หรือ 2FA ครบวงจร

เพิ่มเติม: AuthenticationTest ของ Official Starter Kit ผ่าน 5 tests / 13 assertions (ปิด Vite เฉพาะ harness) และ PHP syntax ของโมดูลผ่าน 25 ไฟล์
