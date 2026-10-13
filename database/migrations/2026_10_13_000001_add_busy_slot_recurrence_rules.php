<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('busy_slot_recurrence_rules', function (Blueprint $table) {
            $table->id();
            $table->string('frequency', 10);
            $table->unsignedInteger('occurrence_count');
            $table->timestamps();
        });

        Schema::table('user_busy_slots', function (Blueprint $table) {
            $table->foreignId('recurrence_rule_id')->nullable()->unique()
                ->constrained('busy_slot_recurrence_rules');
        });
    }

    public function down(): void
    {
        Schema::table('user_busy_slots', function (Blueprint $table) {
            $table->dropForeign(['recurrence_rule_id']);
            $table->dropUnique(['recurrence_rule_id']);
            $table->dropColumn('recurrence_rule_id');
        });
        Schema::dropIfExists('busy_slot_recurrence_rules');
    }
};
