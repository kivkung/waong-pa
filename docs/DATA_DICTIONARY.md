# Data dictionary — Waongpa

ดึงชื่อคอลัมน์ ชนิด SQLite ค่า NULL ค่าเริ่มต้น FK และ unique จากฐานข้อมูลที่ migrate แล้ว ชนิด SQLite ไม่มี unsigned และไม่บังคับความยาว VARCHAR แบบฐานข้อมูลบางชนิด กติกาเพิ่มเติมตรวจใน Settings/Workflow

PK = Primary Key, FK = Foreign Key; — ในค่าเริ่มต้นหมายถึงไม่ได้กำหนด default ระดับฐานข้อมูล เวลา domain เก็บเป็น Asia/Bangkok; password/token ใช้ตาม Starter Kit

## users

บัญชีจาก Starter Kit ไม่สร้างซ้ำใน overlay

| คอลัมน์ | ชนิด SQLite | NULL | Default | ความหมาย |
|---|---|---|---|---|
| id (PK) | INTEGER | ไม่ได้ | — | รหัสแถว PK สร้างอัตโนมัติ |
| name | varchar | ไม่ได้ | — | ชื่อแสดงผล |
| email | varchar | ไม่ได้ | — | อีเมลใช้เข้าสู่ระบบ ไม่ซ้ำ |
| email_verified_at | datetime | ได้ | — | เวลายืนยันอีเมล |
| password | varchar | ไม่ได้ | — | รหัสผ่านที่ hash แล้ว |
| remember_token | varchar | ได้ | — | token สำหรับจดจำการเข้าสู่ระบบ |
| created_at | datetime | ได้ | — | เวลาสร้างแถว (Eloquent) |
| updated_at | datetime | ได้ | — | เวลาแก้ไขล่าสุด (Eloquent) |
| two_factor_secret | TEXT | ได้ | — | ความลับ 2FA เข้ารหัสโดย Starter Kit |
| two_factor_recovery_codes | TEXT | ได้ | — | รหัสกู้คืน 2FA เข้ารหัส |
| two_factor_confirmed_at | datetime | ได้ | — | เวลายืนยันเปิด 2FA |

- UNIQUE (email)

## personal_events

ช่วงไม่ว่างใน My Schedule ของผู้ใช้

| คอลัมน์ | ชนิด SQLite | NULL | Default | ความหมาย |
|---|---|---|---|---|
| id (PK) | INTEGER | ไม่ได้ | — | รหัสแถว PK สร้างอัตโนมัติ |
| user_id | INTEGER | ไม่ได้ | — | ผู้ใช้เจ้าของข้อมูล/สมาชิก |
| title | varchar | ไม่ได้ | — | ชื่อช่วงไม่ว่าง |
| starts_at | datetime | ไม่ได้ | — | เวลาเริ่ม รวมขอบเขตนี้ |
| ends_at | datetime | ไม่ได้ | — | เวลาสิ้นสุด ไม่รวมขอบเขตนี้ |
| source | varchar | ไม่ได้ | 'manual' | แหล่งข้อมูล ปัจจุบัน manual; เตรียมไว้สำหรับ import |
| import_key | varchar | ได้ | — | รหัสต้นทางเพื่อป้องกันนำเข้าซ้ำ ยังไม่มี import UI |
| created_at | datetime | ได้ | — | เวลาสร้างแถว (Eloquent) |
| updated_at | datetime | ได้ | — | เวลาแก้ไขล่าสุด (Eloquent) |

- FK `user_id` → `users.id`; เมื่ออ้างอิงถูกลบ: `RESTRICT`
- INDEX (user_id, starts_at)
- UNIQUE (user_id, import_key)

## rooms

ห้องนัดหมายที่นำกลับมาใช้อีกได้

| คอลัมน์ | ชนิด SQLite | NULL | Default | ความหมาย |
|---|---|---|---|---|
| id (PK) | INTEGER | ไม่ได้ | — | รหัสแถว PK สร้างอัตโนมัติ |
| owner_id | INTEGER | ไม่ได้ | — | ผู้สร้างและเจ้าของห้อง |
| name | varchar | ไม่ได้ | — | ชื่อแสดงผล |
| description | TEXT | ได้ | — | รายละเอียดห้อง |
| visibility | varchar | ไม่ได้ | 'private' | public หรือ private |
| join_code | varchar | ไม่ได้ | — | รหัสเข้าห้อง 12 ตัวอักษร สร้างอัตโนมัติ |
| archived_at | datetime | ได้ | — | เวลาซ่อนห้อง เตรียมไว้ ยังไม่มี archive UI |
| created_at | datetime | ได้ | — | เวลาสร้างแถว (Eloquent) |
| updated_at | datetime | ได้ | — | เวลาแก้ไขล่าสุด (Eloquent) |

- FK `owner_id` → `users.id`; เมื่ออ้างอิงถูกลบ: `RESTRICT`
- UNIQUE (join_code)

## room_members

สมาชิกปัจจุบันของห้อง รวมสถานะออก/ถูกนำออก

| คอลัมน์ | ชนิด SQLite | NULL | Default | ความหมาย |
|---|---|---|---|---|
| id (PK) | INTEGER | ไม่ได้ | — | รหัสแถว PK สร้างอัตโนมัติ |
| room_id | INTEGER | ไม่ได้ | — | ห้องที่สังกัด |
| user_id | INTEGER | ไม่ได้ | — | ผู้ใช้เจ้าของข้อมูล/สมาชิก |
| role | varchar | ไม่ได้ | 'student' | student หรือ professor |
| weight | numeric | ไม่ได้ | '1' | น้ำหนักความพร้อม 0.01–100 ทศนิยมไม่เกิน 2 ตำแหน่ง |
| status | varchar | ไม่ได้ | 'active' | active, left หรือ removed |
| joined_at | datetime | ไม่ได้ | — | เวลาเข้าห้องล่าสุด |
| left_at | datetime | ได้ | — | เวลาออก/ถูกนำออก |
| created_at | datetime | ได้ | — | เวลาสร้างแถว (Eloquent) |
| updated_at | datetime | ได้ | — | เวลาแก้ไขล่าสุด (Eloquent) |

- FK `user_id` → `users.id`; เมื่ออ้างอิงถูกลบ: `RESTRICT`
- FK `room_id` → `rooms.id`; เมื่ออ้างอิงถูกลบ: `RESTRICT`
- UNIQUE (room_id, user_id)

## meeting_rounds

รอบนัดและการตั้งค่าในแต่ละรอบ

| คอลัมน์ | ชนิด SQLite | NULL | Default | ความหมาย |
|---|---|---|---|---|
| id (PK) | INTEGER | ไม่ได้ | — | รหัสแถว PK สร้างอัตโนมัติ |
| room_id | INTEGER | ไม่ได้ | — | ห้องที่สังกัด |
| round_no | INTEGER | ไม่ได้ | — | เลขรอบ เริ่ม 1 เพิ่มต่อห้อง |
| phase | varchar | ไม่ได้ | 'join' | join, review, voting หรือ final |
| active_marker | INTEGER | ได้ | '1' | 1 เมื่อยังไม่จบ; NULL เมื่อ final ใช้ unique กันรอบทำงานซ้ำ |
| search_start_date | date | ไม่ได้ | — | วันแรกที่ค้นหา รวมวันนี้ |
| search_end_date | date | ไม่ได้ | — | วันสุดท้ายที่ค้นหา รวมวันนี้ รวมไม่เกิน 31 วัน |
| daily_start_time | time | ไม่ได้ | '08:00' | เวลาเริ่มค้นหาของแต่ละวัน นาที 00/30 |
| daily_end_time | time | ไม่ได้ | '18:00' | เวลาปิดของแต่ละวัน นาที 00/30 |
| duration_minutes | INTEGER | ไม่ได้ | '60' | ระยะนัด 30–600 นาที ทวีคูณ 30 และไม่ยาวกว่ากรอบแต่ละวัน |
| professor_rule | varchar | ไม่ได้ | 'at_least' | at_least หรือ all |
| min_professors | INTEGER | ได้ | '1' | ขั้นต่ำ 1–100 เมื่อ at_least; NULL เมื่อ all |
| join_starts_at | datetime | ไม่ได้ | — | เริ่มรับสมาชิก/แก้ตาราง |
| review_starts_at | datetime | ไม่ได้ | — | สิ้นสุด Join และเริ่มตรวจตัวเลือก |
| voting_starts_at | datetime | ไม่ได้ | — | สิ้นสุด Review และเริ่มโหวต |
| final_starts_at | datetime | ไม่ได้ | — | สิ้นสุดโหวตและเริ่มสรุปผล |
| version | INTEGER | ไม่ได้ | '1' | รุ่นข้อมูล เริ่ม 1 เพิ่มเมื่อ Reset เพื่อปฏิเสธ action จากหน้าเก่า |
| reset_count | INTEGER | ไม่ได้ | '0' | จำนวนครั้ง Reset |
| created_at | datetime | ได้ | — | เวลาสร้างแถว (Eloquent) |
| updated_at | datetime | ได้ | — | เวลาแก้ไขล่าสุด (Eloquent) |

- FK `room_id` → `rooms.id`; เมื่ออ้างอิงถูกลบ: `RESTRICT`
- UNIQUE (room_id, round_no)
- UNIQUE (room_id, active_marker)

## round_participants

สำเนาบทบาทและน้ำหนักของสมาชิกในรอบ

| คอลัมน์ | ชนิด SQLite | NULL | Default | ความหมาย |
|---|---|---|---|---|
| id (PK) | INTEGER | ไม่ได้ | — | รหัสแถว PK สร้างอัตโนมัติ |
| round_id | INTEGER | ไม่ได้ | — | รอบนัดที่สังกัด |
| room_member_id | INTEGER | ไม่ได้ | — | สมาชิกที่เข้าร่วมรอบนี้ |
| role | varchar | ไม่ได้ | — | student หรือ professor |
| weight | numeric | ไม่ได้ | — | น้ำหนักความพร้อม 0.01–100 ทศนิยมไม่เกิน 2 ตำแหน่ง |
| schedule_copied_at | datetime | ได้ | — | เวลาคัดลอก My Schedule ล่าสุด |
| schedule_confirmed_at | datetime | ได้ | — | เวลาที่ผู้ใช้ยืนยันตาราง NULL หมายถึงยังไม่พร้อม |
| created_at | datetime | ได้ | — | เวลาสร้างแถว (Eloquent) |
| updated_at | datetime | ได้ | — | เวลาแก้ไขล่าสุด (Eloquent) |

- FK `room_member_id` → `room_members.id`; เมื่ออ้างอิงถูกลบ: `RESTRICT`
- FK `round_id` → `meeting_rounds.id`; เมื่ออ้างอิงถูกลบ: `CASCADE`
- UNIQUE (round_id, room_member_id)

## round_busy_periods

ช่วงไม่ว่างที่ใช้คำนวณของรอบนั้น

| คอลัมน์ | ชนิด SQLite | NULL | Default | ความหมาย |
|---|---|---|---|---|
| id (PK) | INTEGER | ไม่ได้ | — | รหัสแถว PK สร้างอัตโนมัติ |
| participant_id | INTEGER | ไม่ได้ | — | ผู้ร่วมรอบเจ้าของช่วงไม่ว่างหรือโหวต |
| source_event_id | INTEGER | ได้ | — | PersonalEvent ต้นฉบับ NULL ได้เมื่อเพิ่มเองหรือต้นฉบับถูกลบ |
| title | varchar | ไม่ได้ | — | ชื่อช่วงไม่ว่าง |
| starts_at | datetime | ไม่ได้ | — | เวลาเริ่ม รวมขอบเขตนี้ |
| ends_at | datetime | ไม่ได้ | — | เวลาสิ้นสุด ไม่รวมขอบเขตนี้ |
| created_at | datetime | ได้ | — | เวลาสร้างแถว (Eloquent) |
| updated_at | datetime | ได้ | — | เวลาแก้ไขล่าสุด (Eloquent) |

- FK `source_event_id` → `personal_events.id`; เมื่ออ้างอิงถูกลบ: `SET NULL`
- FK `participant_id` → `round_participants.id`; เมื่ออ้างอิงถูกลบ: `CASCADE`
- INDEX (participant_id, starts_at)

## time_slots

ตัวเลือกเวลาที่จัดอันดับแล้ว

| คอลัมน์ | ชนิด SQLite | NULL | Default | ความหมาย |
|---|---|---|---|---|
| id (PK) | INTEGER | ไม่ได้ | — | รหัสแถว PK สร้างอัตโนมัติ |
| round_id | INTEGER | ไม่ได้ | — | รอบนัดที่สังกัด |
| starts_at | datetime | ไม่ได้ | — | เวลาเริ่ม รวมขอบเขตนี้ |
| ends_at | datetime | ไม่ได้ | — | เวลาสิ้นสุด ไม่รวมขอบเขตนี้ |
| weighted_score | numeric | ไม่ได้ | — | ผลรวมน้ำหนักผู้ว่างตลอดช่วง; ไม่ใช่คะแนนโหวต |
| rank_no | INTEGER | ไม่ได้ | — | อันดับจากคะแนนมากไปน้อย เสมอเรียงเวลาเร็วสุด |
| calculated_at | datetime | ไม่ได้ | — | เวลาคำนวณอันดับ |
| created_at | datetime | ได้ | — | เวลาสร้างแถว (Eloquent) |
| updated_at | datetime | ได้ | — | เวลาแก้ไขล่าสุด (Eloquent) |

- FK `round_id` → `meeting_rounds.id`; เมื่ออ้างอิงถูกลบ: `CASCADE`
- UNIQUE (round_id, rank_no)
- UNIQUE (round_id, starts_at, ends_at)

## votes

โหวตปัจจุบัน หนึ่งรายการต่อผู้ร่วมรอบ

| คอลัมน์ | ชนิด SQLite | NULL | Default | ความหมาย |
|---|---|---|---|---|
| id (PK) | INTEGER | ไม่ได้ | — | รหัสแถว PK สร้างอัตโนมัติ |
| participant_id | INTEGER | ไม่ได้ | — | ผู้ร่วมรอบเจ้าของช่วงไม่ว่างหรือโหวต |
| time_slot_id | INTEGER | ไม่ได้ | — | ตัวเลือกที่โหวต/ได้รับเลือก |
| created_at | datetime | ได้ | — | เวลาสร้างแถว (Eloquent) |
| updated_at | datetime | ได้ | — | เวลาแก้ไขล่าสุด (Eloquent) |

- FK `time_slot_id` → `time_slots.id`; เมื่ออ้างอิงถูกลบ: `CASCADE`
- FK `participant_id` → `round_participants.id`; เมื่ออ้างอิงถูกลบ: `CASCADE`
- UNIQUE (participant_id)

## round_results

ผลสรุปของรอบ อ้างถึงตัวเลือกที่ชนะ

| คอลัมน์ | ชนิด SQLite | NULL | Default | ความหมาย |
|---|---|---|---|---|
| id (PK) | INTEGER | ไม่ได้ | — | รหัสแถว PK สร้างอัตโนมัติ |
| round_id | INTEGER | ไม่ได้ | — | รอบนัดที่สังกัด |
| time_slot_id | INTEGER | ไม่ได้ | — | ตัวเลือกที่โหวต/ได้รับเลือก |
| decision_reason | varchar | ไม่ได้ | — | most_votes, tie_earliest หรือ no_votes_top_rank |
| finalized_at | datetime | ไม่ได้ | — | เวลาที่บันทึกผลจริง |
| created_at | datetime | ได้ | — | เวลาสร้างแถว (Eloquent) |
| updated_at | datetime | ได้ | — | เวลาแก้ไขล่าสุด (Eloquent) |

- FK `time_slot_id` → `time_slots.id`; เมื่ออ้างอิงถูกลบ: `RESTRICT`
- FK `round_id` → `meeting_rounds.id`; เมื่ออ้างอิงถูกลบ: `CASCADE`
- UNIQUE (time_slot_id)
- UNIQUE (round_id)

## การลบและประวัติ

ใช้ RESTRICT กับผู้ใช้/ห้อง/สมาชิกที่มีข้อมูลอ้างอิงเพื่อไม่ลบประวัติโดยไม่ตั้งใจ การออกห้องแก้ status แทนลบสมาชิก การ Reset ลบตัวเลือกซึ่ง CASCADE โหวต ส่วน source_event_id ใช้ SET NULL เพื่อรักษาสำเนาตาราง รอบที่สรุปแล้วให้สร้างรอบใหม่แทน Reset

users และฟิลด์ auth อาจต่างไปตาม Starter Kit รุ่นที่ทีมติดตั้ง ตาราง passkeys, sessions, password_reset_tokens, cache และ jobs ให้อ้าง migration ของ Starter Kit เป็นหลัก ไม่ใช่ตารางธุรกิจที่ชุดนี้เพิ่ม
