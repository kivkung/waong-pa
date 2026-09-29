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
        Schema::create('room_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('room_id')
                ->constrained('rooms')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('role', 15)
                ->default('student');

            $table->decimal('weight', 6, 2)
                ->default(1);

            $table->string('status', 10)
                ->default('active');

            $table->dateTime('joined_at');
            $table->dateTime('left_at')->nullable();

            $table->timestamps();

            $table->unique(['room_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_members');
    }
};
