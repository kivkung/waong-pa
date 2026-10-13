<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_rounds', function (Blueprint $table) {
            $table->string('repeat_frequency', 10)->default('none');
            $table->unsignedInteger('repeat_count')->default(1);
        });

        // ย้ายการตั้งค่าของรอบที่ยังไม่จบ แล้วเลิกใช้แผนสร้างรอบโหวตใหม่
        foreach (DB::table('round_recurrence_schedules')->get() as $schedule) {
            DB::table('meeting_rounds')
                ->where('recurrence_schedule_id', $schedule->id)
                ->where('status', 'active')
                ->update(['repeat_frequency' => $schedule->frequency, 'repeat_count' => $schedule->round_count]);
        }

        Schema::table('meeting_rounds', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['recurrence_schedule_id']);
            }
            $table->dropUnique(['recurrence_schedule_id', 'recurrence_index']);
            $table->dropColumn(['recurrence_schedule_id', 'recurrence_index']);
        });
        Schema::drop('round_recurrence_schedules');
    }

    public function down(): void
    {
        // คืนโครงสร้างเดิมเท่านั้น ไม่เปิดแผนโหวตซ้ำขึ้นมาเอง
        $oldMigration = require __DIR__.'/2026_10_13_000002_create_round_recurrence_schedules.php';
        $oldMigration->up();
        Schema::table('meeting_rounds', function (Blueprint $table) {
            $table->dropColumn(['repeat_frequency', 'repeat_count']);
        });
    }
};
