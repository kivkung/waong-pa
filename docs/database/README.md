# โมเดลฐานข้อมูล: โหวตครั้งเดียวแล้วนัดหมายเกิดซ้ำ

ใช้ `waongpa-recurrence-v2.mwb` ซึ่งเริ่มจากไฟล์ waongpa.mwb ต้นฉบับของผู้ใช้และคง 9 ตารางเดิมกับตำแหน่งเดิมไว้ แผนภาพอยู่ในแท็บ EER Diagram1

เพิ่มเฉพาะ:

- busy_slot_recurrence_rules และ user_busy_slots.recurrence_rule_id สำหรับ My Schedule
- meeting_rounds.repeat_frequency (VARCHAR(10), default 'none')
- meeting_rounds.repeat_count (INT UNSIGNED, default 1)

รวม 10 ตารางและความสัมพันธ์ 12 เส้น ไม่มี round_recurrence_schedules หรือ recurrence_index เพราะไม่สร้างรอบโหวตซ้ำ

หนึ่งรอบโหวตเลือกเวลาชนะหนึ่งครั้ง แล้วบันทึกกิจกรรมเกิดซ้ำใน My Schedule ของสมาชิกตามความถี่และจำนวนครั้งที่ตั้ง สมาชิกแต่ละคนมีกฎของตนเอง

ไฟล์นี้เพิ่มเฉพาะ recurrence ตามที่ผู้ใช้ขอ จึงไม่ได้อัปเดตคอลัมน์อื่นนอกขอบเขต เช่น activity_name ที่ไม่มีในโครงต้นฉบับ

ตรวจเปิดด้วย MySQL Workbench จริงและส่งออกภาพ waongpa-recurrence-v2-preview.png สำเร็จ ส่วนฐานข้อมูลแอปให้อัปเดตผ่าน php artisan migrate

