# Waongpa — Laravel skeleton สำหรับทีม 4 คน

ตัวอย่างโปรเจกต์นัดหมายจากตารางว่างร่วมกัน ใช้ **Laravel 13 / PHP 8.3+ / Livewire 4 Starter Kit (Built-in authentication) / SQLite** บน Herd ตามเวอร์ชันที่ตกลงกัน

ไฟล์นี้เป็น **ชุดไฟล์เพิ่มเข้าโปรเจกต์ (overlay)** มีหน้าจอและกระบวนการหลักที่ทำงานได้ ต้องสร้าง Laravel Starter Kit ก่อน ไม่รวม vendor, node_modules, รหัสลับ หรือฐานข้อมูลจริงของผู้ใช้ UI เป็นตัวอย่างเรียบง่าย ยังไม่ได้ทำให้ตรง Figma ทุกหน้าจอ

## 1. สร้างโปรเจกต์ฐาน

ใน Herd สร้างไซต์ชื่อ `waongpa` หรือใช้ Laravel Installer รุ่นปัจจุบัน:

```powershell
laravel new waongpa
cd waongpa
herd isolate 8.3
```

เลือก Laravel 13, Starter Kit **Livewire**, authentication **Laravel built-in** (ไม่เลือก WorkOS), database **SQLite**, testing **PHPUnit** หากมีตัวเลือก Teams ให้ปิดสำหรับตัวอย่างนี้ ติดตั้ง dependency ของ Starter Kit ตามขั้นตอนของ Installer

ตรวจสอบก่อนประกอบ:

```powershell
herd php -v
herd php artisan --version
herd composer show livewire/livewire
```

ต้องได้ PHP 8.3 ขึ้นไป, Laravel 13.x และ Livewire 4.x อย่าใช้ `composer create-project laravel/livewire-starter-kit` แบบไม่ระบุรุ่นโดยไม่ตรวจสอบ เพราะแท็กที่ Composer เลือกอาจเป็น Starter Kit เก่า

## 2. คัดลอกไฟล์

คัดลอก **ทุกอย่างภายใน `overlay/`** ไปที่รากโปรเจกต์ `waongpa/` (ตำแหน่งเดียวกับ artisan) โดยรวมโฟลเดอร์เข้าด้วยกัน ตรวจสอบไฟล์ชื่อซ้ำก่อนแทนที่ ชุดนี้ไม่ได้มี User.php, routes/web.php หรือไฟล์ authentication ของ Starter Kit

ตัวอย่างตำแหน่งหลังคัดลอก:

```text
waongpa/
  artisan
  app/Livewire/Waongpa/
  app/Services/Waongpa/
  app/Models/Waongpa/
  routes/waongpa.php
  database/migrations/2026_09_15_000001_create_waongpa_tables.php
```

### เชื่อม routes

เพิ่มบรรทัดนี้ **ครั้งเดียว** ที่ท้าย `routes/web.php` ภายใน PHP โดยเก็บ routes เดิมทั้งหมดไว้:

```php
require __DIR__.'/waongpa.php';
```

### ตั้ง timezone และ SQLite

แก้ค่าเดิมใน `config/app.php`:

```php
'timezone' => env('APP_TIMEZONE', 'Asia/Bangkok'),
```

ตั้ง `.env` ให้สอดคล้องกับไซต์ Herd:

```dotenv
APP_NAME=Waongpa
APP_ENV=local
APP_URL=http://waongpa.test
APP_TIMEZONE=Asia/Bangkok
DB_CONNECTION=sqlite
```

ใช้ไฟล์ `database/database.sqlite` ของ Starter Kit หากยังไม่มีให้สร้างไฟล์ว่าง ไม่ต้องตั้ง DB_HOST, DB_USERNAME, DB_PASSWORD และลบ/คอมเมนต์ DB_DATABASE เดิมที่ชี้ฐานข้อมูลอื่น หรือกำหนดเป็น absolute path ไปยังไฟล์ SQLite นี้

โมดูลนี้เก็บวันเวลาเป็นเวลาไทยในฐานข้อมูล ต้องให้ `config/app.php` และ `config/waongpa.php` ใช้ Asia/Bangkok เหมือนกัน การส่งออก ICS จะแปลงเป็น UTC

### ติดตั้งตารางและข้อมูลตัวอย่าง

รันจากรากโปรเจกต์:

```powershell
herd composer dump-autoload
herd php artisan optimize:clear
herd php artisan migrate
herd php artisan db:seed --class=WaongpaDemoSeeder
npm install
npm run build
```

หาก Starter Kit สร้าง APP_KEY ให้แล้ว ใช้ค่าเดิม หากยังว่างให้รัน `herd php artisan key:generate` ไม่ต้องใช้ migrate:fresh ซึ่งจะลบข้อมูลเดิม

เปิด **http://waongpa.test/waongpa** หากยังไม่ได้ผูกไซต์กับ Herd ให้รัน `herd php artisan serve` แล้วใช้ URL ที่แสดงตามด้วย `/waongpa`

Starter Kit ยังพาไป dashboard หลัง login ตามเดิม ให้เพิ่มลิงก์ `/waongpa` ในเมนู dashboard หรือเปิด URL นี้ตรง ๆ CSS ของโมดูลอยู่ใน public/waongpa.css แต่หน้า authentication ของ Starter Kit ต้องมีไฟล์จาก npm run build

## 3. เปิดการเปลี่ยนเฟสตามเวลา

เพิ่มที่ท้าย `routes/console.php`:

```php
\Illuminate\Support\Facades\Schedule::command('waongpa:tick')
    ->everyMinute()
    ->withoutOverlapping();
```

ระหว่างพัฒนาเปิดอีก terminal แล้วรัน:

```powershell
herd php artisan schedule:work
```

หรือประมวลผลทันทีด้วย `herd php artisan waongpa:tick` คำสั่งนี้เรียกซ้ำได้โดยไม่สร้างผลซ้ำ หากยังไม่ยืนยันตารางครบ/ไม่มีเวลาผ่านเงื่อนไข ระบบคงเฟส Join และแสดงเหตุผล ต้องแก้ข้อมูลหรือ Reset กรอบเวลา ไม่ได้ถือว่าทุกคนว่างอัตโนมัติ

## 4. ทดลองระบบใน 5 นาที

บัญชีสาธิตใช้เฉพาะ local/testing; seeder จะไม่ทับบัญชีเดิมที่ใช้อีเมลนี้:

| ผู้ใช้ | อีเมล | รหัสผ่าน |
|---|---|---|
| เจ้าของห้อง/อาจารย์ | owner@waongpa.test | WaongpaDemo123! |
| สมาชิก/นักศึกษา | student@waongpa.test | WaongpaDemo123! |

1. เปิด `/waongpa` โดยยังไม่ login: เห็นห้อง Public และสรุปรอบ แต่ไม่เห็นสมาชิก รหัสเข้าห้อง และตารางส่วนตัว
2. Login เจ้าของ เปิดห้องตัวอย่าง → เปิดรอบปัจจุบัน ตารางสาธิตยืนยันให้แล้ว
3. กดข้ามไป Review เพื่อจัดอันดับ แล้วข้ามไป Voting
4. Login นักศึกษาในอีก browser/session แล้วโหวต เปลี่ยนโหวตได้ก่อนเส้นตาย
5. เจ้าของข้ามไป Final: ระบบสรุปผล และสมาชิกดาวน์โหลด ICS ได้
6. กลับหน้าห้อง กดนัดอีกครั้ง: สมาชิกเดิมกลับมาในรอบใหม่ ประวัติและผลรอบเดิมยังอยู่ ทุกคนตรวจ/ยืนยันตารางใหม่

หากสร้างห้องเอง ให้ส่งรหัสให้สมาชิกเข้าที่หน้ารวม เจ้าของกำหนดบทบาทอาจารย์และน้ำหนัก สมาชิกใหม่เริ่มเป็นนักศึกษา น้ำหนัก 1 ทุกคนต้องยืนยันตารางก่อนคำนวณ

## 5. Feature ที่ทำแล้ว

- Authentication ใช้ของ Starter Kit: สมัคร/เข้า/ออกจากระบบ และความสามารถบัญชีตามที่เปิดใน Fortify ไม่เขียนระบบรหัสผ่านใหม่
- Guest ดูห้อง Public; ห้อง Private จำกัดสมาชิกที่ยังอยู่ในห้อง
- ค้นหาห้อง, My Rooms, สร้าง/แก้ชื่อและรายละเอียด, Public/Private, เข้าด้วยรหัส
- เจ้าของกำหนดบทบาท student/professor และน้ำหนัก สมาชิกออกได้ เจ้าของนำสมาชิกออกได้ใน Join
- My Schedule เพิ่ม/แก้/ลบช่วงไม่ว่าง พร้อมตรวจเวลาสิ้นสุด
- คัดลอกตารางเข้ารอบ แก้เฉพาะสำเนารอบนั้น ดึงตารางใหม่และยืนยันความครบถ้วน
- ตั้งช่วงวัน เวลา 08–18 โดยปริยาย ระยะนัดเป็นทวีคูณ 30 นาที และอาจารย์อย่างน้อย N คนหรือทุกคน
- Join → Review → Voting → Final ตามเวลา หรือเจ้าของข้ามเฟส
- จัดอันดับเวลาต่อเนื่อง โหวต/เปลี่ยนโหวต สรุปกรณีเสมอและไม่มีโหวต
- Reset รอบที่ยังไม่จบ และนัดอีกครั้งหลังจบโดยเก็บประวัติ
- ส่งออกนัดที่สรุปแล้วเป็น ICS; ข้อความเมื่อไม่มีข้อมูล/ข้อมูลไม่ครบ/เวลาไม่เหมาะสม/เฟสไม่อนุญาต
- ตรวจสิทธิ์ฝั่ง server, validation, transaction และป้องกันหน้าที่ค้างจากก่อน Reset

## 6. กติกาและค่าเริ่มต้นของ skeleton

สิ่งต่อไปนี้เป็นรายละเอียดที่เลือกให้ตัวอย่างทำงานครบวงจร ทีมสามารถปรับก่อนสรุปกับอาจารย์:

- แนะนำ **3 ช่วงเวลา**; คะแนนเป็นผลรวมน้ำหนักสมาชิกที่ว่างตลอดช่วงนัด นักศึกษาที่ไม่ว่างไม่ทำให้ช่วงถูกตัด แต่ไม่ได้เพิ่มคะแนน
- เงื่อนไขอาจารย์เป็นข้อบังคับก่อนจัดอันดับ ต้องมีอาจารย์อย่างน้อยหนึ่งคนในรอบ กรณีไม่มีช่วงผ่านเงื่อนไขจะไม่บังคับเลือกช่วงที่อาจารย์ไม่ว่าง
- คะแนนจัดอันดับเสมอเรียงวันเวลาเร็วสุดก่อน ตัวเลือกอาจทับซ้อนกันได้ เพราะสุดท้ายเลือกเพียงนัดเดียว
- หนึ่งคนหนึ่งโหวต **ไม่นำ weight มาคูณคะแนนโหวต**; เสมอเลือกเวลาที่เร็วที่สุดในกลุ่มคะแนนโหวตสูงสุด; ไม่มีคนโหวตเลือก rank 1
- ตารางว่างที่ยืนยันแล้ว = ว่างทั้งหมด; ตารางที่ยังไม่ยืนยัน = ข้อมูลยังไม่พร้อม ป้องกันการไม่มีข้อมูล Schedule แล้วถูกนับว่าว่าง
- พักเที่ยงให้สมาชิกเพิ่มเป็นช่วงไม่ว่างเอง ไม่มีสวิตช์พักเที่ยงกลางของห้อง
- Review เป็นเฟสตรวจตัวเลือกก่อนโหวต ไม่มีการแก้ตัวเลือกด้วยมือ
- ตั้งเวลา Join < Review < Voting < Final และ Final ก่อนเวลานัดแรก ค้นหาสูงสุด 31 วัน เฟสใช้ขอบเขตเริ่มรวม/สิ้นสุดไม่รวม
- Reset เก็บสมาชิกและช่วงไม่ว่างที่แก้ในรอบ แต่ล้างการยืนยัน ตัวเลือก โหวต และผล พร้อมเพิ่ม version **ถ้าเปลี่ยนวันค้นหา ให้กดดึง My Schedule ใหม่แล้วตรวจทุกวันก่อนยืนยัน**
- นัดอีกครั้งสร้างเลขรอบใหม่ ใช้สมาชิก active เดิม คัดลอก My Schedule ปัจจุบัน และขอการยืนยันใหม่
- Skip ยังตรวจสิทธิ์และข้อมูลที่จำเป็น หากหมดเวลาโหวตแล้วให้ประมวลผลตามเวลาหรือ Reset แทนเปิดโหวตย้อนหลัง

## 7. อ่านโค้ดจากตรงไหน

| ตำแหน่ง | หน้าที่ |
|---|---|
| app/Models/Waongpa/ | 9 models และความสัมพันธ์ของข้อมูล ใช้ User ของ Starter Kit |
| database/migrations/ | ตาราง FK, unique และ index |
| app/Services/Waongpa/Access.php | ตรวจการดูห้อง เจ้าของ และสมาชิกในรอบ |
| app/Services/Waongpa/Settings.php | ค่าเริ่มต้นและกติกาตรวจข้อมูล |
| app/Services/Waongpa/SlotRanker.php | สร้างช่วง 30 นาที ตรวจช่วงไม่ว่างและจัดอันดับ |
| app/Services/Waongpa/Workflow.php | ขั้นตอนสร้าง/เข้า/Reset/นัดซ้ำ ยืนยัน โหวต และเปลี่ยนเฟส ใน transaction |
| app/Livewire/Waongpa/ | Catalogue, CreateRoom, Schedule, RoomBoard, RoundBoard รับ action จากหน้าจอ |
| resources/views/livewire/waongpa/ | หน้าจอของแต่ละ component |
| resources/views/waongpa/ | แบบฟอร์มและข้อความที่ใช้ร่วมกัน |
| resources/views/layouts/waongpa.blade.php | โครงหน้าและเมนู |
| public/waongpa.css | หน้าตาแบบ responsive โดยไม่เพิ่ม package |
| app/Http/Controllers/Waongpa/DownloadMeeting.php | สร้าง ICS สำหรับสมาชิก |
| app/Console/Commands/WaongpaTick.php | ประมวลผลเฟสที่ถึงกำหนด |
| routes/waongpa.php | 6 routes ของโมดูล |
| config/waongpa.php | timezone, จำนวนตัวเลือก, จำนวนวันสูงสุด |
| database/seeders/WaongpaDemoSeeder.php | ข้อมูลสาธิต |
| tests/Feature/WaongpaTest.php | ทดสอบพฤติกรรมหลักและสิทธิ์เข้าถึง |

อ่านภาพรวมฐานข้อมูลต่อใน [ER diagram](docs/ER.md) และ [Data dictionary](docs/DATA_DICTIONARY.md)

## 8. ทดสอบ

```powershell
herd php artisan test --filter=WaongpaTest
herd php artisan route:list --path=waongpa
```

ทดสอบด้วยฐานข้อมูล testing ตาม phpunit.xml ของ Starter Kit ซึ่งควรใช้ SQLite `:memory:` อย่าชี้ไปฐานข้อมูลจริง เพราะ RefreshDatabase สร้างตารางทดสอบใหม่

ตรวจแล้วกับ PHP 8.3.32, Laravel 13.32.0, Livewire 4.4.5, Fortify 1.39.0, SQLite: **21 tests / 66 assertions ผ่าน**, migrate และ seeder ผ่าน รายละเอียดอยู่ใน [VALIDATION.md](docs/VALIDATION.md)

## 9. แบ่งงาน 4 คนและสิ่งที่ต่อยอด

| คน | งานหลัก | เกณฑ์ส่งมอบ |
|---|---|---|
| 1 | Authentication integration, Public catalogue, ออกแบบ UI ตาม Figma | Login/Guest และ responsive/navigation ใช้ได้ครบ |
| 2 | Schedule และปฏิทิน | ตารางรายสัปดาห์, recurring events/ICS import ถ้าต้องการ, ยืนยันสำเนาตารางได้ |
| 3 | Room, สมาชิก และเฟส | ตั้งค่า, สิทธิ์, Reset, นัดอีกครั้งและประวัติครบ |
| 4 | Ranking, Voting, Database และ tests | ยืนยันสูตรกับทีม, เพิ่มกรณีอาจารย์หลายคน, ดูแล ER/DD และ integration |

ให้แต่ละคนทดสอบส่วนของตน และรวมโค้ดทดสอบร่วมกันเป็นระยะ ไม่ฝากการทดสอบทั้งหมดไว้คนที่ 4

ยังไม่ได้ทำ: นำเข้า ICS/ตารางเรียน, recurring timetable, ลากวางปฏิทิน, ระบบแจ้งเตือน, archive/delete UI, audit log แบบละเอียด และ UI ตรง Figma ทุกหน้า การส่งอีเมลของ auth ต้องตั้ง mail transport ของ Starter Kit ก่อนใช้งานจริง ยังไม่ทดสอบโหลดหรือการใช้งานพร้อมกันจำนวนมาก

โครงนี้เหมาะเป็นฐานสำหรับงานรายวิชา ทีมยังต้องเทียบ rubric/เอกสารรายวิชาจริง ไม่ได้ยืนยันว่าครอบคลุมหัวข้อใน ZIP รายวิชาทั้งหมด

## แหล่งอ้างอิงเวอร์ชันและการติดตั้ง

- [Laravel releases](https://laravel.com/docs/13.x/releases)
- [Laravel 13 Starter Kits](https://github.com/laravel/docs/blob/13.x/starter-kits.md)
- [Livewire 4 components](https://livewire.laravel.com/docs/4.x/components)
- [Herd Windows PHP versions](https://herd.laravel.com/docs/windows/technology/php-versions)
