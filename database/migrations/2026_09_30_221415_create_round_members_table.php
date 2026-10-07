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
        Schema::create('round_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('meeting_round_id')
                ->constrained('meeting_rounds')
                ->restrictOnDelete();

            $table->foreignId('room_member_id')
                ->constrained('room_members')
                ->restrictOnDelete();

            $table->string('role', 15);
            $table->decimal('weight', 6, 2);

            $table->dateTime('joined_at');
            $table->dateTime('confirmed_at')->nullable();

            $table->timestamps();

            $table->unique(['meeting_round_id', 'room_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_members');
    }
};
