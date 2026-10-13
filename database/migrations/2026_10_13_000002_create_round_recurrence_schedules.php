<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('round_recurrence_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('frequency', 10);
            $table->unsignedSmallInteger('round_count');
            $table->unsignedSmallInteger('next_index')->default(1);
            $table->unsignedSmallInteger('skipped_count')->default(0);
            $table->json('template');
            $table->boolean('is_active')->default(true);
            $table->dateTime('next_run_at')->nullable()->index();
            $table->timestamps();
        });
        if (DB::getDriverName() === 'sqlite') {
            // เพิ่มคอลัมน์โดยไม่สร้างตารางเดิมใหม่ เพื่อรักษา CHECK และข้อมูลลูกของรอบ
            DB::statement('ALTER TABLE meeting_rounds ADD COLUMN recurrence_schedule_id INTEGER REFERENCES round_recurrence_schedules(id) ON DELETE SET NULL');
            DB::statement('ALTER TABLE meeting_rounds ADD COLUMN recurrence_index INTEGER');
        } else {
            Schema::table('meeting_rounds', function (Blueprint $table) {
                $table->foreignId('recurrence_schedule_id')->nullable()
                    ->constrained('round_recurrence_schedules')->nullOnDelete();
                $table->unsignedSmallInteger('recurrence_index')->nullable();
            });
        }
        Schema::table('meeting_rounds', function (Blueprint $table) {
            $table->unique(['recurrence_schedule_id', 'recurrence_index']);
        });
    }

    public function down(): void
    {
        Schema::table('meeting_rounds', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['recurrence_schedule_id']);
            }
            $table->dropUnique(['recurrence_schedule_id', 'recurrence_index']);
            $table->dropColumn(['recurrence_schedule_id', 'recurrence_index']);
        });
        Schema::dropIfExists('round_recurrence_schedules');
    }
};
