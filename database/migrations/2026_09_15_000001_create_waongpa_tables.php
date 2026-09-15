<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('title');
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('source')->default('manual');
            $t->string('import_key')->nullable();
            $t->unique(['user_id', 'import_key']);
            $t->index(['user_id', 'starts_at']);
            $t->timestamps();
        });
        Schema::create('rooms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->string('name', 150);
            $t->text('description')->nullable();
            $t->string('visibility', 10)->default('private');
            $t->string('join_code', 12)->unique();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
        });
        Schema::create('room_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('room_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('role', 15)->default('student');
            $t->decimal('weight', 6, 2)->default(1);
            $t->string('status', 10)->default('active');
            $t->dateTime('joined_at');
            $t->dateTime('left_at')->nullable();
            $t->unique(['room_id', 'user_id']);
            $t->timestamps();
        });
        Schema::create('meeting_rounds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('room_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('round_no');
            $t->string('phase', 10)->default('join');
            // NULL for completed rounds; UNIQUE prevents two active rounds per room in SQLite.
            $t->unsignedTinyInteger('active_marker')->nullable()->default(1);
            $t->unique(['room_id', 'active_marker']);
            $t->unique(['room_id', 'round_no']);
            $t->date('search_start_date');
            $t->date('search_end_date');
            $t->time('daily_start_time')->default('08:00');
            $t->time('daily_end_time')->default('18:00');
            $t->unsignedSmallInteger('duration_minutes')->default(60);
            $t->string('professor_rule', 10)->default('at_least');
            $t->unsignedSmallInteger('min_professors')->nullable()->default(1);
            foreach (['join', 'review', 'voting', 'final'] as $phase) {
                $t->dateTime($phase.'_starts_at');
            }
            $t->unsignedInteger('version')->default(1);
            $t->unsignedInteger('reset_count')->default(0);
            $t->timestamps();
        });
        Schema::create('round_participants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('round_id')->constrained('meeting_rounds')->cascadeOnDelete();
            $t->foreignId('room_member_id')->constrained()->restrictOnDelete();
            $t->string('role', 15);
            $t->decimal('weight', 6, 2);
            $t->dateTime('schedule_copied_at')->nullable();
            $t->dateTime('schedule_confirmed_at')->nullable();
            $t->unique(['round_id', 'room_member_id']);
            $t->timestamps();
        });
        Schema::create('round_busy_periods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('participant_id')->constrained('round_participants')->cascadeOnDelete();
            $t->foreignId('source_event_id')->nullable()->constrained('personal_events')->nullOnDelete();
            $t->string('title');
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->index(['participant_id', 'starts_at']);
            $t->timestamps();
        });
        Schema::create('time_slots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('round_id')->constrained('meeting_rounds')->cascadeOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->decimal('weighted_score', 12, 2);
            $t->unsignedInteger('rank_no');
            $t->dateTime('calculated_at');
            $t->unique(['round_id', 'starts_at', 'ends_at']);
            $t->unique(['round_id', 'rank_no']);
            $t->timestamps();
        });
        Schema::create('votes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('participant_id')->unique()->constrained('round_participants')->cascadeOnDelete();
            $t->foreignId('time_slot_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
        });
        Schema::create('round_results', function (Blueprint $t) {
            $t->id();
            $t->foreignId('round_id')->unique()->constrained('meeting_rounds')->cascadeOnDelete();
            $t->foreignId('time_slot_id')->unique()->constrained()->restrictOnDelete();
            $t->string('decision_reason', 20);
            $t->dateTime('finalized_at');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['round_results', 'votes', 'time_slots', 'round_busy_periods', 'round_participants', 'meeting_rounds', 'room_members', 'rooms', 'personal_events'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
