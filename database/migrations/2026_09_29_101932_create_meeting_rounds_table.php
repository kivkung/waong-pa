<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meeting_rounds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('room_id')
                ->constrained('rooms')
                ->restrictOnDelete();

            $table->unsignedInteger('round_no');

            $table->string('status', 15)->default('active');
            $table->string('phase', 10)->default('join');

            $table->unsignedTinyInteger('active_marker')
                ->nullable()
                ->default(1);

            $table->date('search_start_date');
            $table->date('search_end_date');
            $table->time('daily_start_time')->default('08:00:00');
            $table->time('daily_end_time')->default('18:00:00');
            $table->unsignedSmallInteger('duration_minutes')->default(60);

            $table->enum('professor_rule', ['at_least', 'all'])->default('at_least');

            // nullable ยอมให้เก็บ NULL เมื่อเลือก all; ไม่ได้เปลี่ยนค่าให้อัตโนมัติ
            // Controller ต้องตรวจจำนวนเมื่อเลือก at_least และตั้งเป็น NULL เมื่อเลือก all
            $table->unsignedSmallInteger('min_professors')->nullable()->default(1);

            $table->dateTime('join_starts_at');
            $table->dateTime('review_starts_at');
            $table->dateTime('voting_starts_at');
            $table->dateTime('final_starts_at');
            // เลขรอบต้องไม่ซ้ำภายในห้องเดียวกัน
            $table->unique(['room_id', 'round_no']);

            // รอบ active ใช้ 1; เมื่อ completed/cancelled ต้องตั้งเป็น NULL พร้อมเปลี่ยน status
            $table->unique(['room_id', 'active_marker']);

            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_rounds');
    }
};
