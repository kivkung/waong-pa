# ตารางส่วนตัวและสำเนาของรอบ

อัปเดต 2 ตุลาคม 2026

## กติกา

- กิจกรรมส่วนตัวใช้ `UserBusySlot` / `user_busy_slots` ผูก `user_id` แก้ได้เฉพาะเจ้าของ ใช้ soft delete
- เข้ารอบคือยินยอมให้ใช้เวลาไม่ว่าง ไม่มีปุ่มส่ง ถอน หรือยืนยันตาราง
- `round_member_busy_periods` เก็บเพียง `round_member_id`, `start_at`, `end_at` และ id ไม่มีชื่อกิจกรรม ไม่มี soft delete หรือประวัติรุ่น
- Preview และการดึงสำเนาใช้ scope `overlappingRound()` และการตัดช่วงรายวันเดียวกัน รองรับกิจกรรมคร่อมหลายวัน ไม่รวมช่วงที่แค่แตะขอบเวลา
- การแก้กิจกรรมในช่วงรอหลังปิด Join ถูกนับโดยตั้งใจ หลังดึงสำเนาแล้วการแก้กิจกรรมไม่เปลี่ยนสำเนา
- สมาชิกที่ไม่มีกิจกรรมทับซ้อนถูกถือว่าว่างตลอดกรอบค้นหา เป็นข้อสมมติของระบบ ไม่ใช่ข้อมูลที่ผู้ใช้ยืนยันแยก
- ปิดสมาชิกที่เวลา Review แม้ scheduler ล่าช้า กรณีอยู่ในรอบ active ที่ปิด Join แล้วให้ออกห้องไม่ได้ ต้องติดต่อเจ้าของให้ reset ก่อน เจ้าของก็นำออกไม่ได้ก่อน reset
- Reset เป็นของเจ้าของห้องเท่านั้น และเฉพาะรอบ active กลับได้เฉพาะ Join กรอกกำหนดเวลาใหม่และยืนยันผลกระทบ ล้างสำเนาทุกคนแต่เก็บสมาชิกในรอบ
- ระบบวิเคราะห์ตัวเลือก โหวต และสรุปผลต่อจากสำเนาตารางแล้ว ดู [คู่มือโหวต](round-voting.md) การรีเซ็ตจะล้างตัวเลือกและโหวตใน transaction เดียวกับสำเนา

## ติดตั้งและรัน

```bash
php artisan migrate
php artisan schedule:work
```

เปิด `schedule:work` ใน terminal แยกขณะพัฒนา ไม่ต้องมี queue worker สำหรับงานนี้ ระบบตั้ง `rounds:process` ให้ทำงานทุกนาที
Production ใช้ cron เรียก `php artisan schedule:run` ทุกนาที จาก directory โปรเจกต์
เรียกหนึ่งครั้งด้วย `php artisan rounds:process` เมื่อจำเป็น คำสั่งนี้จะเปลี่ยนรอบและเขียนสำเนาของรอบที่ถึงกำหนดจริง

`config/rounds.php` อ่าน `ROUND_SNAPSHOT_DELAY_MINUTES` ค่าเริ่มต้น 5 นาที หากเปลี่ยน environment บนระบบที่ cache config ต้องสร้าง config cache ใหม่
Scheduler เป็นการตรวจทุกนาที เวลาที่ UI แสดงคือเวลาที่เริ่มดึงได้ ไม่รับประกันว่าจะตรงวินาที หาก worker หยุดจะดึงเมื่อ worker กลับมารัน

## Migration และข้อมูลเดิม

เพิ่ม `meeting_rounds.snapshot_taken_at` ก่อน backfill ค่าสูงสุดของ `round_members.confirmed_at` ภายในรอบที่ phase ไม่ใช่ Join แล้วจึง drop confirmed_at
ถ้าไม่มีค่าเดิมยังเป็น null เพื่อให้รอบ active ที่ถึงกำหนดถูกดึงตามปกติ ค่าเดิมเป็นเพียงหลักฐานการยืนยัน ไม่สามารถสร้างช่วงเวลาในอดีตกลับมาได้ หากต้องการดึงข้อมูลใหม่สำหรับรอบที่ backfill แล้ว ให้เจ้าของ reset รอบ
การ rollback schema ไม่สามารถคืนค่า confirmed_at รายคนเดิมได้ จึงควรสำรองฐานข้อมูลก่อน migrate
ไม่เปลี่ยน `two_factor_confirmed_at` หรือ session ยืนยันรหัสผ่าน เพราะเป็นคนละความหมาย
แก้ SoftDeletes ที่เคยวางใน RoomController ให้ไปทำงานใน Room model ตาม migration เดิม

## ตรวจสอบ

```bash
php artisan test --compact
php vendor/bin/phpstan analyse --memory-limit=512M --no-progress
php vendor/bin/pint --test
```

Tests ใช้ SQLite in-memory จึงตรวจ transaction rollback ได้ แต่ไม่แทนการทดสอบหลาย connection พร้อมกันบน MySQL/PostgreSQL ซึ่งมี row locks จริง
